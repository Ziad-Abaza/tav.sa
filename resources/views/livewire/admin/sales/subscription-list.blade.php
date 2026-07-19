<div >
    <x-slot:title>
        {{ t('Subscription') }}
    </x-slot:title>

       <x-breadcrumb :items="[
        ['label' => t('dashboard'), 'route' => route('admin.dashboard')],
        ['label' => t('subscriptions')],
    ]" />

    <div class="mt-8 lg:mt-0">
        <div id="admin-subscription-datatable"></div>
    </div>

</div>
