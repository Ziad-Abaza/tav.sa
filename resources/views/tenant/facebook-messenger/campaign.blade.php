<x-app-layout>
    <x-slot:title>
        {{ $campaignId ? t('edit_campaign') : t('create_campaign') }}
    </x-slot:title>

    <x-breadcrumb :items="[
        ['label' => t('dashboard'), 'route' => tenant_route('tenant.dashboard')],
        ['label' => 'Facebook Messenger'],
        ['label' => t('campaigns'), 'route' => tenant_route('tenant.facebook-messenger.campaigns')],
        ['label' => $campaignId ? t('edit') : t('create')],
    ]" />

    <div class="mb-6">
        <div class="flex flex-col sm:flex-row justify-between items-start lg:items-center gap-2 mb-4">
            <div>
                <x-page-heading>
                    {{ $campaignId ? t('edit_campaign') : t('create_campaign') }}
                </x-page-heading>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                    {{ $campaignId ? 'Update your Facebook Messenger campaign.' : 'Create a new broadcast campaign for Facebook Messenger.' }}
                </p>
            </div>
        </div>

        <div id="fb-messenger-campaign-manager"
             data-subdomain="{{ $subdomain }}"
             data-campaign-id="{{ $campaignId ?? '' }}"
             data-statuses='@json($statuses)'
             data-sources='@json($sources)'
             data-groups='@json($groups)'>
        </div>
    </div>
</x-app-layout>
