<!-- Stripe -->
<a href="{{ route('admin.settings.payment.stripe') }}" class="group relative">
    <div
        class=" block p-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-info-500 dark:hover:border-info-500 transition-all duration-200 shadow-sm hover:shadow-md">
        <div class="flex items-center">
            <div class="shrink-0">
                <div class="relative">
                    <div
                        class="w-12 h-12 bg-purple-100 dark:bg-purple-900/50 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" viewBox="0 0 24 24" fill="currentColor">
                            <path
                                d="M13.976 9.15c-2.172-.806-3.356-1.426-3.356-2.409 0-.831.683-1.305 1.901-1.305 2.227 0 4.515.858 6.09 1.631l.89-5.494C18.252.975 15.697 0 12.165 0 9.667 0 7.589.654 6.104 1.872 4.56 3.147 3.757 4.992 3.757 7.218c0 4.039 2.467 5.76 6.476 7.219 2.585.92 3.445 1.574 3.445 2.583 0 .98-.84 1.545-2.354 1.545-1.875 0-4.965-.921-6.99-2.109l-.9 5.555C5.175 22.99 8.385 24 11.714 24c2.641 0 4.843-.624 6.328-1.813 1.664-1.305 2.525-3.236 2.525-5.732 0-4.128-2.524-5.851-6.591-7.305z" />
                        </svg>
                    </div> @php
                        $stripeSettings = get_batch_settings(['payment.stripe_enabled']);
                        $stripeSettings = array_merge(['payment.stripe_enabled' => false], $stripeSettings);
                    @endphp
                    <span
                        class="absolute -top-1 -right-1 h-3 w-3 rounded-full border-2 border-white dark:border-gray-800 {{ $stripeSettings['payment.stripe_enabled'] ? 'bg-success-400' : 'bg-gray-200 ' }}"></span>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ t('stripe') }}
                </h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ t('credit_card_international_payments') }}
                </p>
            </div>
        </div>
        <div class="mt-2 border-t border-gray-200 dark:border-gray-700 pt-2">
            @if ($settings['payment.stripe_enabled'])
                <span class="inline-flex items-center text-xs font-medium text-success-600 dark:text-success-400">
                    <span class="w-2 h-2 rounded-full bg-success-400 mr-2"></span>
                    {{ t('active') }}
                </span>
            @else
                <span class="inline-flex items-center text-xs font-medium text-gray-600 dark:text-gray-400">
                    <span class="w-2 h-2 rounded-full  bg-gray-300 dark:bg-gray-600 mr-2"></span>
                    {{ t('not_configured') }}
                </span>
            @endif
        </div>
    </div>
</a>
