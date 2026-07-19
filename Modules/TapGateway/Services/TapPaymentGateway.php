<?php

namespace Modules\TapGateway\Services;

use App\Events\TransactionCreated;
use App\Models\Invoice\Invoice;
use App\Models\TenantCreditBalance;
use App\Models\Transaction;
use App\Services\Billing\TransactionResult;
use App\Services\PaymentGateways\Contracts\PaymentGatewayInterface;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Tap Payment Gateway Service
 *
 * Implements the PaymentGatewayInterface for Tap payment processing.
 * Handles online payment processing through Tap's REST API for the
 * Middle East and North Africa (MENA) region.
 *
 * Key Features:
 * - Credit/debit card processing
 * - Knet (Kuwait payment system) support
 * - Apple Pay and Google Pay integration
 * - 3D Secure authentication
 * - Sandbox and live environment support
 *
 * API Documentation: https://developers.tap.company/
 *
 * @author WhatsApp SaaS Team
 *
 * @version 1.0.0
 *
 * @since 1.0.0
 */
class TapPaymentGateway implements PaymentGatewayInterface
{
    /**
     * Gateway type identifier
     */
    public const TYPE = 'tap';

    /**
     * Tap API Base URLs
     */
    public const SANDBOX_URL = 'https://api.tap.company/v2';

    public const LIVE_URL = 'https://api.tap.company/v2';

    /**
     * Minimum charge amounts by currency
     */
    protected const MINIMUM_AMOUNTS = [
        'AED' => 1.00, // UAE Dirham
        'SAR' => 1.00, // Saudi Riyal
        'KWD' => 0.30, // Kuwaiti Dinar
        'BHD' => 0.38, // Bahraini Dinar
        'QAR' => 3.65, // Qatari Riyal
        'OMR' => 0.38, // Omani Rial
        'USD' => 1.00, // US Dollar
        'EUR' => 0.85, // Euro
        'GBP' => 0.75, // British Pound
    ];

    /**
     * Tap API secret key
     */
    protected string $secretKey;

    /**
     * Tap API public key
     */
    protected string $publicKey;

    /**
     * Sandbox mode flag
     */
    protected bool $sandboxMode;

    /**
     * Gateway activation status
     */
    protected bool $active = false;

    /**
     * Constructor
     */
    public function __construct(
        string $secretKey = '',
        string $publicKey = '',
        bool $sandboxMode = true
    ) {
        $this->secretKey = $secretKey;
        $this->publicKey = $publicKey;
        $this->sandboxMode = $sandboxMode;

        $this->validate();
    }

    /**
     * Validate gateway configuration and set active status
     */
    public function validate(): void
    {
        // Check if credentials are valid
        $hasCredentials = ! empty($this->secretKey) && ! empty($this->publicKey);

        // Check if the gateway is enabled by admin
        $settings = get_batch_settings([
            'payment.tap_enabled',
            'payment.tap_secret_key',
            'payment.tap_public_key',
        ]);

        $isEnabled = $settings['payment.tap_enabled'] ?? false;

        // Only active if both conditions are met
        $this->active = $hasCredentials && $isEnabled;
    }

    /**
     * Get the payment gateway name
     */
    public function getName(): string
    {
        return 'Tap Payments';
    }

    /**
     * Get the payment gateway type
     */
    public function getType(): string
    {
        return self::TYPE;
    }

    /**
     * Get the payment gateway description
     */
    public function getDescription(): string
    {
        return 'Pay securely with credit cards, Knet, Apple Pay, and Google Pay through Tap Payments.';
    }

    /**
     * Get the payment gateway short description
     */
    public function getShortDescription(): string
    {
        return 'Credit/Debit cards, Knet';
    }

    /**
     * Determine if the payment gateway is active
     */
    public function isActive(): bool
    {
        return $this->active;
    }

    /**
     * Get the payment gateway settings URL
     */
    public function getSettingsUrl(): string
    {
        return route('admin.settings.payment.tap');
    }

    /**
     * Get the checkout URL for the invoice
     */
    public function getCheckoutUrl(Invoice $invoice): string
    {
        return tenant_route('tenant.payment.tap.checkout', ['invoice' => $invoice->id]);
    }

