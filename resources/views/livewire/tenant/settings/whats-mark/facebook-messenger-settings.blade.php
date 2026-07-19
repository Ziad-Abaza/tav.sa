<div>
    <x-slot:title>
        {{ t('facebook_messenger') }}
    </x-slot:title>

    <!-- Page Heading -->
    <div class="pb-6">
        <x-settings-heading>{{ t('application_settings') }}</x-settings-heading>
    </div>

    <div class="flex flex-wrap lg:flex-nowrap gap-4">
        <!-- Sidebar Menu -->
        <div class="w-full lg:w-1/5">
            <x-tenant-whatsmark-settings-navigation wire:ignore />
        </div>

        <!-- Main Content -->
        <div class="flex-1 space-y-5">
            <form wire:submit="save" class="space-y-6">
                <!-- Webhook Configuration Card -->
                <x-card class="rounded-lg">
                    <x-slot:header>
                        <x-settings-heading>{{ t('webhook_configuration') }}</x-settings-heading>
                        <x-settings-description>{{ t('webhook_configuration_description') }}</x-settings-description>
                    </x-slot:header>

                    <x-slot:content class="space-y-5">

                        <!-- Toggle -->
                        <div>
                            <h3 class="text-base font-medium text-secondary-900 dark:text-white mb-1">
                                {{ t('enable_facebook_messenger') }}
                            </h3>
                            <x-toggle id="fb_messenger_enabled" name="fb_messenger_enabled" :value="$fb_messenger_enabled"
                                wire:model="fb_messenger_enabled" />
                        </div>

                        <div
                            class="bg-white dark:bg-secondary-800 rounded-lg border border-secondary-200 dark:border-secondary-700 p-5 mt-6 space-y-6">

                            <!-- Webhook URL -->
                            <div x-data="{
                                copied: false,
                                copyText() {
                                    const t = $refs.fb_messenger_webhook_url?.value;
                                    if (!t) { showNotification('No text found', 'danger'); return; }
                                    copyToClipboard(t);
                                    this.copied = true;
                                    setTimeout(() => this.copied = false, 2000);
                                }
                            }" class="space-y-1">
                                <x-label for="fb_messenger_webhook_url" :value="t('webhook_callback_url')" />
                                <div class="flex flex-col sm:flex-row gap-2">
                                    <x-input x-ref="fb_messenger_webhook_url" id="fb_messenger_webhook_url"
                                        type="text" value="{{ $fb_messenger_webhook_url }}" class="flex-1"
                                        readonly />
                                    <x-button.secondary x-on:click="copyText()" class="sm:w-auto">
                                        <span x-text="copied ? 'Copied' : 'Copy'"></span>
                                    </x-button.secondary>
                                </div>
                                <x-input-error for="fb_messenger_webhook_url" class="mt-1" />
                            </div>

                            <!-- Verify Token -->
                            <div x-data="{
                                copied: false,
                                copyText() {
                                    const t = $refs.fb_messenger_verify_token?.value;
                                    if (!t) { showNotification('No text found', 'danger'); return; }
                                    copyToClipboard(t);
                                    this.copied = true;
                                    setTimeout(() => this.copied = false, 2000);
                                }
                            }" class="space-y-1">
                                <x-label for="fb_messenger_verify_token" :value="t('verify_token')" />

                                <div class="flex flex-col sm:flex-row gap-2">
                                    <x-input x-ref="fb_messenger_verify_token" id="fb_messenger_verify_token"
                                        value="{{ $fb_messenger_verify_token }}" type="text" class="flex-1"
                                        readonly />

                                    <x-button.secondary wire:click="generateVerifyToken" type="button"
                                        class="sm:w-auto">
                                        {{ t('generate_new_token') }}
                                    </x-button.secondary>

                                    <x-button.secondary x-on:click="copyText()" class="sm:w-auto">
                                        <span x-text="copied ? 'Copied' : 'Copy'"></span>
                                    </x-button.secondary>
                                </div>

                                <x-input-error for="fb_messenger_verify_token" class="mt-1" />
                            </div>

                        </div>

                        <!-- Get Started Guide Card -->

                        <div
                            class="bg-white dark:bg-secondary-800 rounded-lg border border-secondary-200 dark:border-secondary-700 p-5 mt-6 space-y-4">

                            <h3 class="text-lg font-semibold text-secondary-900 dark:text-white">
                                {{ t('get_started_with_facebook_official_api') }}</h3>

                             <p class="text-sm text-secondary-500 dark:text-secondary-400 mb-6">
                                {{ t('follow_steps_to_configure_facebook_messenger') }}
                            </p> 

                            <div class="space-y-4 text-sm text-slate-700 dark:text-slate-300">
                                <div>
                                    <strong>{{ t('step') }} 1:</strong>
                                    <a href="https://developers.facebook.com/" target="_blank"
                                        class="text-info-500 hover:underline">
                                        {{ t('create_facebook_developer_account') }}
                                    </a>
                                </div>
                                <div>
                                    <strong>{{ t('step') }} 2:</strong>
                                    {{ t('create_app_navigate_messenger') }}
                                </div>
                                <div>
                                    <strong>{{ t('step') }} 3:</strong>
                                    {{ t('enter_webhook_callback_url_verify_token') }}
                                </div>
                                <div>
                                    <strong>{{ t('step') }} 4:</strong>
                                    {{ t('enable_message_handling_webhook_fields') }}
                                </div>
                            </div>
                        </div>

                        <!-- Messenger Access Token Card -->

                        <div
                            class="bg-white dark:bg-secondary-800 rounded-lg border border-secondary-200 dark:border-secondary-700 p-5 mt-6 space-y-4">

                            <h3 class="text-lg font-semibold text-secondary-900 dark:text-white">
                                {{ t('messenger_access_token') }}</h3>

                            <p class="text-sm text-secondary-500 dark:text-secondary-400 mb-6">
                                {{ t('messenger_access_token_description') }}
                            </p>

                            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                                <!-- Access Token -->
                                <div x-data="{ show: false }">
                                    <x-label for="fb_messenger_access_token" :value="t('access_token')" />
                                    <div class="relative mt-1">
                                        <x-input wire:model.defer="fb_messenger_access_token"
                                            id="fb_messenger_access_token" ::type="show ? 'text' : 'password'" class="pr-10" />
                                        <button type="button" @click="show = !show"
                                            class="absolute inset-y-0 right-0 pr-3 flex items-center">
                                            <x-heroicon-o-eye x-show="!show" class="h-5 w-5 text-slate-400" />
                                            <x-heroicon-o-eye-slash x-show="show" class="h-5 w-5 text-slate-400" />
                                        </button>
                                    </div>
                                    <x-input-error for="fb_messenger_access_token" class="mt-2" />
                                </div>

                                <!-- App ID -->
                                <div>
                                    <x-label for="fb_messenger_app_id" :value="t('app_id')" />
                                    <x-input wire:model.defer="fb_messenger_app_id" id="fb_messenger_app_id"
                                        type="text" class="mt-1" />
                                    <x-input-error for="fb_messenger_app_id" class="mt-2" />
                                </div>
                            </div>

                        </div>


                        <!-- Get Permanent Access Token Guide Card -->

                        <div
                            class="bg-white dark:bg-secondary-800 rounded-lg border border-secondary-200 dark:border-secondary-700 p-5 mt-6 space-y-4">

                            <h3 class="text-lg font-semibold text-secondary-900 dark:text-white">
                                {{ t('get_permanent_access_token') }}</h3>

                            <p class="text-sm text-secondary-500 dark:text-secondary-400 mb-6">
                                <a href="https://developers.facebook.com/docs/facebook-login/guides/access-tokens/get-long-lived"
                                    target="_blank" class="text-info-500 hover:underline">
                                    {{ t('view_facebook_docs') }}
                                </a>
                            </p>

                            <div class="space-y-2 text-sm text-slate-700 dark:text-slate-300">
                                <p>{{ t('follow_facebook_docs_to_generate_permanent_token') }}</p>
                                <p>{{ t('enter_token_above_after_obtaining') }}</p>
                            </div>
                        </div>

                        <!-- Page ID Card -->
                        <div
                            class="bg-white dark:bg-secondary-800 rounded-lg border border-secondary-200 dark:border-secondary-700 p-5 mt-6 space-y-4">

                            <h3 class="text-lg font-semibold text-secondary-900 dark:text-white">
                                {{ t('get_page_id') }}</h3>

                            <p class="text-sm text-secondary-500 dark:text-secondary-400 mb-6">
                                {{ t('page_id_description') }}
                            </p>

                            <div class="space-y-4">
                                <div class="space-y-2 text-sm text-slate-700 dark:text-slate-300">
                                    <div>
                                        <strong>{{ t('step') }} 1:</strong>
                                        {{ t('navigate_facebook_app_messenger_api_setup') }}
                                    </div>
                                    <div>
                                        <strong>{{ t('step') }} 2:</strong>
                                        {{ t('copy_page_id') }}
                                    </div>
                                </div>

                                <!-- Page ID Input -->
                                <div>
                                    <x-label for="fb_messenger_page_id" :value="t('page_id')" />
                                    <x-input wire:model.defer="fb_messenger_page_id" id="fb_messenger_page_id"
                                        type="text" class="mt-1" />
                                    <x-input-error for="fb_messenger_page_id" class="mt-2" />
                                </div>
                            </div>
                        </div>
                    </x-slot:content>

                    @if (checkPermission('tenant.facebook_messenger.settings.edit'))
                        <x-slot:footer class="bg-slate-50 dark:bg-transparent rounded-b-lg">
                            <div class="flex justify-end space-x-2">
                                <x-button.loading-button type="submit" target="save">
                                    {{ t('save_changes') }}
                                </x-button.loading-button>
                            </div>
                        </x-slot:footer>
                    @endif
                </x-card>

            </form>
        </div>
    </div>
</div>
