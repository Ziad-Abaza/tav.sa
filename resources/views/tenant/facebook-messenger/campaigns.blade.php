<x-app-layout>
    <x-slot:title>
        {{ t('facebook_messenger_campaigns') ?? 'Messenger Campaigns' }}
    </x-slot:title>

    <x-breadcrumb :items="[
        ['label' => t('dashboard'), 'route' => tenant_route('tenant.dashboard')],
        ['label' => 'Facebook Messenger'],
        ['label' => 'Campaigns'],
    ]" />

    <div class="mb-6">
        <div class="flex flex-col sm:flex-row justify-between items-start lg:items-center gap-2 mb-4">
            <div>
                <x-page-heading>
                    {{ t('facebook_messenger_campaigns') ?? 'Facebook Messenger Campaigns' }}
                </x-page-heading>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    Create and manage broadcast campaigns for Facebook Messenger.
                </p>
            </div>
            <x-button.primary :href="tenant_route('tenant.facebook-messenger.campaign.create')" class="mt-4">
                <x-heroicon-m-plus class="w-4 h-4 text-white mr-2" /> {{ t('create_campaign') ?? 'Create Campaign' }}
            </x-button.primary>
        </div>

        <div id="fb-messenger-campaign-datatable" data-subdomain="{{ tenant_subdomain() }}"></div>
    </div>
</x-app-layout>
