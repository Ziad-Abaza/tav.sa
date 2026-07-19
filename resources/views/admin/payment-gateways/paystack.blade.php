<!-- Paystack -->
<a href="{{ route('admin.settings.payment.paystack') }}" class="group relative">
    <div
        class="block p-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-info-500 dark:hover:border-info-500 transition-all duration-200 shadow-sm hover:shadow-md">
        <div class="flex items-center">
            <div class="shrink-0">
                <div class="relative">
                    <div class="w-12 h-12 bg-green-100 dark:bg-green-900/50 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-2 15l-5-5 1.41-1.41L10 14.17l7.59-7.59L19 8l-9 9z" />
                        </svg>
                    </div>
                    <span
                        class="absolute -top-1 -right-1 h-3 w-3 rounded-full border-2 border-white dark:border-gray-800 {{ $settings['payment.paystack_enabled'] ? 'bg-success-400' : 'bg-gray-200' }}"></span>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ t('paystack') ?? 'Paystack' }}
                </h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ t('paystack_description') ?? 'Cards, Bank Transfer, Mobile Money' }}
                </p>
            </div>
        </div>
        <div class="mt-2 border-t border-gray-200 dark:border-gray-700 pt-2">
            @if ($settings['payment.paystack_enabled'])
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
