<x-app-layout>
    <x-slot:title>
        {{ isset($template) ? 'Edit Messenger Template' : (t('create_facebook_messenger_template') ?? 'Create Messenger Template') }}
    </x-slot:title>

    <x-breadcrumb :items="[
        ['label' => t('dashboard'), 'route' => tenant_route('tenant.dashboard')],
        ['label' => 'Facebook Messenger'],
        ['label' => 'Templates', 'route' => tenant_route('tenant.facebook-messenger.templates')],
        ['label' => isset($template) ? 'Edit' : 'Create'],
    ]" />

    @if(isset($template))
        @php $fbTemplateData = $template->only(['id', 'name', 'content_type', 'message_text', 'media_url', 'buttons']); @endphp
        <script>window.fbTemplateData = @json($fbTemplateData);</script>
    @endif

    <div id="fb-messenger-template-create" data-subdomain="{{ tenant_subdomain() }}"></div>

</x-app-layout>
