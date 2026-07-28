<div>
    <x-slot:title>
        {{ t('tenant_details') }} - {{ $tenant->company_name }}
    </x-slot:title>
    <x-card class="mb-6">
        <x-slot:header class="flex justify-between items-start flex-col sm:flex-row gap-4">
            <div class="flex items-center">
                <div class="flex-shrink-0 h-11 w-11 bg-primary-100 rounded-full flex items-center justify-center">
                    <span class="text-primary-600 font-semibold">{{ substr($tenant->company_name, 0, 1) }}</span>
                </div>
                <div class="ml-3">
                    <h1 class="font-semibold text-primary-600">{{ $tenant->company_name }}</h1>
                    <div class="flex items-center">
                        <span
                            class="text-primary-600 break-all">{{ config('app.url') . '/' . $tenant->subdomain }}</span>
                        <a href="{{ $tenant->url }}" target="_blank" class="ml-2 text-primary-600">
                            <x-heroicon-o-arrow-top-right-on-square class="w-4 h-4" />
                        </a>
                    </div>
                </div>
            </div>
            <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4">
                <!-- Status Badge -->
                <div class="flex items-center">
                    @switch($tenant->status)
                        @case('active')
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-300">
                                <x-heroicon-s-check-circle class="w-4 h-4 mr-1" />
                                {{ t('active') }}
                            </span>
                        @break

                        @case('deactive')
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-300">
                                <x-heroicon-s-pause-circle class="w-4 h-4 mr-1" />
                                {{ t('deactive') }}
                            </span>
                        @break

                        @case('suspended')
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-300">
                                <x-heroicon-s-x-circle class="w-4 h-4 mr-1" />
                                {{ t('suspended') }}
                            </span>
                        @break

                        @default
                            <span
                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                {{ ucfirst($tenant->status) }}
                            </span>
                    @endswitch
                </div>

                <!-- Action Buttons -->
                <div class="flex flex-row gap-2">
                    @if (checkPermission('admin.tenants.edit'))
                        <x-button.secondary href="{{ route('admin.tenants.save', ['tenantId' => $tenant->id]) }}">
                            <x-heroicon-o-pencil-square class="w-4 h-4 mr-1" />
                            {{ t('edit') }}
                        </x-button.secondary>
                    @endif

                    @if (checkPermission('admin.tenants.edit'))
                        @if ($tenant->status === 'active')
                            <x-button.danger wire:click="confirmStatusChange('deactive')">
                                <x-heroicon-o-pause-circle class="w-4 h-4 mr-1" />
                                {{ t('deactivate') }}
                            </x-button.danger>
                        @else
                            <x-button.primary wire:click="confirmStatusChange('active')">
                                <x-heroicon-o-check-circle class="w-4 h-4 mr-1" />
                                {{ t('activate') }}
                            </x-button.primary>
                        @endif
                    @endif
                </div>
            </div>
        </x-slot:header>
        <x-slot:content>
            <!-- Quick Stats -->
            <div class="grid grid-cols-2 md:grid-cols-3 lg:grid-cols-6 gap-4">


                <!-- Total Users -->
                <div
                    class="bg-gradient-to-br from-purple-50 to-purple-100 dark:from-purple-900/20 dark:to-purple-800/20 rounded-lg p-4 border border-purple-200 dark:border-purple-700/50">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-purple-600 dark:text-purple-400">
                                {{ $users->total() }}
                            </div>
                            <div class="text-sm text-purple-600/70 dark:text-purple-400/70 font-medium">
                                {{ t('total_users') }}</div>
                        </div>
                        <div class="p-2 bg-purple-100 dark:bg-purple-800/50 rounded-lg">
                            <x-heroicon-s-users class="h-5 w-5 text-purple-600 dark:text-purple-400" />
                        </div>
                    </div>
                </div>

                <!-- Total Tickets -->
                <div
                    class="bg-gradient-to-br from-orange-50 to-orange-100 dark:from-orange-900/20 dark:to-orange-800/20 rounded-lg p-4 border border-orange-200 dark:border-orange-700/50">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-2xl font-bold text-orange-600 dark:text-orange-400">
                                {{ $subscriptionSummary['total_tickets'] }}
                            </div>
                            <div class="text-sm text-orange-600/70 dark:text-orange-400/70 font-medium">
                                {{ t('total_tickets') }}</div>
                        </div>
                        <div class="p-2 bg-orange-100 dark:bg-orange-800/50 rounded-lg">
                            <x-heroicon-s-ticket class="h-5 w-5 text-orange-600 dark:text-orange-400" />
                        </div>
                    </div>
                </div>
            </div>
        </x-slot:content>
    </x-card>
    <div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
        <x-card class="mb-6 w-full self-start">
            <x-slot:header>
                <h3 class=" font-medium text-gray-900 dark:text-white">{{ t('tenant_information') }}</h3>
            </x-slot:header>
            <x-slot:content>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <dl class="space-y-4">
                            <div>
                                <dt class="font-medium text-gray-500 dark:text-gray-400">
                                    {{ t('company_name') }}</dt>
                                <dd class="mt-1 text-gray-900 dark:text-white">
                                    {{ $tenant->company_name ?: 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-500 dark:text-gray-400">
                                    {{ t('subdomain') }}</dt>
                                <dd class="mt-1 text-gray-900 dark:text-white">
                                    <span class=" bg-gray-100 dark:bg-gray-700 px-2 py-1 rounded text-xs">
                                        {{ $tenant->subdomain }}
                                    </span>
                                </dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-500 dark:text-gray-400">
                                    {{ t('address') }}</dt>
                                <dd class="mt-1 text-gray-900 dark:text-white break-words">
                                    {{ $tenant->address ?: 'N/A' }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-500 dark:text-gray-400">
                                    {{ t('country') }}</dt>
                                <dd class="mt-1 text-gray-900 dark:text-white">
                                    {{ get_country_name($tenant->country_id) }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-500 dark:text-gray-400">
                                    {{ t('phone') }}</dt>
                                <dd class="mt-1 text-gray-900 dark:text-white">
                                    {{ $tenant->adminUser?->phone ?: 'N/A' }}</dd>
                            </div>
                        </dl>
                    </div>
                    <div>
                        <dl class="space-y-4">
                            <div>
                                <dt class="font-medium text-gray-500 dark:text-gray-400">
                                    {{ t('status') }}</dt>
                                <dd class="mt-1">
                                    @switch($tenant->status)
                                        @case('active')
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-success-100 text-success-800 dark:bg-success-900 dark:text-success-300">
                                                {{ t('active') }}
                                            </span>
                                        @break

                                        @case('deactive')
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-300">
                                                {{ t('deactive') }}
                                            </span>
                                        @break

                                        @case('suspended')
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-danger-100 text-danger-800 dark:bg-danger-900 dark:text-danger-300">
                                                {{ t('suspended') }}
                                            </span>
                                        @break

                                        @default
                                            <span
                                                class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-800 dark:bg-gray-700 dark:text-gray-300">
                                                {{ ucfirst($tenant->status) }}
                                            </span>
                                    @endswitch
                                </dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-500 dark:text-gray-400">
                                    {{ t('created_at') }}</dt>
                                <dd class="mt-1 text-gray-900 dark:text-white">
                                    {{ format_date_time($tenant->created_at) }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-500 dark:text-gray-400">
                                    {{ t('timezone') }}</dt>
                                <dd class="mt-1 text-gray-900 dark:text-white">
                                    {{ $tenant->timezone ?: config('app.timezone') }}</dd>
                            </div>
                            <div>
                                <dt class="font-medium text-gray-500 dark:text-gray-400">
                                    {{ t('email') }}</dt>
                                <dd class="mt-1 text-gray-900 dark:text-white break-words">
                                    {{ $tenant->adminUser?->email ?: 'N/A' }}</dd>
                            </div>
                            @if ($tenant->expires_at)
                                <div>
                                    <dt class="font-medium text-gray-500 dark:text-gray-400">
                                        {{ t('expires_at') }}</dt>
                                    <dd class="mt-1">
                                        <span
                                            class="{{ $tenant->isExpired() ? 'text-danger-600 dark:text-danger-400' : 'text-gray-900 dark:text-white' }}">
                                            {{ format_date_time($tenant->expires_at) }}
                                            @if ($tenant->isExpired())
                                                <span class="text-xs">({{ t('expired') }})</span>
                                            @endif
                                        </span>
                                    </dd>
                                </div>
                            @endif
                        </dl>
                    </div>
                </div>
            </x-slot:content>
        </x-card>

        {{ do_action('tenant.billing_information', $tenant) }}

       
    </div>

    <!-- Status Change Confirmation Modal -->
    <x-modal.confirm-box wire:model.live="confirmingStatusChange" :maxWidth="'lg'">
        <x-slot:title>
            {{ t('change_tenant_status') }}
        </x-slot:title>

        <x-slot:content>
            @if ($newStatus === 'active')
                <p class="text-gray-700 dark:text-gray-300">{{ t('confirm_activate_tenant') }}</p>
                <div
                    class="mt-3 p-3 bg-success-50 dark:bg-success-900/20 rounded text-success-800 dark:text-success-300">
                    {{ t('activate_tenant_description') }}
                </div>
            @elseif($newStatus === 'deactive')
                <p class="text-gray-700 dark:text-gray-300">{{ t('confirm_deactivate_tenant') }}</p>
                <div
                    class="mt-3 p-3 bg-warning-50 dark:bg-warning-900/20 rounded text-warning-800 dark:text-warning-300">
                    {{ t('deactivate_tenant_description') }}
                </div>
            @elseif($newStatus === 'suspended')
                <p class="text-gray-700 dark:text-gray-300">{{ t('confirm_suspend_tenant') }}</p>
                <div class="mt-3 p-3 bg-danger-50 dark:bg-danger-900/20 rounded text-danger-800 dark:text-danger-300">
                    <strong>{{ t('warning') }}:</strong> {{ t('suspend_tenant_description') }}
                </div>
            @endif
        </x-slot:content>

        <x-slot:footer>
            <x-button.cancel-button wire:click="$set('confirmingStatusChange', false)" wire:loading.attr="disabled">
                {{ t('cancel') }}
            </x-button.cancel-button>

            <button type="button" wire:click="updateStatus" wire:loading.attr="disabled"
                class="ml-3 inline-flex justify-center py-2 px-4 border border-transparent shadow-sm font-medium rounded-md
                {{ $newStatus === 'active'
                    ? 'text-white bg-success-600 hover:bg-success-700'
                    : ($newStatus === 'deactive'
                        ? 'text-white bg-warning-600 hover:bg-warning-700'
                        : 'text-white bg-danger-600 hover:bg-danger-700') }}">
                {{ t('confirm') }}
            </button>
        </x-slot:footer>
    </x-modal.confirm-box>
</div>
