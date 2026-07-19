<!-- PayPal -->
<a href="{{ route('admin.settings.payment.paypal') }}" class="group relative">
    <div
        class="block p-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-info-500 dark:hover:border-info-500  transition-all duration-200 shadow-sm hover:shadow-md">
        <div class="flex items-center">
            <div class="shrink-0">
                <div class="relative">
                    <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900/50 rounded-lg flex items-center justify-center">
                        <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="currentColor" viewBox="0 0 24 24">
                            <path
                                d="M7.076 21.337H2.47a.641.641 0 0 1-.633-.74L4.944.901C5.026.382 5.473 0 5.998 0h7.46c2.57 0 4.578.543 5.69 1.81 1.01 1.15 1.304 2.42 1.012 4.287-.023.143-.047.288-.077.437-.983 5.05-4.349 6.797-8.647 6.797h-2.19c-.524 0-.968.382-1.05.9l-1.12 7.106zm14.146-14.42a3.35 3.35 0 0 0-.607-.541c-.013.076-.026.175-.041.254-.58 2.975-2.477 4.562-5.95 4.562H11.4c-.146 0-.27.097-.292.238l-.891 5.641-.03.152h2.01a.469.469 0 0 0 .464-.397l.019-.08.383-2.43.025-.086a.469.469 0 0 1 .464-.398h.292c1.886 0 3.36-.378 3.902-1.78.223-.535.292-1.064.299-1.469a3.385 3.385 0 0 0-.823-2.996 2.53 2.53 0 0 0-.673-.67z" />
                            <path
                                d="M20.713 6.72c-.015-.044-.03-.088-.046-.132-1.444-3.344-4.414-4.358-8.844-4.358h-5.8c-.174 0-.34.093-.394.333L3.467 18.21a.237.237 0 0 0 .234.293h3.29l.855-5.424-.027.176c.054-.24.22-.333.394-.333h2.19c3.94 0 7.013-1.6 7.908-6.227.026-.134.05-.265.07-.393.225-1.46.01-2.477-.668-3.582z" />
                        </svg>
                    </div>
                    <span
                        class="absolute -top-1 -right-1 h-3 w-3 rounded-full border-2 border-white dark:border-gray-800 {{ $settings['payment.paypal_enabled'] ? 'bg-success-400' : 'bg-gray-200' }}"></span>
                </div>
            </div>
            <div class="ml-4 flex-1">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                    {{ t('paypal') }}
                </h3>
                <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                    {{ t('global_payment_solution_paypal_credit') }}
                </p>
            </div>
        </div>
        <div class="mt-2 border-t border-gray-200 dark:border-gray-700 pt-2">
            @if ($settings['payment.paypal_enabled'])
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
