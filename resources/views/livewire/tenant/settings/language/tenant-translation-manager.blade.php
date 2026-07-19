<div class="px-4 md:px-0">
    <x-slot:title>{{ t('translation_management') }}</x-slot:title>

    <x-card class="mx-4 lg:mx-0 rounded-lg">
        <x-slot:content>
            <x-slot:header>
                <x-settings-heading class="font-display">
                    {{ t('translate_language') }}
                </x-settings-heading>
                <x-settings-description>
                    {{ t('translate_language') }} {{ t('english_to') }}
                    <span class="text-primary-400 font-medium">{{ $languageName[0] }} </span>
                </x-settings-description>
            </x-slot:header>
            <div class="mx-auto md:px-0">
                <div wire:ignore>
                    <div id="tenant-language-lines-datatable" data-subdomain="{{ tenant_subdomain() }}"
                        data-language-code="{{ $code }}"></div>
                </div>
            </div>
        </x-slot:content>
    </x-card>
</div>
