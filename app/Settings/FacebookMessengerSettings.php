<?php

namespace App\Settings;

use Spatie\LaravelSettings\Settings;

class FacebookMessengerSettings extends Settings
{
    /**
     * Facebook Messenger Access Token (encrypted)
     */
    public string $fb_messenger_access_token;

    /**
     * Facebook App ID
     */
    public string $fb_messenger_app_id;

    /**
     * Webhook Verify Token
     */
    public string $fb_messenger_verify_token;

    /**
     * Webhook Callback URL (generated, read-only)
     */
    public string $fb_messenger_webhook_url;

    /**
     * Enable/disable Facebook Messenger integration
     */
    public bool $fb_messenger_enabled;

    /**
     * Facebook Page ID
     */
    public string $fb_messenger_page_id;

    /**
     * Encrypted properties
     *
     * @var array<string>
     */
    public static function encrypted(): array
    {
        return [
            'fb_messenger_access_token',
        ];
    }

    public static function group(): string
    {
        return 'facebook_messenger';
    }
}
