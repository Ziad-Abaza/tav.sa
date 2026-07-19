<?php

namespace App\Livewire\Tenant\Settings\WhatsMark;

use App\Rules\PurifiedInput;
use Illuminate\Support\Str;
use Livewire\Component;

class FacebookMessengerSettings extends Component
{
    public string $fb_messenger_access_token = '';

    public string $fb_messenger_app_id = '';

    public string $fb_messenger_verify_token = '';

    public string $fb_messenger_webhook_url = '';

    public bool $fb_messenger_enabled = false;

    public string $fb_messenger_page_id = '';

    protected function rules()
    {
        return [
            'fb_messenger_access_token' => [
                $this->fb_messenger_enabled ? 'required' : 'nullable',
                'string',
                new PurifiedInput(t('sql_injection_error')),
            ],
            'fb_messenger_app_id' => [
                $this->fb_messenger_enabled ? 'required' : 'nullable',
                'string',
                new PurifiedInput(t('sql_injection_error')),
            ],
            'fb_messenger_verify_token' => [
                'required',
                'string',
                new PurifiedInput(t('sql_injection_error')),
            ],
            'fb_messenger_page_id' => [
                $this->fb_messenger_enabled ? 'required' : 'nullable',
                'string',
                new PurifiedInput(t('sql_injection_error')),
            ],
            'fb_messenger_enabled' => ['boolean'],
        ];
    }

    public function mount()
    {
        if (! checkPermission('tenant.facebook_messenger.settings.view')) {
            $this->notify(['type' => 'danger', 'message' => t('access_denied_note')], true);

            return redirect()->to(tenant_route('tenant.dashboard'));
        }

        $settings = tenant_settings_by_group('whats-mark');

        $this->fb_messenger_access_token = $settings['fb_messenger_access_token'] ?? '';
        $this->fb_messenger_app_id = $settings['fb_messenger_app_id'] ?? '';
        $this->fb_messenger_verify_token = $settings['fb_messenger_verify_token'] ?? '';
        $this->fb_messenger_webhook_url = $this->generateWebhookUrl();
        $this->fb_messenger_enabled = $settings['fb_messenger_enabled'] ?? false;
        $this->fb_messenger_page_id = $settings['fb_messenger_page_id'] ?? '';
    }

    public function save()
    {
        if (! checkPermission('tenant.facebook_messenger.settings.view')) {
            $this->notify(['type' => 'danger', 'message' => t('access_denied_note')]);

            return;
        }

        $this->validate();

        try {
            $originalSettings = tenant_settings_by_group('whats-mark');

            $newSettings = [
                'fb_messenger_access_token' => $this->fb_messenger_access_token,
                'fb_messenger_app_id' => $this->fb_messenger_app_id,
                'fb_messenger_verify_token' => $this->fb_messenger_verify_token,
                'fb_messenger_webhook_url' => $this->generateWebhookUrl(),
                'fb_messenger_enabled' => $this->fb_messenger_enabled,
                'fb_messenger_page_id' => $this->fb_messenger_page_id,
            ];

            // Filter only modified or undefined settings
            $modifiedSettings = array_filter($newSettings, function ($value, $key) use ($originalSettings) {
                return ! array_key_exists($key, $originalSettings) || $originalSettings[$key] !== $value;
            }, ARRAY_FILTER_USE_BOTH);

            if (! empty($modifiedSettings)) {
                foreach ($modifiedSettings as $key => $value) {
                    save_tenant_setting('whats-mark', $key, $value);
                }

                $this->notify(['type' => 'success', 'message' => t('setting_save_successfully')]);
            }

            // Clear tenant cache
            if (function_exists('clear_tenant_cache')) {
                clear_tenant_cache(tenant_id());
            }
        } catch (\Exception $e) {
            app_log(
                'Facebook Messenger settings save error: '.$e->getMessage(),
                'error',
                $e
            );

            $this->notify([
                'type' => 'danger',
                'message' => t('something_went_wrong').' '.$e->getMessage(),
            ]);
        }
    }

    public function generateVerifyToken()
    {
        $this->fb_messenger_verify_token = Str::random(32);

        $this->notify([
            'type' => 'success',
            'message' => t('verify_token_generated_successfully'),
        ]);
    }

    protected function generateWebhookUrl(): string
    {
        return route('facebook.messenger.webhook');
    }

    public function testConnection()
    {
        if (! $this->fb_messenger_access_token || ! $this->fb_messenger_app_id) {
            $this->notify([
                'type' => 'danger',
                'message' => t('fill_required_facebook_messenger_credentials'),
            ]);

            return;
        }

        // TODO: Implement Facebook API connection test
        $this->notify([
            'type' => 'info',
            'message' => t('connection_test_not_implemented_yet'),
        ]);
    }

    public function render()
    {
        return view('livewire.tenant.settings.whats-mark.facebook-messenger-settings');
    }
}
