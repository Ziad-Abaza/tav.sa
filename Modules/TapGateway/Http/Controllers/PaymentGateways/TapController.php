<?php

namespace Modules\TapGateway\Http\Controllers\PaymentGateways;

use App\Events\TransactionCreated;
use App\Http\Controllers\Controller;
use App\Models\Invoice\Invoice;
use App\Models\Tenant;
use App\Models\TenantCreditBalance;
use App\Models\Transaction;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Modules\TapGateway\Services\TapPaymentGateway;

class TapController extends Controller
{
    /**
     * Tap payment gateway instance
     */
    protected TapPaymentGateway $gateway;

    /**
     * Create a new controller instance
     */
    public function __construct()
    {
        // Get Tap settings
        $settings = get_batch_settings([
            'payment.tap_enabled',
            'payment.tap_secret_key',
            'payment.tap_public_key',
            'payment.tap_sandbox_mode',
        ]);

        // Create gateway instance with correct keys
        $this->gateway = new TapPaymentGateway(
            $settings['payment.tap_secret_key'] ?? '',
            $settings['payment.tap_public_key'] ?? '',
            $settings['payment.tap_sandbox_mode'] ?? true
        );
    }

    /**
     * Get Tap settings from database
     */
    private function getTapSettings(): array
    {
        return get_batch_settings([
            'payment.tap_enabled',
            'payment.tap_secret_key',
            'payment.tap_public_key',
            'payment.tap_sandbox_mode',
        ]);
    }

