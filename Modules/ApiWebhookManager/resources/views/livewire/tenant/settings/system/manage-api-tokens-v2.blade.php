<div class="mx-auto px-4 md:px-0">
    <x-slot:title>
        {{ t('api_v2_token_management') }}
    </x-slot:title>

    <!-- Page Heading -->
    <div class="pb-6">
        <x-settings-heading>{{ t('system_setting') }}</x-settings-heading>
    </div>

    <div class="flex flex-wrap lg:flex-nowrap gap-4">
        <!-- Sidebar Menu -->
        <div class="w-full lg:w-1/5">
            <x-tenant-system-settings-navigation wire:ignore />
        </div>

        <!-- Main Content -->
        <div class="flex-1 space-y-5">
            <!-- Header with Create Button -->
            <x-card class="rounded-lg">
                <x-slot:header>
                    <div class="flex justify-between items-center">
                        <div>
                            <x-settings-heading>
                                {{ t('api_v2_token_management') }}
                            </x-settings-heading>
                            <x-settings-description>
                                {{ t('api_v2_token_management_description') }}
                            </x-settings-description>
                        </div>
                        <div class="flex gap-3">
                            <a href="{{ url('/api-docs') }}" target="_blank">
                                <x-button.green type="button">
                                    <svg class="w-5 h-5 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/>
                                    </svg>
                                    {{ t('api_docs') }}
                                </x-button.green>
                            </a>
                            @if (checkPermission('system_settings.edit'))
                                <x-button.primary wire:click="openCreateModal" type="button">
                                    <x-heroicon-o-plus class="w-5 h-5 mr-1" />
                                    {{ t('create_token') }}
                                </x-button.primary>
                            @endif
                        </div>
                    </div>
                </x-slot:header>

                <x-slot:content>
                    <!-- Tokens Table -->
                    @if ($tokens->count() > 0)
                        <div class="overflow-x-auto">
                            <table class="min-w-full divide-y divide-secondary-200 dark:divide-secondary-700">
                                <thead class="bg-secondary-50 dark:bg-secondary-800">
                                    <tr>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                                            {{ t('token_name_label') }}
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                                            {{ t('scopes_label') }}
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                                            {{ t('status_label') }}
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                                            {{ t('last_used_label') }}
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-left text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                                            {{ t('expires_label') }}
                                        </th>
                                        <th scope="col"
                                            class="px-6 py-3 text-right text-xs font-medium text-secondary-500 dark:text-secondary-400 uppercase tracking-wider">
                                            {{ t('actions_label') }}
                                        </th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-secondary-900 divide-y divide-secondary-200 dark:divide-secondary-700">
                                    @foreach ($tokens as $token)
                                        <tr wire:key="token-{{ $token->id }}">
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                <div class="text-sm font-medium text-secondary-900 dark:text-white">
                                                    {{ $token->name }}
                                                </div>
                                                <div class="text-xs text-secondary-500 dark:text-secondary-400">
                                                    {{ t('token_ends_with') }} ...{{ substr($token->token, -8) }}
                                                </div>
                                            </td>
                                            <td class="px-6 py-4">
                                                <div class="flex flex-wrap gap-1">
                                                    @foreach (array_slice($token->scopes ?? [], 0, 3) as $scope)
                                                        <span
                                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-primary-100 text-primary-800 dark:bg-primary-900 dark:text-primary-200">
                                                            {{ $scope }}
                                                        </span>
                                                    @endforeach
                                                    @if (count($token->scopes ?? []) > 3)
                                                        <span
                                                            class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium bg-secondary-100 text-secondary-800 dark:bg-secondary-700 dark:text-secondary-200">
                                                            +{{ count($token->scopes) - 3 }} {{ t('more_scopes') }}
                                                        </span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap">
                                                @if ($token->is_active && !$token->isExpired())
                                                    <span
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-200">
                                                        {{ t('active_status') }}
                                                    </span>
                                                @elseif ($token->isExpired())
                                                    <span
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200">
                                                        {{ t('expired_status') }}
                                                    </span>
                                                @else
                                                    <span
                                                        class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full bg-secondary-100 text-secondary-800 dark:bg-secondary-700 dark:text-secondary-200">
                                                        {{ t('inactive_status') }}
                                                    </span>
                                                @endif
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-secondary-500 dark:text-secondary-400">
                                                {{ $token->last_used_at ? $token->last_used_at->diffForHumans() : t('never') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-sm text-secondary-500 dark:text-secondary-400">
                                                {{ $token->expires_at ? $token->expires_at->format('M d, Y') : t('never') }}
                                            </td>
                                            <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                                <div class="flex justify-end items-center space-x-2">
                                                    @if (checkPermission('system_settings.edit'))
                                                        <button wire:click="editToken({{ $token->id }})" type="button"
                                                            class="text-primary-600 hover:text-primary-900 dark:text-primary-400 dark:hover:text-primary-300"
                                                            title="{{ t('edit_title') }}">
                                                            <x-heroicon-o-pencil class="w-5 h-5" />
                                                        </button>

                                                        @if ($token->is_active)
                                                            <button wire:click="rotateToken({{ $token->id }})" type="button"
                                                                class="text-warning-600 hover:text-warning-900 dark:text-warning-400 dark:hover:text-warning-300"
                                                                title="{{ t('rotate_token_title') }}">
                                                                <x-heroicon-o-arrow-path class="w-5 h-5" />
                                                            </button>
                                                        @endif

                                                        <button wire:click="toggleTokenStatus({{ $token->id }})" type="button"
                                                            class="{{ $token->is_active ? 'text-secondary-600 hover:text-secondary-900 dark:text-secondary-400 dark:hover:text-secondary-300' : 'text-success-600 hover:text-success-900 dark:text-success-400 dark:hover:text-success-300' }}"
                                                            title="{{ $token->is_active ? t('revoke_title') : t('activate_title') }}">
                                                            @if ($token->is_active)
                                                                <x-heroicon-o-no-symbol class="w-5 h-5" />
                                                            @else
                                                                <x-heroicon-o-check-circle class="w-5 h-5" />
                                                            @endif
                                                        </button>

                                                        <button wire:click="deleteToken({{ $token->id }})"
                                                            wire:confirm="{{ t('delete_token_confirmation') }}"
                                                            type="button"
                                                            class="text-danger-600 hover:text-danger-900 dark:text-danger-400 dark:hover:text-danger-300"
                                                            title="{{ t('delete_title') }}">
                                                            <x-heroicon-o-trash class="w-5 h-5" />
                                                        </button>
                                                    @endif
                                                </div>
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>

                        <!-- Pagination -->
                        <div class="mt-4">
                            {{ $tokens->links() }}
                        </div>
                    @else
                        <div class="text-center py-12">
                            <x-heroicon-o-key class="mx-auto h-12 w-12 text-secondary-400" />
                            <h3 class="mt-2 text-sm font-medium text-secondary-900 dark:text-white">{{ t('no_api_tokens') }}</h3>
                            <p class="mt-1 text-sm text-secondary-500 dark:text-secondary-400">{{ t('get_started_message') }}</p>
                            @if (checkPermission('system_settings.edit'))
                                <div class="mt-6">
                                    <x-button.primary wire:click="openCreateModal" type="button">
                                        <x-heroicon-o-plus class="w-5 h-5 mr-1" />
                                        {{ t('create_token') }}
                                    </x-button.primary>
                                </div>
                            @endif
                        </div>
                    @endif
                </x-slot:content>
            </x-card>

            <!-- API Documentation -->
            <x-card class="rounded-lg">
                <x-slot:header>
                    <x-settings-heading>
                        {{ t('api_v2_endpoint_info') }}
                    </x-settings-heading>
                </x-slot:header>

                <x-slot:content>
                    <div class="space-y-4">
                        <!-- Base URL -->
                        <div>
                            <label class="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                                {{ t('api_base_url_label') }}
                            </label>
                            <div class="flex gap-2" x-data="{
                                copied: false,
                                copyText() {
                                    const text = $refs.apiUrl?.value;
                                    if (!text) return;
                                    copyToClipboard(text);
                                    this.copied = true;
                                    setTimeout(() => this.copied = false, 2000);
                                }
                            }">
                                <x-input type="text" class="flex-1" value="{{ config('app.url') . '/api/v2/' }}" readonly x-ref="apiUrl" />
                                <x-button.secondary x-on:click="copyText()" type="button">
                                    <span x-text="copied ? '{{ t('copied_button') }}' : '{{ t('copy_button') }}'">{{ t('copy_button') }}</span>
                                </x-button.secondary>
                            </div>
                        </div>

                        <!-- Authentication -->
                        <div>
                            <label class="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                                {{ t('authentication_header_label') }}
                            </label>
                            <div class="flex gap-2" x-data="{
                                copied: false,
                                copyText() {
                                    const text = $refs.authHeader?.value;
                                    if (!text) return;
                                    copyToClipboard(text);
                                    this.copied = true;
                                    setTimeout(() => this.copied = false, 2000);
                                }
                            }">
                                <x-input type="text" class="flex-1" value="Authorization: Bearer YOUR_TOKEN_HERE" readonly x-ref="authHeader" />
                                <x-button.secondary x-on:click="copyText()" type="button">
                                    <span x-text="copied ? '{{ t('copied_button') }}' : '{{ t('copy_button') }}'">{{ t('copy_button') }}</span>
                                </x-button.secondary>
                            </div>
                        </div>

                        <!-- Example Endpoint -->
                        <div>
                            <label class="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                                {{ t('example_list_contacts') }}
                            </label>
                            <code class="block bg-secondary-100 dark:bg-secondary-800 p-3 rounded text-sm">
                                GET {{ config('app.url') }}/api/v2/contacts<br>
                                Authorization: Bearer YOUR_TOKEN_HERE
                            </code>
                        </div>
                    </div>
                </x-slot:content>
            </x-card>
        </div>
    </div>

    <!-- Create/Edit Token Modal -->
    <x-modal.custom-modal wire:model="showCreateModal" max-width="3xl">
        <div class="p-6">
            <h3 class="text-lg font-medium text-secondary-900 dark:text-white mb-4">
                {{ $editMode ? t('edit_api_token') : t('create_new_api_token') }}
            </h3>

            @if ($plainToken)
                <!-- Show Token After Creation -->
                <div class="mb-6 p-4 bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800 rounded-lg">
                    <div class="flex">
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-warning-400 mr-2" />
                        <div class="flex-1">
                            <h4 class="text-sm font-medium text-warning-800 dark:text-warning-200">{{ t('copy_token_now') }}</h4>
                            <p class="mt-1 text-sm text-warning-700 dark:text-warning-300">
                                {{ t('token_security_warning') }}
                            </p>
                            <div class="mt-3 flex gap-2" x-data="{
                                copied: false,
                                copyToken() {
                                    const text = $refs.plainToken?.value;
                                    if (!text) return;
                                    copyToClipboard(text);
                                    this.copied = true;
                                    setTimeout(() => this.copied = false, 2000);
                                }
                            }">
                                <x-input type="text" class="flex-1 font-mono text-sm" value="{{ $plainToken }}" readonly x-ref="plainToken" />
                                <x-button.primary x-on:click="copyToken()" type="button">
                                    <span x-text="copied ? '{{ t('copied_token_button') }}' : '{{ t('copy_token_button') }}'">{{ t('copy_token_button') }}</span>
                                </x-button.primary>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end">
                    <x-button.primary wire:click="finishCreate" type="button">
                        {{ t('done_button') }}
                    </x-button.primary>
                </div>
            @else
                <form wire:submit="{{ $editMode ? 'updateToken' : 'createToken' }}">
                    <div class="space-y-4">
                        <!-- Token Name -->
                        <div>
                            <label for="name" class="block text-sm font-medium text-secondary-700 dark:text-secondary-300">
                                {{ t('token_name_required') }} <span class="text-danger-500">*</span>
                            </label>
                            <x-input wire:model="name" id="name" type="text" class="mt-1 w-full"
                                placeholder="{{ t('token_name_placeholder') }}" />
                            @error('name')
                                <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Scopes -->
                        <div x-data="{
                            selectedScopes: @entangle('selectedScopes'),
                            hasFullAccess() {
                                return Array.isArray(this.selectedScopes) && this.selectedScopes.includes('*');
                            }
                        }" x-init="$watch('selectedScopes', (value) => {
                            // If Full Access is selected, disable other checkboxes
                            if (Array.isArray(value) && value.includes('*') && value.length > 1) {
                                // Only keep the '*' scope
                                this.selectedScopes = ['*'];
                            }
                        })">
                            <label class="block text-sm font-medium text-secondary-700 dark:text-secondary-300 mb-2">
                                {{ t('permissions_scopes_label') }} <span class="text-danger-500">*</span>
                            </label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-96 overflow-y-auto p-4 border border-secondary-200 dark:border-secondary-700 rounded-lg">
                                @foreach ($groupedScopes as $resource => $scopes)
                                    <div class="space-y-2">
                                        <h4 class="font-medium text-sm text-secondary-900 dark:text-white capitalize">
                                            {{ $resource }}
                                        </h4>
                                        @foreach ($scopes as $scope)
                                            <label class="flex items-start space-x-2 cursor-pointer" :class="{ 'opacity-50': hasFullAccess() && '{{ $scope->value }}' !== '*' }">
                                                <input type="checkbox" wire:model.live="selectedScopes" value="{{ $scope->value }}"
                                                    :disabled="hasFullAccess() && '{{ $scope->value }}' !== '*'"
                                                    class="mt-0.5 rounded border-secondary-300 text-primary-600 focus:ring-primary-500 dark:border-secondary-600 dark:bg-secondary-700 disabled:opacity-50 disabled:cursor-not-allowed">
                                                <div class="flex-1">
                                                    <div class="text-sm text-secondary-900 dark:text-white">
                                                        {{ $scope->label() }}
                                                    </div>
                                                    <div class="text-xs text-secondary-500 dark:text-secondary-400">
                                                        {{ $scope->description() }}
                                                    </div>
                                                </div>
                                            </label>
                                        @endforeach
                                    </div>
                                @endforeach
                            </div>
                            @error('selectedScopes')
                                <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Rate Limit -->
                        <div>
                            <label for="rateLimitPerMinute" class="block text-sm font-medium text-secondary-700 dark:text-secondary-300">
                                {{ t('rate_limit_label') }} <span class="text-danger-500">*</span>
                            </label>
                            <x-input wire:model="rateLimitPerMinute" id="rateLimitPerMinute" type="number" min="1" max="10000"
                                class="mt-1 w-full" />
                            @error('rateLimitPerMinute')
                                <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>

                        <!-- Expires In Days -->
                        @if (!$editMode)
                            <div>
                                <label for="expiresInDays" class="block text-sm font-medium text-secondary-700 dark:text-secondary-300">
                                    {{ t('expires_in_days_label') }}
                                </label>
                                <x-input wire:model="expiresInDays" id="expiresInDays" type="number" min="1" max="3650"
                                    class="mt-1 w-full" placeholder="{{ t('no_expiration_placeholder') }}" />
                                @error('expiresInDays')
                                    <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                @enderror
                            </div>
                        @endif

                        <!-- IP Whitelist -->
                        <div>
                            <label for="ipWhitelistInput" class="block text-sm font-medium text-secondary-700 dark:text-secondary-300">
                                {{ t('ip_whitelist_label') }}
                            </label>
                            <x-input wire:model="ipWhitelistInput" id="ipWhitelistInput" type="text" class="mt-1 w-full"
                                placeholder="{{ t('ip_whitelist_placeholder') }}" />
                            <p class="mt-1 text-xs text-secondary-500 dark:text-secondary-400">
                                {{ t('ip_whitelist_help') }}
                            </p>
                            @error('ipWhitelistInput')
                                <p class="mt-1 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                            @enderror
                        </div>
                    </div>

                    <div class="mt-6 flex justify-end space-x-3">
                        <x-button.secondary wire:click="closeCreateModal" type="button">
                            {{ t('cancel_button') }}
                        </x-button.secondary>
                        <x-button.loading-button type="submit" target="{{ $editMode ? 'updateToken' : 'createToken' }}">
                            {{ $editMode ? t('update_token_button') : t('create_token_button') }}
                        </x-button.loading-button>
                    </div>
                </form>
            @endif
        </div>
    </x-modal.custom-modal>

    <!-- View Token Modal (for rotation) -->
    <x-modal.custom-modal wire:model="showViewModal" max-width="2xl">
        <div class="p-6">
            <h3 class="text-lg font-medium text-secondary-900 dark:text-white mb-4">
                {{ t('token_rotated_successfully') }}
            </h3>

            @if ($plainToken)
                <div class="mb-6 p-4 bg-warning-50 dark:bg-warning-900/20 border border-warning-200 dark:border-warning-800 rounded-lg">
                    <div class="flex">
                        <x-heroicon-o-exclamation-triangle class="h-5 w-5 text-warning-400 mr-2" />
                        <div class="flex-1">
                            <h4 class="text-sm font-medium text-warning-800 dark:text-warning-200">{{ t('copy_new_token_now') }}</h4>
                            <p class="mt-1 text-sm text-warning-700 dark:text-warning-300">
                                {{ t('old_token_revoked_message') }}
                            </p>
                            <div class="mt-3 flex gap-2" x-data="{
                                copied: false,
                                copyToken() {
                                    const text = $refs.plainToken2?.value;
                                    if (!text) return;
                                    copyToClipboard(text);
                                    this.copied = true;
                                    setTimeout(() => this.copied = false, 2000);
                                }
                            }">
                                <x-input type="text" class="flex-1 font-mono text-sm" value="{{ $plainToken }}" readonly x-ref="plainToken2" />
                                <x-button.primary x-on:click="copyToken()" type="button">
                                    <span x-text="copied ? '{{ t('copied_token_button') }}' : '{{ t('copy_token_button') }}'">{{ t('copy_token_button') }}</span>
                                </x-button.primary>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div class="flex justify-end">
                <x-button.primary wire:click="closeViewModal" type="button">
                    {{ t('done_button') }}
                </x-button.primary>
            </div>
        </div>
    </x-modal.custom-modal>
</div>
