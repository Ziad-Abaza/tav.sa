<div>
    <x-slot:title>
        {{ t('invoices') }}
    </x-slot:title>
    <x-breadcrumb :items="[['label' => t('dashboard'), 'route' => route('admin.dashboard')], ['label' => t('invoices')]]" />

    <div class="mt-8 lg:mt-0">
        <div wire:ignore>
            <div id="adminmaininvoice-datatable"></div>
        </div>
    </div>

</div>
