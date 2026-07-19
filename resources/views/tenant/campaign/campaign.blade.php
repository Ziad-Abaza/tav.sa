<x-app-layout>
    <div>
          @if (empty(get_tenant_setting_from_db('whatsapp', 'is_whatsmark_connected')) ||
                empty(get_tenant_setting_from_db('whatsapp', 'wm_default_phone_number')))
            <x-disconnected-account />
          @else
        
            <div class="mb-4">
                <x-breadcrumb :items="[
                    ['label' => t('dashboard'), 'route' => tenant_route('tenant.dashboard')],
                    ['label' => t('campaigns'), 'route' => tenant_route('tenant.campaigns.list')],
                    ['label' => isset($campaignId) ? t('edit_campaign') : t('create_campaign')],
                ]" />
                <div class="flex flex-col xl:flex-row justify-between items-start gap-4">
                    <x-settings-heading class="font-display">
                        {{ isset($campaignId) ? t('edit_campaign') : t('create_campaign') }}
                    </x-settings-heading>
                </div>
            </div>
       
            @if (isset($hasReachedLimit) && $hasReachedLimit && !isset($campaignId))
                <div class="py-4">
                    <div
                        class="space-x-1 rounded-lg border border-warning-600 bg-warning-100 text-warning-800 dark:bg-warning-900 dark:text-warning-200 dark:border-warning-500 p-4">
                        <div class="flex">
                            <div class="flex-shrink-0">
                                <x-heroicon-s-exclamation-triangle class="h-5 w-5 text-warning-400" />
                            </div>
                            <div class="ml-3">
                                <h3 class="text-sm font-medium text-warning-800 dark:text-warning-200">
                                    {{ t('campaign_limit_reached') }}</h3>
                                <div class="mt-2 text-sm text-warning-700 dark:text-warning-300">
                                    <p>{{ t('campaign_limit_reached_upgrade_plan') }} <a
                                            href="{{ tenant_route('tenant.subscription') }}"
                                            class="font-medium underline">{{ t('upgrade_plan') }}</a></p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            @endif

            <div id="campaign-manager" class="w-full">
                <campaign-manager :campaign-id="{{ $campaignId ?? 'null' }}"
                    statuses-data='@json($statuses)' sources-data='@json($sources)'
                    groups-data='@json($groups)'
                    tenant-subdomain="{{ $subdomain }}"></campaign-manager>
            </div>
       @endif
    </div>
</x-app-layout>