    /**
     * Determine if the payment gateway supports auto billing
     */
    public function supportsAutoBilling(): bool
    {
        return true;
    }

    /**
     * Auto charge the invoice
     */
    public function autoCharge(Invoice $invoice, $remainingCredit = 0)
    {
        return new TransactionResult(
            false,
            'Tap payment gateway does not support auto charge!.'
        );
    }

    /**
     * Get the URL for updating auto-billing payment data
     */
    public function getAutoBillingDataUpdateUrl(string $returnUrl = '/'): string
    {
        return tenant_route('tenant.payment.tap.setup', ['return_url' => $returnUrl]);
    }

    /**
     * Verify a transaction status
     */
    public function verify(Transaction $transaction): TransactionResult
    {
        try {
            // Get Tap charge ID from transaction metadata or idempotency_key
            $chargeId = $transaction->metadata['tap_charge_id'] ?? $transaction->idempotency_key;

            if (! $chargeId) {
                return new TransactionResult(
                    TransactionResult::RESULT_FAILED,
                    'No Tap charge ID found for transaction'
                );
            }

            $response = $this->makeApiRequest('GET', "/charges/{$chargeId}");

            if ($response->successful()) {
                $data = $response->json();

                // Update transaction metadata with latest status
                $transaction->update([
                    'metadata' => array_merge($transaction->metadata ?? [], [
                        'last_verified_at' => now()->toISOString(),
                        'tap_status' => $data['status'],
                        'verification_count' => ($transaction->metadata['verification_count'] ?? 0) + 1,
                    ]),
                ]);

                switch ($data['status']) {
                    case 'CAPTURED':
                        // Update transaction to success if not already
                        if ($transaction->status !== Transaction::STATUS_SUCCESS) {
                            // Handle credit balance deduction if any was used
                            $invoice = $transaction->invoice;
                            if ($invoice) {
                                $currency_id = $invoice->currency_id;
                                $balance = TenantCreditBalance::getOrCreateBalance($invoice->tenant_id, $currency_id);
                                $total = $invoice->total();

                                if ($balance->balance > 0) {
                                    $credit = $balance->balance;
                                    if ($credit > $total) {
                                        $credit = $total;
                                    }

                                    // Deduct the credit that was used
                                    TenantCreditBalance::deductCredit($invoice->tenant_id, $credit, 'Tap Payment Used Credit', $invoice->id);
                                }
                            }

                            $transaction->update([
                                'status' => Transaction::STATUS_SUCCESS,
                                'metadata' => array_merge($transaction->metadata ?? [], [
                                    'completed_at' => now()->toISOString(),
                                    'gateway_fee' => $data['fees'] ?? 0,
                                    'payment_method' => $data['source']['payment_method'] ?? 'card',
                                ]),
                            ]);

                            // Fire the event
                            event(new TransactionCreated($transaction->id, $transaction->invoice_id));
                        }

                        return new TransactionResult(
                            TransactionResult::RESULT_DONE,
                            'Payment captured successfully'
                        );

                    case 'FAILED':
                    case 'DECLINED':
                    case 'CANCELLED':
                        // Update transaction to failed if not already
                        if ($transaction->status !== Transaction::STATUS_FAILED) {
                            $errorMessage = $data['response']['message'] ?? 'Payment failed';

                            $transaction->update([
                                'status' => Transaction::STATUS_FAILED,
                                'error' => $errorMessage,
                                'metadata' => array_merge($transaction->metadata ?? [], [
                                    'failed_at' => now()->toISOString(),
                                    'failure_reason' => $errorMessage,
                                    'failure_code' => $data['response']['code'] ?? null,
                                ]),
                            ]);
                        }

                        return new TransactionResult(
                            TransactionResult::RESULT_FAILED,
                            $data['response']['message'] ?? 'Payment failed'
                        );

                    case 'REQUIRES_ACTION':
                        // Payment requires additional customer action
                        $transaction->update([
                            'metadata' => array_merge($transaction->metadata ?? [], [
                                'action_required_at' => now()->toISOString(),
                                'action_url' => $data['transaction']['url'] ?? null,
                            ]),
                        ]);

                        return new TransactionResult(
                            TransactionResult::RESULT_PENDING,
                            'Payment requires customer action',
                            [
                                'action_url' => $data['transaction']['url'] ?? null,
                                'requires_action' => true,
                            ]
                        );

                    case 'PENDING':
                    case 'INITIATED':
                        // Update transaction to pending if not already
                        if ($transaction->status !== Transaction::STATUS_PENDING) {
                            $transaction->update([
                                'status' => Transaction::STATUS_PENDING,
                            ]);
                        }

                        return new TransactionResult(
                            TransactionResult::RESULT_PENDING,
                            'Payment is being processed'
                        );

                    default:
                        // Unknown status - log for investigation
                        Log::warning('Unknown Tap payment status encountered', [
                            'transaction_id' => $transaction->id,
                            'charge_id' => $chargeId,
                            'status' => $data['status'],
                            'data' => $data,
                        ]);

                        return new TransactionResult(
                            TransactionResult::RESULT_PENDING,
                            'Payment status unknown: '.$data['status']
                        );
                }
            }

            // API request failed
            $errorMessage = 'Failed to verify payment status';
            if ($response->status() === 404) {
                $errorMessage = 'Payment not found in Tap system';
            } elseif ($response->status() >= 500) {
                $errorMessage = 'Tap payment system temporarily unavailable';
            }

            return new TransactionResult(
                TransactionResult::RESULT_FAILED,
                $errorMessage
            );

        } catch (\Exception $e) {
            Log::error('Tap payment verification failed', [
                'transaction_id' => $transaction->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return new TransactionResult(
                TransactionResult::RESULT_FAILED,
                'Payment verification failed: '.$e->getMessage()
            );
        }
    }

    /**
     * Determine if manual transaction review is allowed
     */
    public function allowManualReviewingOfTransaction(): bool
    {
        return false;
    }

    /**
     * Get the minimum charge amount for a currency
     */
    public function getMinimumChargeAmount($currency): float
    {
        return self::MINIMUM_AMOUNTS[strtoupper($currency)] ?? 1.00;
    }

    /**
     * Create a payment charge
     */
    public function createCharge(array $data): array
    {
        $response = $this->makeApiRequest('POST', '/charges', $data);

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to create charge: '.$response->body());
    }

    /**
     * Retrieve a charge by ID
     */
    public function getCharge(string $chargeId): array
    {
        $response = $this->makeApiRequest('GET', "/charges/{$chargeId}");

        if ($response->successful()) {
            return $response->json();
        }

        throw new \Exception('Failed to retrieve charge: '.$response->body());
    }

    /**
     * Make API request to Tap
     */
    public function makeApiRequest(string $method, string $endpoint, array $data = []): Response
    {
        $url = $this->getApiUrl().$endpoint;

        // Get the actual secret key from settings
        $secretKey = $this->getSecretKey();

        if (empty($secretKey)) {
            throw new \Exception('Tap secret key is not configured');
        }

        $client = Http::withHeaders([
            'Authorization' => 'Bearer '.$secretKey,
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])->timeout(30);

        return match (strtoupper($method)) {
            'GET' => $client->get($url),
            'POST' => $client->post($url, $data),
            'PUT' => $client->put($url, $data),
            'DELETE' => $client->delete($url),
            default => throw new \InvalidArgumentException("Unsupported HTTP method: {$method}")
        };
    }

    /**
     * Get the appropriate API URL based on sandbox mode
     */
    protected function getApiUrl(): string
    {
        $sandboxMode = $this->isSandboxMode();

        return $sandboxMode ? self::SANDBOX_URL : self::LIVE_URL;
    }

    /**
     * Get Tap secret key from settings
     */
    public function getSecretKey(): string
    {
        $settings = get_batch_settings(['payment.tap_secret_key']);

        return $settings['payment.tap_secret_key'] ?? $this->secretKey;
    }

    /**
     * Get Tap public key from settings
     */
    public function getPublicKey(): string
    {
        $settings = get_batch_settings(['payment.tap_public_key']);

        return $settings['payment.tap_public_key'] ?? $this->publicKey;
    }

    /**
     * Check if sandbox mode is enabled
     */
    public function isSandboxMode(): bool
    {
        $settings = get_batch_settings(['payment.tap_sandbox_mode']);

        return $settings['payment.tap_sandbox_mode'] ?? $this->sandboxMode;
    }
}
