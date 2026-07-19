<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class FacebookMessengerService
{
    protected string $graphApiUrl = 'https://graph.facebook.com/v21.0';

    protected ?string $accessToken = null;

    protected ?string $pageId = null;

    protected ?int $tenantId = null;

    /**
     * Initialize the service with tenant settings
     */
    public function __construct(?int $tenantId = null)
    {
        if ($tenantId) {
            $this->setTenant($tenantId);
        }
    }

    /**
     * Set tenant and load settings
     */
    public function setTenant(int $tenantId): self
    {
        $this->tenantId = $tenantId;
        $this->loadSettings();

        return $this;
    }

    /**
     * Load settings from tenant_settings
     */
    protected function loadSettings(): void
    {
        $this->accessToken = $this->getTenantSetting('fb_messenger_access_token');
        $this->pageId = $this->getTenantSetting('fb_messenger_page_id');
    }

    /**
     * Get a tenant setting
     */
    protected function getTenantSetting(string $key): ?string
    {
        $value = get_tenant_setting_by_tenant_id('whats-mark', $key, null, $this->tenantId);

        return $value ?: null;
    }

    /**
     * Check if service is configured
     */
    public function isConfigured(): bool
    {
        return ! empty($this->accessToken) && ! empty($this->pageId);
    }

    /**
     * Send a text message to a user
     *
     * @param  string  $recipientId  The Facebook PSID of the recipient
     * @param  string  $message  The message text
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendTextMessage(string $recipientId, string $message): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Facebook Messenger not configured'];
        }

        try {
            $response = Http::post("{$this->graphApiUrl}/me/messages", [
                'access_token' => $this->accessToken,
                'recipient' => ['id' => $recipientId],
                'message' => ['text' => $message],
                'messaging_type' => 'RESPONSE',
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['message_id'])) {
                return [
                    'success' => true,
                    'message_id' => $data['message_id'],
                    'recipient_id' => $data['recipient_id'] ?? $recipientId,
                ];
            }

            app_log('Facebook Messenger send failed', 'error', null, [
                'recipient_id' => $recipientId,
                'response' => $data,
            ], $this->tenantId);

            return [
                'success' => false,
                'error' => $data['error']['message'] ?? 'Failed to send message',
            ];
        } catch (\Exception $e) {
            app_log('Facebook Messenger send exception', 'error', $e, [
                'recipient_id' => $recipientId,
            ], $this->tenantId);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send an attachment (image, video, file, audio)
     *
     * @param  string  $recipientId  The Facebook PSID of the recipient
     * @param  string  $type  The attachment type (image, video, file, audio)
     * @param  string  $url  The URL of the attachment
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendAttachment(string $recipientId, string $type, string $url): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Facebook Messenger not configured'];
        }

        try {
            $response = Http::post("{$this->graphApiUrl}/me/messages", [
                'access_token' => $this->accessToken,
                'recipient' => ['id' => $recipientId],
                'message' => [
                    'attachment' => [
                        'type' => $type,
                        'payload' => ['url' => $url, 'is_reusable' => true],
                    ],
                ],
                'messaging_type' => 'RESPONSE',
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['message_id'])) {
                return [
                    'success' => true,
                    'message_id' => $data['message_id'],
                    'recipient_id' => $data['recipient_id'] ?? $recipientId,
                ];
            }

            return [
                'success' => false,
                'error' => $data['error']['message'] ?? 'Failed to send attachment',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Get user profile information from Facebook Graph API
     *
     * @param  string  $userId  The Facebook PSID
     * @return array{first_name?: string, last_name?: string, profile_pic?: string}|null
     */
    public function getUserProfile(string $userId): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        // Cache user profile for 24 hours
        $cacheKey = "fb_user_profile_{$this->tenantId}_{$userId}";

        return Cache::remember($cacheKey, 86400, function () use ($userId) {
            try {
                $response = Http::get("{$this->graphApiUrl}/{$userId}", [
                    'fields' => 'first_name,last_name,profile_pic',
                    'access_token' => $this->accessToken,
                ]);

                if ($response->successful()) {
                    $data = $response->json();

                    return [
                        'first_name' => $data['first_name'] ?? null,
                        'last_name' => $data['last_name'] ?? null,
                        'profile_pic' => $data['profile_pic'] ?? null,
                    ];
                }

                return null;
            } catch (\Exception $e) {
                app_log('Error fetching Facebook user profile', 'error', $e, [
                    'user_id' => $userId,
                ], $this->tenantId);

                return null;
            }
        });
    }

    /**
     * Mark a message as seen
     */
    public function markSeen(string $recipientId): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::post("{$this->graphApiUrl}/me/messages", [
                'access_token' => $this->accessToken,
                'recipient' => ['id' => $recipientId],
                'sender_action' => 'mark_seen',
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Show typing indicator
     */
    public function showTyping(string $recipientId, bool $on = true): bool
    {
        if (! $this->isConfigured()) {
            return false;
        }

        try {
            $response = Http::post("{$this->graphApiUrl}/me/messages", [
                'access_token' => $this->accessToken,
                'recipient' => ['id' => $recipientId],
                'sender_action' => $on ? 'typing_on' : 'typing_off',
            ]);

            return $response->successful();
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Get access token (for testing)
     */
    public function getAccessToken(): ?string
    {
        return $this->accessToken;
    }

    /**
     * Get page ID (for testing)
     */
    public function getPageId(): ?string
    {
        return $this->pageId;
    }

    /**
     * Send a message with Quick Reply buttons
     *
     * @param  string  $recipientId  The Facebook PSID of the recipient
     * @param  string  $text  The message text
     * @param  array  $buttons  Array of buttons with 'type', 'title', 'payload' or 'url'
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendQuickReplyMessage(string $recipientId, string $text, array $buttons): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Facebook Messenger not configured'];
        }

        // Check if any buttons are URLs - if so, use Button Template instead
        $hasUrlButtons = collect($buttons)->contains(fn ($b) => ($b['type'] ?? '') === 'web_url');

        if ($hasUrlButtons && count($buttons) <= 3) {
            return $this->sendButtonTemplate($recipientId, $text, $buttons);
        }

        // Use Quick Replies for postback buttons (up to 13)
        $quickReplies = collect($buttons)
            ->filter(fn ($b) => ($b['type'] ?? '') === 'postback')
            ->take(13)
            ->map(fn ($b) => [
                'content_type' => 'text',
                'title' => substr($b['title'] ?? 'Button', 0, 20),
                'payload' => $b['payload'] ?? strtoupper(str_replace(' ', '_', $b['title'] ?? 'BUTTON')),
            ])
            ->values()
            ->all();

        if (empty($quickReplies)) {
            // No valid quick replies, just send text
            return $this->sendTextMessage($recipientId, $text);
        }

        try {
            $response = Http::post("{$this->graphApiUrl}/me/messages", [
                'access_token' => $this->accessToken,
                'recipient' => ['id' => $recipientId],
                'message' => [
                    'text' => $text,
                    'quick_replies' => $quickReplies,
                ],
                'messaging_type' => 'RESPONSE',
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['message_id'])) {
                return [
                    'success' => true,
                    'message_id' => $data['message_id'],
                    'recipient_id' => $data['recipient_id'] ?? $recipientId,
                ];
            }

            app_log('Facebook Messenger quick reply send failed', 'error', null, [
                'recipient_id' => $recipientId,
                'response' => $data,
            ], $this->tenantId);

            return [
                'success' => false,
                'error' => $data['error']['message'] ?? 'Failed to send message',
            ];
        } catch (\Exception $e) {
            app_log('Facebook Messenger quick reply exception', 'error', $e, [
                'recipient_id' => $recipientId,
            ], $this->tenantId);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a Button Template message (for URL buttons, max 3)
     *
     * @param  string  $recipientId  The Facebook PSID of the recipient
     * @param  string  $text  The message text (max 640 chars)
     * @param  array  $buttons  Array of up to 3 buttons
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendButtonTemplate(string $recipientId, string $text, array $buttons): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Facebook Messenger not configured'];
        }

        $templateButtons = collect($buttons)
            ->take(3)
            ->map(function ($b) {
                if (($b['type'] ?? '') === 'web_url' && ! empty($b['url'])) {
                    return [
                        'type' => 'web_url',
                        'title' => substr($b['title'] ?? 'Link', 0, 20),
                        'url' => $b['url'],
                    ];
                }

                return [
                    'type' => 'postback',
                    'title' => substr($b['title'] ?? 'Button', 0, 20),
                    'payload' => $b['payload'] ?? strtoupper(str_replace(' ', '_', $b['title'] ?? 'BUTTON')),
                ];
            })
            ->values()
            ->all();

        try {
            $response = Http::post("{$this->graphApiUrl}/me/messages", [
                'access_token' => $this->accessToken,
                'recipient' => ['id' => $recipientId],
                'message' => [
                    'attachment' => [
                        'type' => 'template',
                        'payload' => [
                            'template_type' => 'button',
                            'text' => substr($text, 0, 640),
                            'buttons' => $templateButtons,
                        ],
                    ],
                ],
                'messaging_type' => 'RESPONSE',
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['message_id'])) {
                return [
                    'success' => true,
                    'message_id' => $data['message_id'],
                    'recipient_id' => $data['recipient_id'] ?? $recipientId,
                ];
            }

            app_log('Facebook Messenger button template send failed', 'error', null, [
                'recipient_id' => $recipientId,
                'response' => $data,
            ], $this->tenantId);

            return [
                'success' => false,
                'error' => $data['error']['message'] ?? 'Failed to send button template',
            ];
        } catch (\Exception $e) {
            app_log('Facebook Messenger button template exception', 'error', $e, [
                'recipient_id' => $recipientId,
            ], $this->tenantId);

            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Send a Generic Template (card carousel)
     *
     * @param  string  $recipientId  The Facebook PSID of the recipient
     * @param  array  $elements  Array of card elements
     * @return array{success: bool, message_id?: string, error?: string}
     */
    public function sendGenericTemplate(string $recipientId, array $elements): array
    {
        if (! $this->isConfigured()) {
            return ['success' => false, 'error' => 'Facebook Messenger not configured'];
        }

        try {
            $response = Http::post("{$this->graphApiUrl}/me/messages", [
                'access_token' => $this->accessToken,
                'recipient' => ['id' => $recipientId],
                'message' => [
                    'attachment' => [
                        'type' => 'template',
                        'payload' => [
                            'template_type' => 'generic',
                            'elements' => array_slice($elements, 0, 10), // Max 10 elements
                        ],
                    ],
                ],
                'messaging_type' => 'RESPONSE',
            ]);

            $data = $response->json();

            if ($response->successful() && isset($data['message_id'])) {
                return [
                    'success' => true,
                    'message_id' => $data['message_id'],
                    'recipient_id' => $data['recipient_id'] ?? $recipientId,
                ];
            }

            return [
                'success' => false,
                'error' => $data['error']['message'] ?? 'Failed to send generic template',
            ];
        } catch (\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
}
