<div>
    <x-slot:title>
        {{ t('invoices') }}
    </x-slot:title>
    <x-breadcrumb :items="[['label' => t('dashboard'), 'route' => tenant_route('tenant.dashboard')], ['label' => t('invoices')]]" />

    <div class="mt-8 lg:mt-0">
        <div id="maininvoice-datatable" data-subdomain="{{ tenant_subdomain() }}"></div>
    </div>
</div>
