<!-- Razorpay -->
<a href="{{ route('admin.settings.payment.razorpay') }}" class="group relative">
    <div
        class="block p-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-info-500 dark:hover:border-info-500 transition-all duration-200 shadow-sm hover:shadow-md">
        <div class="flex items-center">
            <div class="shrink-0">
                <div class="relative">
                    <div class="w-12 h-12 bg-info-100 dark:bg-info-900/50 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-info-600 dark:text-info-400" fill="none" stroke="currentColor"
                            viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z" />
                        </svg>
                    </div>
                    <span
                        class="absolute -top-1 -right-1 h-3 w-3 rounded-full border-2 border-white dark:border-gray-800 {{ $settings['payment.razorpay_enabled'] ? 'bg-success-400' : 'bg-gray-200' }}"></span>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ t('razorpay') }}
                </h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ t('upi_cards_netbanking_wallets') }}
                </p>
            </div>
        </div>
        <div class="mt-2 border-t border-gray-200 dark:border-gray-700 pt-2">
            @if ($settings['payment.razorpay_enabled'])
                <span class="inline-flex items-center text-xs font-medium text-success-600 dark:text-success-400">
                    <span class="w-2 h-2 rounded-full bg-success-400 mr-2"></span>
                    {{ t('active') }}
                </span>
            @else
                <span class="inline-flex items-center text-xs font-medium text-gray-600 dark:text-gray-400">
                    <span class="w-2 h-2 rounded-full bg-gray-300 dark:bg-gray-600 mr-2"></span>
                    {{ t('not_configured') }}
                </span>
            @endif
        </div>
    </div>
</a>
