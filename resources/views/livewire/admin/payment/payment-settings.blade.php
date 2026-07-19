<div>
    <x-slot:title>
        {{ t('payment_gateway_settings') }}
    </x-slot:title>


    <div class="space-y-6 max-w-6xl mx-auto">
        <x-breadcrumb :items="[
        ['label' => t('dashboard'), 'route' => route('admin.dashboard')],
        ['label' => t('payment_gateway_settings')],
    ]" />
        @php
        $settings = get_batch_settings(['payment.offline_enabled']);
        // Ensure all keys exist to prevent undefined array key errors
        $settings = array_merge([
        'payment.offline_enabled' => false,
        ], $settings);
        @endphp

        <x-card>
            <x-slot:header>
                <div class="flex items-center space-x-4">
                    <div class="flex-shrink-0">
                        <div class="w-10 h-10 bg-primary-100  rounded-full flex items-center justify-center">
                            <x-heroicon-m-bars-arrow-up class="w-5 h-5 text-primary-600" />
                        </div>
                    </div>
                    <div>
                        <h3 class="text-base sm:text-lg font-semibold text-gray-900 dark:text-white flex items-center">
                            {{ t('payment_gateway_settings') }}
                        </h3>
                        <p class="text-xs sm:text-sm text-gray-600 dark:text-gray-400 flex items-center">
                            {{ t('configure_and_manage_payment_gateway') }}
                        </p>
                    </div>
                </div>
            </x-slot:header>
            <x-slot:content>
                <!-- Payment Methods Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- Offline Payment -->
                    <a href="{{ route('admin.settings.payment.offline') }}" class="group relative">
                        <div
                            class="block p-6 bg-white dark:bg-gray-800 border border-gray-200 dark:border-gray-700 rounded-xl hover:border-info-500 dark:hover:border-info-500 transition-all duration-200 shadow-sm hover:shadow-md">
                            <div class="flex items-center">
                                <div class="shrink-0">
                                    <div class="relative">
                                        <div
                                            class="w-12 h-12 bg-info-100 dark:bg-info-900/50 rounded-lg flex items-center justify-center">
                                            <svg class="w-6 h-6 text-info-600 dark:text-info-400" fill="none"
                                                viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                                <path stroke-linecap="round" stroke-linejoin="round"
                                                    d="M2.25 18.75a60.07 60.07 0 0115.797 2.101c.727.198 1.453-.342 1.453-1.096V18.75M3.75 4.5v.75A.75.75 0 013 6h-.75m0 0v-.375c0-.621.504-1.125 1.125-1.125H20.25M2.25 6v9m18-10.5v.75c0 .414.336.75.75.75h.75m-1.5-1.5h.375c.621 0 1.125.504 1.125 1.125v9.75c0 .621-.504 1.125-1.125 1.125h-.375m1.5-1.5H21a.75.75 0 00-.75.75v.75m0 0H3.75m0 0h-.375a1.125 1.125 0 01-1.125-1.125V15m1.5 1.5v-.75A.75.75 0 003 15h-.75M15 10.5a3 3 0 11-6 0 3 3 0 016 0zm3 0h.008v.008H18V10.5zm-12 0h.008v.008H6V10.5z" />
                                            </svg>
                                        </div>

                                        <span
                                            class="absolute -top-1 -right-1 h-3 w-3 rounded-full  border-2 border-white dark:border-gray-800 {{ $settings['payment.offline_enabled'] ? 'bg-success-400' : 'bg-gray-200' }}"></span>
                                    </div>
                                </div>
                                <div class="ml-4 flex-1">
                                    <h3 class="text-lg font-semibold text-gray-900 dark:text-gray-100">
                                        {{ t('offline_payment') }}
                                    </h3>
                                    <p class="mt-1 text-sm text-gray-600 dark:text-gray-400">
                                        {{ t('accept_cash_bank_transfers') }}
                                    </p>
                                </div>
                            </div>
                            <div class="mt-2 border-t border-gray-200 dark:border-gray-700 pt-2">

                                @if ($settings['payment.offline_enabled'])
                                <span
                                    class="inline-flex items-center text-xs font-medium text-success-600 dark:text-success-400">
                                    <span class="w-2 h-2 rounded-full bg-success-400 mr-2"></span>
                                    {{ t('active') }}
                                </span>
                                @else
                                <span class="inline-flex items-center text-xs font-medium">
                                    <span class="w-2 h-2 rounded-full bg-gray-300 dark:bg-gray-600 mr-2"></span>
                                    {{ t('not_configured') }}
                                </span>
                                @endif
                            </div>
                        </div>
                    </a>

                    <!-- Additional Payment Gateways via Events -->
                    @php
                        $additionalGatewaysEvent = new \App\Events\PaymentSettingsViewRendering();
                        event($additionalGatewaysEvent);
                    @endphp

                    @foreach($additionalGatewaysEvent->paymentGateways as $gatewayHtml)
                        {!! $gatewayHtml !!}
                    @endforeach
                </div>
            </x-slot:content>
        </x-card>
    </div>
</div>
