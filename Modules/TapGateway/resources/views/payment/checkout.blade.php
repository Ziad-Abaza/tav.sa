<x-app-layout>
    <x-slot:title>
        {{ t('tap_payment') ?? 'Tap Payment' }}
    </x-slot:title>

    <div class="max-w-full mx-auto">
        <div class="flex flex-col lg:flex-row gap-6">
            <!-- First Card: Invoice Details -->
            <x-card class="w-full lg:w-1/2">
                <x-slot:header>
                    <div class="flex items-center space-x-3">
                        <div class="w-6 h-6 sm:w-10 sm:h-10 bg-primary-100 rounded-full flex items-center justify-center">
                            <x-heroicon-o-credit-card class="w-6 h-6 text-primary-600" />
                        </div>
                        <div>
                            <h2 class="text-xl font-bold text-gray-900 dark:text-gray-300">
                                {{ t('tap_payment') ?? 'Tap Payment' }}
                            </h2>
                            <p class="text-sm text-gray-500 dark:text-gray-300">
                                {{ t('secure_payment_with_tap') ?? 'Secure payment with Tap Payments' }}
                            </p>
                        </div>
                    </div>
                </x-slot:header>

                <x-slot:content>
                    <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden shadow sm:rounded-lg"
                        x-data="{ expanded: true }">
                        <div class="flex items-center justify-between px-4 py-5 sm:px-6 bg-primary-50 dark:bg-slate-700 cursor-pointer"
                            @click="expanded = !expanded">
                            <div class="flex items-center">
                                <x-heroicon-s-receipt-refund class="h-6 w-6 text-gray-600 dark:text-gray-400 mr-3" />
                                <h2 class="text-lg font-medium text-gray-900 dark:text-slate-200">
                                    {{ t('invoice_details') ?? 'Invoice Details' }}
                                </h2>
                            </div>
                            <div class="flex items-center">
                                <span class="mr-3 text-sm font-semibold text-primary-600 dark:text-slate-200">
                                    @if(isset($invoice))
                                        {{ $invoice->formattedTotal() }}
                                    @else
                                        {{ formatMoney($amount, $currency) }}
                                    @endif
                                </span>
                                <x-heroicon-s-chevron-down x-show="!expanded" class="h-5 w-5 text-gray-500" />
                                <x-heroicon-s-chevron-up x-show="expanded" class="h-5 w-5 text-gray-500" />
                            </div>
                        </div>

                        <div x-show="expanded" x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100"
                            x-transition:leave="transition ease-in duration-150" x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0">
                            <dl class="divide-y divide-gray-200 dark:divide-slate-700">
                                @if(isset($invoice))
                                    <div class="px-4 py-4 sm:px-6 grid grid-cols-2">
                                        <dt class="text-sm font-medium text-gray-500 text-left">{{ t('invoice_number') ?? 'Invoice Number' }}</dt>
                                        <dd class="text-sm text-gray-900 dark:text-slate-200 text-right">
                                            {{ $invoice->invoice_number ?? format_draft_invoice_number() }}
                                        </dd>
                                    </div>

                                    <div class="px-4 py-4 sm:px-6 sm:grid sm:grid-cols-2">
                                        <dt class="text-sm font-medium text-gray-500 text-left">{{ t('description') ?? 'Description' }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-slate-200 sm:mt-0 text-right">
                                            {{ $invoice->title }}
                                        </dd>
                                    </div>
                                    
                                    <div class="px-4 py-4 sm:px-6 sm:grid sm:grid-cols-2">
                                        <dt class="text-sm font-medium text-gray-500 text-left">{{ t('subtotal') ?? 'Subtotal' }}</dt>
                                        <dd class="mt-1 text-sm text-gray-900 dark:text-slate-200 sm:mt-0 text-right">
                                            {{ $invoice->formatAmount($invoice->subTotal()) }}
                                        </dd>
                                    </div>

                                    @php
                                        // Make sure taxes are calculated and applied
                                        if ($invoice->taxes()->count() === 0) {
                                            $invoice->applyTaxes();
                                        }

                                        // Recalculate tax details after ensuring they're applied
                                        $taxDetails = $invoice->getTaxDetails();
                                        $totalTaxAmount = 0;
                                        $taxBreakdown = [];

                                        // Calculate total tax amount from tax details
                                        foreach ($taxDetails as $tax) {
                                            $taxBreakdown[] = $tax['formatted_rate'] . ' ' . $tax['name'];
                                            $totalTaxAmount += $tax['amount'];
                                        }

                                        // Force recalculation of total with taxes
                                        $invoice->calculateTotalTaxAmount();

                                        // Log values for debugging
                                        $subtotal = $invoice->subTotal();
                                        $tax = $invoice->getTax();
                                        $fee = $invoice->fee ?: 0;
                                        $calculatedTotal = $subtotal + $tax + $fee;
                                    @endphp

                                    <!-- Detailed tax breakdown -->
                                    @if (count($taxDetails) > 0)
                                        @foreach ($taxDetails as $tax)
                                            <div class="px-4 py-4 sm:px-6 sm:grid sm:grid-cols-2">
                                                <dt class="text-sm font-medium text-gray-500 text-left">
                                                    {{ $tax['name'] }} ({{ $tax['formatted_rate'] }})
                                                </dt>
                                                <dd class="mt-1 text-sm text-gray-900 dark:text-slate-200 sm:mt-0 text-right">
                                                    @php
                                                        // Calculate tax amount based on rate and subtotal if it's showing as 0
                                                        $taxAmount = $tax['amount'];
                                                        if ($taxAmount <= 0 && $tax['rate'] > 0) {
                                                            $taxAmount = $invoice->subTotal() * ($tax['rate'] / 100);
                                                        }
                                                        echo $invoice->formatAmount($taxAmount);
                                                    @endphp
                                                </dd>
                                            </div>
                                        @endforeach
                                    @endif

                                    @if ($invoice->fee > 0)
                                        <div class="px-4 py-4 sm:px-6 sm:grid sm:grid-cols-2">
                                            <dt class="text-sm font-medium text-gray-500 text-left">{{ t('fee') ?? 'Fee' }}</dt>
                                            <dd class="mt-1 text-sm text-gray-900 dark:text-slate-200 sm:mt-0 text-right">
                                                {{ $invoice->formatAmount($invoice->fee) }}
                                            </dd>
                                        </div>
                                    @endif

                                    <div class="px-4 py-4 sm:px-6 sm:grid sm:grid-cols-2 bg-gray-50 dark:bg-slate-800">
                                        <dt class="text-sm font-medium text-gray-900 dark:text-slate-500 text-left">
                                            {{ t('total_amount') ?? 'Total Amount' }}
                                        </dt>
                                        <dd class="mt-1 text-sm font-bold text-primary-600 dark:text-slate-200 sm:mt-0 text-right">
                                            {{ $invoice->formattedTotal() }}
                                        </dd>
                                    </div>

                                    @if (isset($remainingCredit) && $remainingCredit > 0)
                                        <div class="px-4 py-4 sm:px-6 sm:grid sm:grid-cols-2 dark:bg-slate-800">
                                            <dt class="text-sm font-medium text-gray-900 dark:text-slate-500 text-left">
                                                {{ t('total_credit_remaining') ?? 'Total Credit Remaining' }}
                                            </dt>
                                            <dd class="mt-1 text-sm font-bold text-info-600 dark:text-slate-200 sm:mt-0 text-right">
                                                -{{ $invoice->formatAmount($remainingCredit) }}
                                            </dd>
                                        </div>
                                        <div class="px-4 py-4 sm:px-6 sm:grid sm:grid-cols-2 bg-gray-50 dark:bg-slate-800">
                                            <dt class="text-sm font-medium text-gray-900 dark:text-slate-500 text-left">
                                                {{ t('final_payable_amount') ?? 'Final Payable Amount' }}
                                            </dt>
                                            <dd class="mt-1 text-sm font-bold text-info-600 dark:text-slate-200 sm:mt-0 text-right">
                                                @php
                                                    $finalAmount = max($invoice->total() - $remainingCredit, 0);
                                                    echo $invoice->formatAmount($finalAmount);
                                                @endphp
                                            </dd>
                                        </div>
                                    @endif
                                @else
                                    @if(isset($plan))
                                        <div class="px-4 py-4 sm:px-6 sm:grid sm:grid-cols-2">
                                            <dt class="text-sm font-medium text-gray-500 text-left">{{ t('plan') ?? 'Plan' }}</dt>
                                            <dd class="mt-1 text-sm text-gray-900 dark:text-slate-200 sm:mt-0 text-right">{{ $plan->name }}</dd>
                                        </div>
                                        
                                        @if($plan->interval)
                                            <div class="px-4 py-4 sm:px-6 sm:grid sm:grid-cols-2">
                                                <dt class="text-sm font-medium text-gray-500 text-left">{{ t('billing_cycle') ?? 'Billing Cycle' }}</dt>
                                                <dd class="mt-1 text-sm text-gray-900 dark:text-slate-200 sm:mt-0 text-right">
                                                    {{ t($plan->interval) }}
                                                </dd>
                                            </div>
                                        @endif
                                    @endif
                                    
                                    <div class="px-4 py-4 sm:px-6 sm:grid sm:grid-cols-2 bg-gray-50 dark:bg-slate-700">
                                        <dt class="text-lg font-semibold text-gray-900 dark:text-slate-200 text-left">{{ t('total_amount') ?? 'Total Amount' }}</dt>
                                        <dd class="mt-1 text-lg font-bold text-primary-600 dark:text-primary-400 sm:mt-0 text-right">
                                            {{ formatMoney($amount, $currency) }}
                                        </dd>
                                    </div>
                                @endif
                            </dl>
                        </div>
                    </div>
                </x-slot:content>
            </x-card>

            <!-- Payment Details -->
            <x-card class="w-full lg:w-1/2">
                <x-slot:header>
                    <div class="flex items-center">
                        <x-heroicon-s-credit-card class="h-6 w-6 text-gray-500 dark:text-gray-400 mr-3" />
                        <h2 class="text-lg font-medium text-gray-900 dark:text-slate-200">
                            {{ t('payment_details') ?? 'Payment Details' }}
                        </h2>
                    </div>
                </x-slot:header>

                <x-slot:content>
                    <div class="px-4 py-5 sm:p-6">
                        <div id="payment-form-container" class="mt-12">
                            @php
                                // Calculate final payable amount after credit deduction
                                if (isset($invoice)) {
                                    $payAmount = $invoice->formattedTotal();
                                    $finalPayableAmount = $invoice->total();
                                    
                                    if (isset($remainingCredit) && $remainingCredit > 0) {
                                        $finalPayableAmount = max($invoice->total() - $remainingCredit, 0);
                                        $payAmount = $invoice->formatAmount($finalPayableAmount);
                                    }
                                } else {
                                    $payAmount = formatMoney($amount, $currency);
                                    $finalPayableAmount = $amount;
                                }
                            @endphp

                            <div class="text-center mb-6">
                                <p class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                                    {{ t('tap_payment_methods') ?? 'Visa, Mastercard, American Express, and local payment methods' }}
                                </p>
                                <div class="flex justify-center space-x-4 mb-6">
                                    <span class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">Visa</span>
                                    <span class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">Mastercard</span>
                                    <span class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">AMEX</span>
                                    <span class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">KNET</span>
                                    <span class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">Apple Pay</span>
                                    <span class="text-xs bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded">Google Pay</span>
                                </div>
                            </div>

                            <div class="text-center">
                                <button type="button" id="tap-payment-button"
                                    class="inline-flex items-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-green-600 hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 disabled:opacity-50">
                                    <x-heroicon-o-credit-card class="w-5 h-5 mr-2" />
                                    {{ t('pay_with_tap') ?? 'Pay with Tap' }} {{ $payAmount }}
                                </button>
                            </div>
                        </div>

                        <!-- Success Message -->
                        <div id="payment-success" class="hidden mt-6">
                            <div class="bg-white dark:bg-slate-800 overflow-hidden shadow sm:rounded-lg border border-slate-200 dark:border-slate-700 mt-6">
                                <div class="px-4 py-5 sm:p-6">
                                    <div class="text-center">
                                        <div class="mx-auto flex h-12 w-12 items-center justify-center rounded-full bg-success-100 dark:bg-success-900">
                                            <x-heroicon-s-check class="h-6 w-6 text-success-600 dark:text-success-300" />
                                        </div>
                                        <h3 class="mt-2 text-lg font-medium text-gray-900 dark:text-slate-200">
                                            {{ t('payment_successful') ?? 'Payment Successful' }}
                                        </h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-slate-400">
                                            {{ t('payment_process_successfully') ?? 'Your payment has been processed successfully' }}
                                        </p>
                                        <div class="mt-6">
                                            <a href="#" id="success-redirect"
                                                class="inline-flex items-center px-4 py-2 border border-transparent text-sm font-medium rounded-md shadow-sm text-white bg-info-600 hover:bg-info-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-info-500">
                                                {{ t('continue') ?? 'Continue' }}
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Help Section -->
                        <div class="rounded-md bg-gray-50 p-4 shadow-sm dark:bg-slate-700 mt-24">
                            <div class="flex">
                                <div class="flex-shrink-0">
                                    <x-heroicon-o-light-bulb class="h-5 w-5 text-gray-400" />
                                </div>
                                <div class="ml-3 flex-1 md:flex md:justify-between">
                                    <p class="text-sm text-gray-400">
                                        {{ t('need_assistance_with_payment') ?? 'Need assistance with payment?' }}
                                    </p>
                                    <p class="mt-3 text-sm md:mt-0 md:ml-6">
                                        @if(function_exists('tenant_route'))
                                            <a href="{{ tenant_route('tenant.tickets.index') }}"
                                                class="whitespace-nowrap font-medium text-info-600 dark:text-info-500 hover:text-info-500">
                                                {{ t('contact_support') ?? 'Contact Support' }} <span aria-hidden="true">&rarr;</span>
                                            </a>
                                        @else
                                            <a href="#"
                                                class="whitespace-nowrap font-medium text-info-600 dark:text-info-500 hover:text-info-500">
                                                {{ t('contact_support') ?? 'Contact Support' }} <span aria-hidden="true">&rarr;</span>
                                            </a>
                                        @endif
                                    </p>
                                </div>
                            </div>
                        </div>
                    </div>
                </x-slot:content>
            </x-card>
        </div>

        <!-- Hidden Form for Tap Payment Processing -->
        <form id="tap-payment-form" method="GET" action="{{ isset($charge['transaction']['url']) ? $charge['transaction']['url'] : '#' }}" style="display: none;">
            <!-- No form data needed - redirecting to Tap's hosted payment page -->
        </form>
    </div>

    @push('scripts')
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const tapPaymentButton = document.getElementById('tap-payment-button');
            const paymentSuccess = document.getElementById('payment-success');
            const paymentFormContainer = document.getElementById('payment-form-container');

            @if(isset($charge['transaction']['url']))
                // Tap payment URL is available - redirect to Tap's hosted payment page
                const tapPaymentUrl = '{{ $charge['transaction']['url'] }}';
                
                tapPaymentButton.addEventListener('click', function(e) {
                    e.preventDefault();
                    
                    // Show loading state
                    tapPaymentButton.disabled = true;
                    tapPaymentButton.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>{{ t("processing_payment") ?? "Processing payment..." }}';

                    // Small delay to show loading state, then redirect
                    setTimeout(() => {
                        window.location.href = tapPaymentUrl;
                    }, 500);
                });
            @else
                @if(isset($charge['id']))
                    tapPaymentButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        
                        // Show loading state
                        tapPaymentButton.disabled = true;
                        tapPaymentButton.innerHTML = '<svg class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 814 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>{{ t("processing_payment") ?? "Processing payment..." }}';
                        
                        // Check if there's a way to manually trigger the payment
                        @if(isset($charge['status']) && $charge['status'] === 'INITIATED')
                            // For INITIATED charges, there might be a different workflow
                            alert('Payment initiated but no redirect URL provided. Please contact support.');
                            
                            // Reset button
                            tapPaymentButton.disabled = false;
                            tapPaymentButton.innerHTML = '<x-heroicon-o-credit-card class="w-5 h-5 mr-2" />{{ t("pay_with_tap") ?? "Pay with Tap" }} {{ isset($payAmount) ? $payAmount : (isset($invoice) ? $invoice->formattedTotal() : formatMoney($amount, $currency)) }}';
                        @else
                            // If we have the public key, try using Tap's client-side SDK
                            @if(isset($publicKey))
                                try {
                                    // This would require Tap's JavaScript SDK to be loaded
                                    // For now, show an error and instructions
                                    alert('Payment initialization failed. Please refresh the page and try again, or contact support if the issue persists.');
                                    
                                    // Reset button
                                    tapPaymentButton.disabled = false;
                                    tapPaymentButton.innerHTML = '<x-heroicon-o-credit-card class="w-5 h-5 mr-2" />{{ t("pay_with_tap") ?? "Pay with Tap" }} {{ isset($payAmount) ? $payAmount : (isset($invoice) ? $invoice->formattedTotal() : formatMoney($amount, $currency)) }}';
                                } catch (e) {
                                    console.error('Alternative payment method failed:', e);
                                    alert('Payment processing error. Please contact support.');
                                    
                                    // Reset button
                                    tapPaymentButton.disabled = false;
                                    tapPaymentButton.innerHTML = '<x-heroicon-o-credit-card class="w-5 h-5 mr-2" />{{ t("pay_with_tap") ?? "Pay with Tap" }} {{ isset($payAmount) ? $payAmount : (isset($invoice) ? $invoice->formattedTotal() : formatMoney($amount, $currency)) }}';
                                }
                            @else
                                alert('Payment configuration error. Please contact support.');
                                
                                // Reset button
                                tapPaymentButton.disabled = false;
                                tapPaymentButton.innerHTML = '<x-heroicon-o-credit-card class="w-5 h-5 mr-2" />{{ t("pay_with_tap") ?? "Pay with Tap" }} {{ isset($payAmount) ? $payAmount : (isset($invoice) ? $invoice->formattedTotal() : formatMoney($amount, $currency)) }}';
                            @endif
                        @endif
                    });
                @else
                    // No charge data at all - show error
                    console.error('No charge data available');
                    
                    tapPaymentButton.addEventListener('click', function(e) {
                        e.preventDefault();
                        alert('{{ t("payment_unavailable") ?? "Payment processing is currently unavailable. Please try again later." }}');
                    });
                @endif
            @endif

            // Handle success redirect if it exists in URL parameters
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.get('payment_status') === 'success') {
                showPaymentSuccess(urlParams.get('redirect_url') || '{{ $cancel_url ?? tenant_route("tenant.billing") }}');
            } else if (urlParams.get('payment_status') === 'failed') {
                showPaymentError(urlParams.get('error_message') || '{{ t("payment_failed") ?? "Payment failed. Please try again." }}');
            }

            function showPaymentSuccess(redirectUrl) {
                paymentFormContainer.style.display = 'none';
                paymentSuccess.classList.remove('hidden');
                document.getElementById('success-redirect').href = redirectUrl;

                // Auto redirect after 3 seconds
                setTimeout(() => {
                    window.location.href = redirectUrl;
                }, 3000);
            }

            function showPaymentError(errorMessage) {
                // Reset button
                tapPaymentButton.disabled = false;
                tapPaymentButton.innerHTML = '<x-heroicon-o-credit-card class="w-5 h-5 mr-2" />{{ t("pay_with_tap") ?? "Pay with Tap" }} {{ isset($payAmount) ? $payAmount : (isset($invoice) ? $invoice->formattedTotal() : formatMoney($amount, $currency)) }}';
                
                // Show error message
                alert(errorMessage);
            }
        });
    </script>
    @endpush
</x-app-layout>