    /**
     * Show the checkout page for an invoice
     */
    public function checkout(Request $request, string $subdomain, $invoiceId)
    {
        // Check if Tap is enabled
        $settings = $this->getTapSettings();
        if (empty($settings['payment.tap_enabled'])) {
            payment_log('Tap payment gateway is disabled', 'warning');

            return redirect()
                ->to(tenant_route('tenant.invoices.show', ['id' => $invoiceId]))
                ->with('error', t('tap_payment_not_available'));
        }

        $invoice = Invoice::where('id', $invoiceId)
            ->where('tenant_id', tenant_id())
            ->where('status', Invoice::STATUS_NEW)
            ->firstOrFail();

        try {
            // Create Tap charge
            $user = getUserByTenantId(tenant_id());
            $tenant = Tenant::find(tenant_id());
            $name = $user->firstname.' '.$user->lastname;

            $chargeData = [
                'amount' => (float) number_format($invoice->total(), 3, '.', ''), // Tap requires 3 decimal places
                'description' => "Invoice #{$invoice->invoice_number} - {$name}",
                'currency' => strtoupper($invoice->currency->code ?? 'USD'),
                'receipt' => [
                    'email' => true,
                    'sms' => true,
                ],
                'customer' => [
                    'first_name' => $user->firstname ?? 'Customer',
                    'last_name' => $user->lastname ?? '',
                    'email' => $user->email,
                    'phone' => [
                        'country_code' => '+1',
                        'number' => preg_replace('/[^0-9]/', '', $user->phone ?? '1234567890'),
                    ],
                ],
                'source' => [
                    'id' => 'src_card',
                ],
                'redirect' => [
                    'url' => tenant_route('tenant.payment.tap.callback', [
                        'subdomain' => $subdomain,
                        'invoice' => $invoice->id,
                    ]),
                ],
                'save_card' => false,
                'reference' => [
                    'transaction' => "inv_{$invoice->id}",
                    'order' => strval($invoice->id),
                ],
                'metadata' => [
                    'invoice_id' => strval($invoice->id),
                    'customer_id' => strval($user->id),
                    'tenant_id' => strval(tenant_id()),
                ],
                'merchant' => [
                    'id' => tenant_id(),
                ],
            ];

            $charge = $this->gateway->createCharge($chargeData);

            payment_log('Tap charge created successfully', 'info', [
                'charge_id' => $charge['id'],
                'invoice_id' => $invoice->id,
                'amount' => $invoice->total(),
            ]);

            // Create a pending transaction using the proper method
            $transaction = $invoice->createPendingTransaction($this->gateway, tenant_id());

            // Update transaction with Tap charge ID and metadata
            $transaction->update([
                'idempotency_key' => $charge['id'], // Use Tap charge ID as idempotency key
                'metadata' => [
                    'tap_charge_id' => $charge['id'],
                    'charge_created_at' => now()->toISOString(),
                    'tap_status' => $charge['status'],
                    'charge_type' => 'one_time',
                    'redirect_url' => $charge['transaction']['url'] ?? null,
                ],
            ]);

            // Check if payment requires redirect (3D Secure, etc.)
            if (isset($charge['transaction']['url'])) {
                return redirect($charge['transaction']['url']);
            }

            // Direct payment successful
            if ($charge['status'] === 'CAPTURED') {
                return $this->handleSuccessfulPayment($invoice, $transaction, $charge);
            }

            return view('TapGateway::payment.checkout', [
                'invoice' => $invoice,
                'charge' => $charge,
                'publicKey' => $this->gateway->getPublicKey(),
                'chargeId' => $charge['id'],
                'amount' => $invoice->total(),
                'currency' => $invoice->currency->code ?? 'USD',
                'remainingCredit' => TenantCreditBalance::getOrCreateBalance(tenant_id(), $invoice->currency_id)->balance ?? 0,
            ]);

        } catch (\Exception $e) {
            payment_log('Tap checkout error', 'error', [
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            session()->flash('notification', [
                'type' => 'danger',
                'message' => 'An error occurred while initiating payment: '.$e->getMessage(),
            ]);

            return redirect()
                ->to(tenant_route('tenant.invoices.show', ['id' => $invoice->id]))
                ->with('error', 'Payment processing failed: '.$e->getMessage());
        }
    }

    /**
     * Handle payment callback from Tap
     */
    public function callback(Request $request, string $subdomain, $invoiceId)
    {
        $invoice = Invoice::where('id', $invoiceId)
            ->where('tenant_id', tenant_id())
            ->firstOrFail();

        $tapId = $request->get('tap_id');
        if (! $tapId) {
            payment_log('Tap callback missing tap_id', 'warning', [
                'request_data' => $request->all(),
                'invoice_id' => $invoice->id,
            ]);

            return redirect()
                ->to(tenant_route('tenant.invoices.show', ['id' => $invoice->id]))
                ->with('error', t('payment_verification_failed'));
        }

        try {
            // Get the latest charge status from Tap API
            $charge = $this->gateway->getCharge($tapId);

            payment_log('Tap callback received', 'info', [
                'tap_id' => $tapId,
                'invoice_id' => $invoice->id,
                'status' => $charge['status'],
                'amount' => $charge['amount'] ?? 'unknown',
            ]);

            // Find the transaction
            $transaction = Transaction::where('invoice_id', $invoice->id)
                ->where('type', 'tap')
                ->whereJsonContains('metadata->tap_charge_id', $tapId)
                ->first();

            if (! $transaction) {
                // Look for transaction by idempotency_key as fallback
                $transaction = Transaction::where('invoice_id', $invoice->id)
                    ->where('type', 'tap')
                    ->where('idempotency_key', $tapId)
                    ->first();
            }

            if (! $transaction) {
                payment_log('Transaction not found for Tap callback', 'warning', [
                    'tap_id' => $tapId,
                    'invoice_id' => $invoice->id,
                ]);

                return redirect()
                    ->to(tenant_route('tenant.invoices.show', ['id' => $invoiceId]))
                    ->with('error', t('transaction_not_found'));
            }

            // Update transaction metadata with latest status
            $transaction->update([
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'last_callback_at' => now()->toISOString(),
                    'tap_status' => $charge['status'],
                    'callback_count' => ($transaction->metadata['callback_count'] ?? 0) + 1,
                ]),
            ]);

            // Handle different payment statuses
            switch ($charge['status']) {
                case 'CAPTURED':
                    return $this->handleSuccessfulPayment($invoice, $transaction, $charge);

                case 'PENDING':
                case 'INITIATED':
                    // For pending payments, show a waiting page with auto-refresh/polling
                    return $this->handlePendingPayment($invoice, $transaction, $charge);

                case 'REQUIRES_ACTION':
                    // Payment requires additional action (like 3D Secure)
                    return $this->handleActionRequiredPayment($invoice, $transaction, $charge);

                case 'FAILED':
                case 'DECLINED':
                case 'CANCELLED':
                    return $this->handleFailedPayment($invoice, $transaction, $charge);

                default:
                    // Unknown status - treat as pending and poll for updates
                    payment_log('Unknown Tap payment status', 'warning', [
                        'tap_id' => $tapId,
                        'status' => $charge['status'],
                        'invoice_id' => $invoice->id,
                    ]);

                    return $this->handlePendingPayment($invoice, $transaction, $charge);
            }

        } catch (\Exception $e) {
            payment_log('Tap callback error', 'error', [
                'tap_id' => $tapId,
                'invoice_id' => $invoice->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return redirect()
                ->to(tenant_route('tenant.invoices.show', ['id' => $invoiceId]))
                ->with('error', t('payment_verification_failed'));
        }
    }

    /**
     * Setup auto-billing data
     */
    public function autoBillingData(Request $request)
    {
        $returnUrl = $request->input('return_url');

        try {
            $user = getUserByTenantId(tenant_id());
            $tenant = Tenant::find(tenant_id());

            return view('TapGateway::payment.setup', [
                'publicKey' => $this->gateway->getPublicKey(),
                'customer' => [
                    'first_name' => $user->firstname ?? 'Customer',
                    'last_name' => $user->lastname ?? '',
                    'email' => $user->email,
                ],
                'returnUrl' => $returnUrl,
            ]);

        } catch (\Exception $e) {
            payment_log('Tap setup error', 'error', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return back()->with('error', 'An error occurred: '.$e->getMessage());
        }
    }

    /**
     * Handle successful payment
     */
    private function handleSuccessfulPayment(Invoice $invoice, Transaction $transaction, array $charge)
    {
        DB::beginTransaction();

        try {
            // Handle credit balance deduction if any was used
            $currency_id = $invoice->currency_id;
            $balance = TenantCreditBalance::getOrCreateBalance(tenant_id(), $currency_id);
            $total = $invoice->total();

            if ($balance->balance > 0) {
                $credit = $balance->balance;
                if ($credit > $total) {
                    $credit = $total;
                }

                // Deduct the credit that was used
                TenantCreditBalance::deductCredit(tenant_id(), $credit, 'Tap Payment Used Credit', $invoice->id);
            }

            // Update transaction with completion details
            $transaction->update([
                'status' => Transaction::STATUS_SUCCESS,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'completed_at' => now()->toISOString(),
                    'tap_status' => 'CAPTURED',
                    'gateway_fee' => $charge['fees'] ?? 0,
                    'payment_method' => $charge['source']['payment_method'] ?? 'card',
                ]),
            ]);

            // Mark invoice as paid
            $invoice->markAsPaid($transaction);

            // Fire the transaction created event
            event(new TransactionCreated($transaction->id, $invoice->id));

            DB::commit();

            payment_log('Tap payment successful', 'info', [
                'invoice_id' => $invoice->id,
                'transaction_id' => $transaction->id,
                'amount' => $invoice->total(),
            ]);

            return redirect()
                ->to(tenant_route('tenant.invoices.thank-you', ['id' => $invoice->id]))
                ->with('success', t('payment_successful'));

        } catch (\Exception $e) {
            DB::rollBack();

            payment_log('Error processing successful payment', 'error', [
                'invoice_id' => $invoice->id,
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->to(tenant_route('tenant.invoices.show', ['id' => $invoice->id]))
                ->with('error', t('payment_failed').': '.$e->getMessage());
        }
    }

    /**
     * Handle failed payment
     */
    private function handleFailedPayment(Invoice $invoice, Transaction $transaction, array $charge)
    {
        $errorMessage = $charge['response']['message'] ?? 'Payment failed';

        $transaction->update([
            'status' => Transaction::STATUS_FAILED,
            'metadata' => array_merge($transaction->metadata ?? [], [
                'failed_at' => now()->toISOString(),
                'failure_reason' => $errorMessage,
                'tap_status' => $charge['status'],
                'response_data' => json_encode($charge),
            ]),
        ]);

        payment_log('Tap payment failed', 'warning', [
            'invoice_id' => $invoice->id,
            'transaction_id' => $transaction->id,
            'error' => $errorMessage,
        ]);

        return redirect()
            ->to(tenant_route('tenant.invoices.show', ['id' => $invoice->id]))
            ->with('error', t('payment_failed').': '.$errorMessage);
    }

    /**
     * Handle pending payment - show waiting page with polling
     */
    private function handlePendingPayment(Invoice $invoice, Transaction $transaction, array $charge)
    {
        // Update transaction status to pending if not already
        if ($transaction->status !== Transaction::STATUS_PENDING) {
            $transaction->update([
                'status' => Transaction::STATUS_PENDING,
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'pending_since' => now()->toISOString(),
                    'tap_status' => $charge['status'],
                ]),
            ]);
        }

        payment_log('Tap payment pending', 'info', [
            'invoice_id' => $invoice->id,
            'transaction_id' => $transaction->id,
            'tap_id' => $charge['id'],
            'status' => $charge['status'],
        ]);

        // Show waiting page with auto-polling
        return view('TapGateway::payment.pending', [
            'invoice' => $invoice,
            'transaction' => $transaction,
            'charge' => $charge,
            'pollUrl' => tenant_route('tenant.payment.tap.status', [
                'subdomain' => request()->route('subdomain'),
                'transaction' => $transaction->id,
            ]),
        ]);
    }

    /**
     * Handle payment that requires additional action (3D Secure, etc.)
     */
    private function handleActionRequiredPayment(Invoice $invoice, Transaction $transaction, array $charge)
    {
        // Update transaction with action required status
        $transaction->update([
            'status' => Transaction::STATUS_PENDING,
            'metadata' => array_merge($transaction->metadata ?? [], [
                'action_required_at' => now()->toISOString(),
                'tap_status' => $charge['status'],
                'action_url' => $charge['transaction']['url'] ?? null,
            ]),
        ]);

        payment_log('Tap payment requires action', 'info', [
            'invoice_id' => $invoice->id,
            'transaction_id' => $transaction->id,
            'tap_id' => $charge['id'],
            'action_url' => $charge['transaction']['url'] ?? null,
        ]);

        // If there's an action URL, redirect to it
        if (isset($charge['transaction']['url'])) {
            return redirect($charge['transaction']['url']);
        }

        // Otherwise, show pending page
        return $this->handlePendingPayment($invoice, $transaction, $charge);
    }

    /**
     * Check payment status (API endpoint for polling)
     */
    public function checkPaymentStatus(Request $request, string $subdomain, string $transactionId)
    {
        try {
            $transaction = Transaction::where('id', $transactionId)
                ->where('type', 'tap')
                ->where('tenant_id', tenant_id())
                ->first();

            if (! $transaction) {
                return response()->json([
                    'success' => false,
                    'message' => 'Transaction not found',
                ], 404);
            }

            // Get Tap charge ID from transaction
            $tapChargeId = $transaction->metadata['tap_charge_id'] ?? $transaction->idempotency_key;

            if (! $tapChargeId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tap charge ID not found',
                ], 400);
            }

            // Get latest status from Tap API
            $charge = $this->gateway->getCharge($tapChargeId);

            // Update transaction with latest information
            $transaction->update([
                'metadata' => array_merge($transaction->metadata ?? [], [
                    'last_status_check' => now()->toISOString(),
                    'tap_status' => $charge['status'],
                    'status_check_count' => ($transaction->metadata['status_check_count'] ?? 0) + 1,
                ]),
            ]);

            $shouldRedirect = false;
            $redirectUrl = null;
            $message = '';

            // Handle status changes
            switch ($charge['status']) {
                case 'CAPTURED':
                    if ($transaction->status !== Transaction::STATUS_SUCCESS) {
                        // Process the successful payment
                        $invoice = $transaction->invoice;
                        $this->handleSuccessfulPayment($invoice, $transaction, $charge);

                        $shouldRedirect = true;
                        $redirectUrl = tenant_route('tenant.invoices.thank-you', ['id' => $invoice->id]);
                        $message = 'Payment completed successfully';
                    }
                    break;

                case 'FAILED':
                case 'DECLINED':
                case 'CANCELLED':
                    if ($transaction->status !== Transaction::STATUS_FAILED) {
                        // Process the failed payment
                        $invoice = $transaction->invoice;
                        $errorMessage = $charge['response']['message'] ?? 'Payment failed';

                        $transaction->update([
                            'status' => Transaction::STATUS_FAILED,
                            'error_message' => $errorMessage,
                            'metadata' => array_merge($transaction->metadata ?? [], [
                                'failed_at' => now()->toISOString(),
                                'failure_reason' => $errorMessage,
                            ]),
                        ]);

                        $shouldRedirect = true;
                        $redirectUrl = tenant_route('tenant.invoices.show', ['id' => $invoice->id]);
                        $message = 'Payment failed: '.$errorMessage;
                    }
                    break;

                case 'REQUIRES_ACTION':
                    // Check if there's an action URL to redirect to
                    if (isset($charge['transaction']['url'])) {
                        $shouldRedirect = true;
                        $redirectUrl = $charge['transaction']['url'];
                        $message = 'Payment requires additional action';
                    }
                    break;

                case 'PENDING':
                case 'INITIATED':
                default:
                    // Still pending, continue polling
                    break;
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'transaction_id' => $transaction->id,
                    'status' => $charge['status'],
                    'amount' => $charge['amount'],
                    'currency' => $charge['currency'],
                    'created_at' => $charge['created'],
                    'should_redirect' => $shouldRedirect,
                    'redirect_url' => $redirectUrl,
                    'message' => $message,
                    'continue_polling' => ! $shouldRedirect, // Stop polling if we need to redirect
                ],
            ]);

        } catch (\Exception $e) {
            payment_log('Error checking payment status', 'error', [
                'transaction_id' => $transactionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to check payment status: '.$e->getMessage(),
                'continue_polling' => true, // Continue polling on error
            ], 500);
        }
    }
}
