<x-app-layout>
    <x-slot:title>
        {{ t('tap_payment_settings') }}
    </x-slot:title>
    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
        <div>
            <div class="flex flex-col sm:flex-row gap-4 sm:gap-0 sm:items-center justify-between">
                <div>
                    <h1 class="font-display text-3xl text-slate-900 dark:text-slate-200 font-medium">
                        {{ t('tap_payment_settings') }}
                    </h1>
                    <p class="mt-2 text-base text-gray-600 dark:text-gray-400">
                        {{ t('configure_tap_payments_description') }}
                    </p>
                </div>
                <x-button.secondary type="button" onclick="history.back()">
                    <x-heroicon-o-arrow-left class="w-5 h-5 mr-2" />
                    {{ t('back') }}
                </x-button.secondary>
            </div>
        </div>

        <div class="bg-gray-50 dark:bg-gray-900 mt-6">
            <div class="max-w-7xl mx-auto">
                <!-- Main Content -->
                <form id="tap-settings-form" method="POST"
                    action="{{ route('admin.settings.payment.tap.update') }}" x-data="{
                        tapEnabled: {{ $settings->tap_enabled ? 'true' : 'false' }},
                        sandboxMode: {{ $settings->tap_sandbox_mode ? 'true' : 'false' }},
                        testConnection() {
                            const secretKey = document.getElementById('tap_secret_key').value;
                            const publicKey = document.getElementById('tap_public_key').value;
                            
                            if (!secretKey || !publicKey) {
                                window.dispatchEvent(new CustomEvent('notify', {
                                    detail: {
                                        type: 'error',
                                        message: '{{ t('tap_keys_required') }}'
                                    }
                                }));
                                return;
                            }
                            
                            fetch('{{ route('admin.settings.payment.tap.test') }}', {
                                method: 'POST',
                                headers: {
                                    'Content-Type': 'application/json',
                                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content
                                },
                                body: JSON.stringify({
                                    secret_key: secretKey,
                                    public_key: publicKey,
                                    sandbox_mode: this.sandboxMode
                                })
                            })
                            .then(response => response.json())
                            .then(data => {
                                window.dispatchEvent(new CustomEvent('notify', {
                                    detail: {
                                        type: data.success ? 'success' : 'danger',
                                        message: data.message
                                    }
                                }));
                            })
                            .catch(error => {
                                window.dispatchEvent(new CustomEvent('notify', {
                                    detail: {
                                        type: 'danger',
                                        message: '{{ t('connection_test_failed') }}'
                                    }
                                }));
                            });
                        }
                    }">
                    @csrf
                    @method('PUT')
                    <x-card>
                        <x-slot:content>
                            <div class="space-y-8">
                                <!-- Enable/Disable Section -->
                                <x-card>
                                    <x-slot:content>
                                        <div class="flex items-center justify-between">
                                            <div class="flex items-center">
                                                <x-checkbox id="tap_enabled" name="tap_enabled"
                                                    :checked="$settings->tap_enabled" x-model="tapEnabled"
                                                    class="h-5 w-5 rounded border-gray-300 text-primary-600 transition duration-150 ease-in-out dark:border-gray-600 dark:bg-gray-700" />
                                                <x-label for="tap_enabled" value="{{ t('enable_tap_payments') }}"
                                                    class="ml-3 font-medium text-gray-900 dark:text-white" />
                                            </div>
                                            <div class="text-sm text-gray-500 dark:text-gray-400">
                                                {{ t('enable_tap_payments_description') }}
                                            </div>
                                        </div>
                                    </x-slot:content>
                                </x-card>

                                <!-- Basic Settings Card -->
                                <x-card>
                                    <x-slot:header>
                                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                                            {{ t('tap_configuration') }}
                                        </h3>
                                        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                            {{ t('tap_configuration_description') }}
                                        </p>
                                    </x-slot:header>
                                    <x-slot:content>
                                        <!-- Tap Keys -->
                                        <div class="space-y-6">
                                            <!-- Public Key -->
                                            <div>
                                                <x-label for="tap_public_key" :value="t('public_key')"
                                                    class="text-base font-medium text-gray-900 dark:text-white" />
                                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                    {{ t('tap_public_key_description') }}
                                                </p>
                                                <x-input id="tap_public_key" name="tap_public_key" type="text"
                                                    x-bind:disabled="!tapEnabled"
                                                    class="mt-2 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                    :value="$settings->tap_public_key" />
                                                <x-input-error for="tap_public_key" class="mt-2" />
                                            </div>

                                            <!-- Secret Key -->
                                            <div>
                                                <x-label for="tap_secret_key" :value="t('secret_key')"
                                                    class="text-base font-medium text-gray-900 dark:text-white" />
                                                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                                    {{ t('tap_secret_key_description') }}
                                                </p>
                                                <x-input id="tap_secret_key" name="tap_secret_key" type="password"
                                                    x-bind:disabled="!tapEnabled"
                                                    class="mt-2 block w-full border-gray-300 dark:border-gray-600 dark:bg-gray-700 dark:text-white rounded-lg shadow-sm focus:border-primary-500 focus:ring-primary-500"
                                                    :value="$settings->tap_secret_key" />
                                                <x-input-error for="tap_secret_key" class="mt-2" />
                                            </div>

                                            <!-- Sandbox Mode -->
                                            <div>
                                                <div class="flex items-center">
                                                    <x-checkbox id="tap_sandbox_mode" name="tap_sandbox_mode"
                                                        :checked="$settings->tap_sandbox_mode" x-model="sandboxMode"
                                                        x-bind:disabled="!tapEnabled"
                                                        class="h-5 w-5 rounded border-gray-300 text-primary-600 transition duration-150 ease-in-out dark:border-gray-600 dark:bg-gray-700" />
                                                    <x-label for="tap_sandbox_mode" value="{{ t('enable_sandbox_mode') }}"
                                                        class="ml-3 text-base font-medium text-gray-900 dark:text-white" />
                                                </div>
                                                <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
                                                    {{ t('tap_sandbox_mode_description') }}
                                                </p>
                                            </div>

                                            <!-- Test Connection Button -->
                                            <div x-show="tapEnabled" class="pt-4">
                                                <x-button.secondary type="button" @click="testConnection()">
                                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                                                    </svg>
                                                    {{ t('test_connection') }}
                                                </x-button.secondary>
                                            </div>
                                        </div>
                                    </x-slot:content>
                                </x-card>
                            </div>
                        </x-slot:content>

                        <!-- Form Actions -->
                        @if(checkPermission('admin.payment_settings.edit'))
                        <x-slot:footer class="bg-gray-50 dark:bg-transparent px-6 py-3">
                            <div class="flex justify-end">
                                <x-button.primary type="submit">
                                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                            d="M5 13l4 4L19 7" />
                                    </svg>
                                    {{ t('save_settings') }}
                                </x-button.primary>
                            </div>
                        </x-slot:footer>
                        @endif
                    </x-card>
                </form>
            </div>
        </div>
    </div>
</x-app-layout>