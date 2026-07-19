<x-app-layout>
    <x-slot:title>
        {{ t('processing_payment_title') ?? 'Processing Payment' }}
    </x-slot:title>

    <div class="max-w-3xl mx-auto">
        <x-card>
            <!-- Enhanced Header Section -->
            <x-slot:header>
                <div class="flex items-center space-x-3">
                    <div class="w-6 h-6 sm:w-10 sm:h-10 bg-blue-100 rounded-full flex items-center justify-center">
                        <x-heroicon-o-clock class="w-6 h-6 text-blue-600 animate-pulse" />
                    </div>
                    <div>
                        <h2 class="text-xl font-bold text-gray-900 dark:text-gray-300">
                            {{ t('processing_payment_title') ?? 'Processing Payment' }}
                        </h2>
                        <p class="text-sm text-gray-500 dark:text-gray-300">
                            {{ t('please_wait_payment_processing') ?? 'Please wait while we process your payment' }}
                        </p>
                    </div>
                </div>
            </x-slot:header>

            <x-slot:content>
                <!-- Loading Animation Section -->
                <div class="text-center py-8">
                    <!-- Enhanced Loading Animation -->
                    <div class="mx-auto flex items-center justify-center h-20 w-20 rounded-full bg-blue-100 dark:bg-blue-900/20 mb-6">
                        <svg class="animate-spin h-10 w-10 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                    </div>

                    <h3 class="text-lg font-medium text-gray-900 dark:text-slate-200 mb-2">
                        {{ t('processing_your_payment') ?? 'Processing Your Payment' }}
                    </h3>
                    
                    <p class="text-sm text-gray-600 dark:text-gray-400 mb-6">
                        {{ t('payment_processing_description') ?? 'Please wait while we process your payment through Tap Payments. This may take a few moments.' }}
                    </p>
                </div>

                <!-- Invoice Details Panel -->
                <div class="bg-white dark:bg-slate-800 border border-slate-200 dark:border-slate-700 overflow-hidden shadow sm:rounded-lg mb-6">
                    <div class="px-4 py-5 sm:px-6 bg-gray-50 dark:bg-slate-700">
                        <div class="flex items-center">
                            <x-heroicon-s-document-text class="h-6 w-6 text-gray-600 dark:text-gray-400 mr-3" />
                            <h3 class="text-lg font-medium text-gray-900 dark:text-slate-200">
                                {{ t('payment_details') ?? 'Payment Details' }}
                            </h3>
                        </div>
                    </div>
                    
                    <dl class="divide-y divide-gray-200 dark:divide-slate-700">
                        <div class="px-4 py-4 sm:px-6 grid grid-cols-2">
                            <dt class="text-sm font-medium text-gray-500 text-left">{{ t('invoice') ?? 'Invoice' }}:</dt>
                            <dd class="text-sm text-gray-900 dark:text-slate-200 text-right font-semibold">#{{ $invoice->invoice_number }}</dd>
                        </div>
                        <div class="px-4 py-4 sm:px-6 grid grid-cols-2">
                            <dt class="text-sm font-medium text-gray-500 text-left">{{ t('amount') ?? 'Amount' }}:</dt>
                            <dd class="text-sm text-gray-900 dark:text-slate-200 text-right font-semibold">{{ format_currency($invoice->total_amount, $invoice->currency) }}</dd>
                        </div>
                        <div class="px-4 py-4 sm:px-6 grid grid-cols-2 bg-blue-50 dark:bg-blue-900/20">
                            <dt class="text-sm font-medium text-gray-500 text-left">{{ t('status') ?? 'Status' }}:</dt>
                            <dd class="text-sm font-medium text-blue-600 dark:text-blue-400 text-right" id="payment-status">
                                {{ t('processing') ?? 'Processing...' }}
                            </dd>
                        </div>
                    </dl>
                </div>

                <!-- Status Messages -->
                <div class="text-center mb-6">
                    <div id="status-message" class="text-sm text-gray-600 dark:text-gray-400 mb-4">
                        {{ t('verifying_payment_tap') ?? "We're verifying your payment with Tap Payments..." }}
                    </div>

                    <!-- Enhanced Progress Bar -->
                    <div class="w-full bg-gray-200 dark:bg-gray-700 rounded-full h-3 mb-6">
                        <div id="progress-bar" class="bg-gradient-to-r from-blue-500 to-blue-600 h-3 rounded-full transition-all duration-500 ease-out" style="width: 0%"></div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-col sm:flex-row gap-4 justify-center">
                    <!-- Manual Refresh Button (initially hidden) -->
                    <button id="manual-refresh" onclick="window.location.reload()" 
                            class="hidden">
                        <span class="inline-flex items-center justify-center px-6 py-3 border border-transparent text-base font-medium rounded-md shadow-sm text-white bg-blue-600 hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500">
                            <x-heroicon-o-arrow-path class="w-5 h-5 mr-2" />
                            {{ t('refresh_status') ?? 'Refresh Status' }}
                        </span>
                    </button>

                    <!-- Back to Invoice Button -->
                    <a href="{{ tenant_route('tenant.invoices.show', ['id' => $invoice->id]) }}" 
                       class="inline-flex items-center justify-center px-6 py-3 border border-gray-300 dark:border-gray-600 text-base font-medium rounded-md text-gray-700 dark:text-gray-300 bg-white dark:bg-gray-700 hover:bg-gray-50 dark:hover:bg-gray-600 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-primary-500">
                        <x-heroicon-o-arrow-left class="w-5 h-5 mr-2" />
                        {{ t('back_to_invoice') ?? 'Back to Invoice' }}
                    </a>
                </div>

                <!-- Support Notice -->
                <div class="mt-8 bg-yellow-50 dark:bg-yellow-900/20 border border-yellow-200 dark:border-yellow-800 rounded-lg p-4">
                    <div class="flex">
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-yellow-500 mr-3 mt-0.5" />
                        <div class="text-sm text-yellow-700 dark:text-yellow-300">
                            <p class="font-medium">{{ t('payment_taking_longer') ?? 'Payment taking longer than expected?' }}</p>
                            <p>{{ t('payment_support_message') ?? 'If the payment verification takes more than 5 minutes, please contact our support team for assistance.' }}</p>
                        </div>
                    </div>
                </div>
            </x-slot:content>
        </x-card>
    </div>
    </div>

    @push('scripts')
    <script>
    (function() {
        const pollUrl = @json($pollUrl);
        const maxPollAttempts = 60; // Poll for up to 5 minutes (60 * 5 seconds)
        let pollAttempts = 0;
        let pollInterval;

        const statusElement = document.getElementById('payment-status');
        const messageElement = document.getElementById('status-message');
        const progressBar = document.getElementById('progress-bar');
        const manualRefreshBtn = document.getElementById('manual-refresh');

        // Show manual refresh button
        function showManualRefresh() {
            manualRefreshBtn.classList.remove('hidden');
            manualRefreshBtn.classList.add('flex');
        }

        // Update progress bar
        function updateProgress() {
            const progress = Math.min((pollAttempts / maxPollAttempts) * 100, 95);
            progressBar.style.width = progress + '%';
        }

        // Update status message
        function updateMessage(message) {
            messageElement.textContent = message || "{{ t('verifying_payment_tap') ?? \"We're verifying your payment with Tap Payments...\" }}";
        }

        // Poll payment status
        function pollPaymentStatus() {
            pollAttempts++;
            updateProgress();

            fetch(pollUrl, {
                method: 'GET',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusElement.textContent = data.data.status || "{{ t('processing') ?? 'Processing...' }}";
                    
                    if (data.data.message) {
                        updateMessage(data.data.message);
                    }

                    // Check if we should redirect
                    if (data.data.should_redirect && data.data.redirect_url) {
                        progressBar.style.width = '100%';
                        clearInterval(pollInterval);
                        
                        // Small delay for visual feedback
                        setTimeout(() => {
                            window.location.href = data.data.redirect_url;
                        }, 1000);
                        return;
                    }

                    // Check if we should stop polling
                    if (!data.data.continue_polling) {
                        clearInterval(pollInterval);
                        showManualRefresh();
                        updateMessage("{{ t('payment_status_check_completed') ?? 'Payment status check completed. Please refresh if needed.' }}");
                    }
                } else {
                    console.error('Polling error:', data.message);
                    
                    // Continue polling on errors unless we've exceeded max attempts
                    if (pollAttempts >= maxPollAttempts) {
                        clearInterval(pollInterval);
                        showManualRefresh();
                        updateMessage("{{ t('unable_to_verify_payment') ?? 'Unable to verify payment status automatically. Please refresh the page.' }}");
                        statusElement.textContent = "{{ t('verification_error') ?? 'Verification Error' }}";
                    }
                }
            })
            .catch(error => {
                console.error('Network error:', error);
                
                // Continue polling on network errors unless we've exceeded max attempts
                if (pollAttempts >= maxPollAttempts) {
                    clearInterval(pollInterval);
                    showManualRefresh();
                    updateMessage("{{ t('network_error_refresh') ?? 'Network error. Please check your connection and refresh the page.' }}");
                    statusElement.textContent = "{{ t('connection_error') ?? 'Connection Error' }}";
                }
            });

            // Stop polling after max attempts
            if (pollAttempts >= maxPollAttempts) {
                clearInterval(pollInterval);
                showManualRefresh();
                updateMessage("{{ t('payment_verification_timeout') ?? 'Payment verification is taking longer than expected. Please refresh the page or contact support.' }}");
            }
        }

        // Start polling immediately, then every 5 seconds
        pollPaymentStatus();
        pollInterval = setInterval(pollPaymentStatus, 5000);

        // Clear interval when leaving page
        window.addEventListener('beforeunload', function() {
            if (pollInterval) {
                clearInterval(pollInterval);
            }
        });
    })();
    </script>
    @endpush
</x-app-layout>