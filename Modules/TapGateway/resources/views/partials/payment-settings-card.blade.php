<!-- Tap Payment -->
<a href="{{ route('admin.settings.payment.tap') }}" class="group relative">
    <div
        class="block p-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-info-500 dark:hover:border-info-500 transition-all duration-200 shadow-sm hover:shadow-md">
        <div class="flex items-center">
            <div class="shrink-0">
                <div class="relative">
                    <div
                        class="w-12 h-12 bg-orange-100 dark:bg-orange-900/50 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-orange-600 dark:text-orange-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z" />
                        </svg>
                    </div>
                    <span
                        class="absolute -top-1 -right-1 h-3 w-3 rounded-full border-2 border-white dark:border-gray-800 {{ $statusClass }}"></span>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ t('tap_payment') ?? 'Tap Payment' }}
                </h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ t('tap_payment_description') ?? 'MENA Region Cards, Knet, Apple Pay' }}
                </p>
            </div>
        </div>
        <div class="mt-2 border-t border-gray-200 dark:border-gray-700 pt-2">
            @if ($tapEnabled)
                <span
                    class="inline-flex items-center text-xs font-medium {{ $statusTextClass }}">
                    <span class="w-2 h-2 rounded-full bg-success-400 mr-2"></span>
                    {{ t('active') }}
                </span>
            @else
                <span
                    class="inline-flex items-center text-xs font-medium {{ $statusTextClass }}">
                    <span class="w-2 h-2 rounded-full bg-gray-300 dark:bg-gray-600 mr-2"></span>
                    {{ t('not_configured') }}
                </span>
            @endif
        </div>
    </div>
</a>