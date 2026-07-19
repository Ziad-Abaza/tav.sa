<x-app-layout>
    <x-slot:title>
        {{ t('facebook_messenger_templates') ?? 'Messenger Templates' }}
    </x-slot:title>

    <x-breadcrumb :items="[
        ['label' => t('dashboard'), 'route' => tenant_route('tenant.dashboard')],
        ['label' => 'Facebook Messenger'],
        ['label' => 'Templates'],
    ]" />

    <div class="mb-6">
        <div class="flex flex-col sm:flex-row justify-between items-start lg:items-center gap-2 mb-4">
            <div>
            <x-page-heading>
                {{ t('facebook_messenger_templates') ?? 'Facebook Messenger Templates' }}
            </x-page-heading>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Create and manage message templates for Facebook Messenger campaigns and bots.
            </p>
            </div>
            <x-button.primary :href="tenant_route('tenant.facebook-messenger.template.create')" class="mt-4">
                <x-heroicon-m-plus class="w-4 h-4 text-white mr-2" /> {{ t('create_template') ?? 'Create Template' }}
            </x-button.primary>
        </div>

        <div id="fb-messenger-template-datatable" data-subdomain="{{ tenant_subdomain() }}"></div>
</x-app-layout>
