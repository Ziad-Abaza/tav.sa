<x-app-layout>
    <x-slot:title>
        {{ t('transactions') }}
    </x-slot:title>
    <x-breadcrumb :items="[['label' => t('dashboard'), 'route' => route('admin.dashboard')], ['label' => t('transactions')]]" />

    <div x-init="setInterval(() => {
        Livewire.dispatch('refreshTable');
    
    }, 30000);">

        <div class="mt-8 lg:mt-0">
            <div wire:ignore>
                <div id="transaction-datatable"></div>
            </div>
        </div>
    </div>


</x-app-layout>
