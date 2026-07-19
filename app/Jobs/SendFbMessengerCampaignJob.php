<?php

namespace App\Jobs;

use App\Models\Tenant\Contact;
use App\Models\Tenant\FacebookMessengerTemplate;
use App\Models\Tenant\FbMessengerCampaign;
use App\Models\Tenant\FbMessengerCampaignDetail;
use App\Services\FacebookMessengerService;
use Corbital\LaravelEmails\Services\MergeFieldsService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\ThrottlesExceptions;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendFbMessengerCampaignJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Number of times the job may be attempted
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job
     */
    public array $backoff = [180, 300, 600];

    /**
     * The maximum number of seconds the job should be allowed to run
     */
    public int $timeout = 120;

    /**
     * Maximum number of exceptions before failing
     */
    public int $maxExceptions = 3;

    /**
     * Delete the job if models no longer exist
     */
    public bool $deleteWhenMissingModels = true;

    protected int $detailId;

    protected int $campaignId;

    protected int $tenantId;

    /**
     * Create a new job instance.
     */
    public function __construct(
        int $detailId,
        int $campaignId,
        int $tenantId
    ) {
        $this->detailId = $detailId;
        $this->campaignId = $campaignId;
        $this->tenantId = $tenantId;

        // Use the same queue as WhatsApp messages
        $this->onQueue('whatsapp-messages');
    }

    /**
     * Get the middleware the job should pass through.
     */
    public function middleware(): array
    {
        return [
            new WithoutOverlapping("fb_campaign_detail:{$this->detailId}"),
            new ThrottlesExceptions(10, 5), // Max 10 exceptions per 5 minutes
        ];
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Check if campaign is paused
        $isPaused = Cache::remember(
            "fb_campaign:{$this->campaignId}:paused",
            60,
            fn () => FbMessengerCampaign::where('id', $this->campaignId)->value('pause_campaign')
        );

        if ($isPaused) {
            $this->release(180);

            return;
        }

        try {
            // Fetch detail
            $detail = FbMessengerCampaignDetail::find($this->detailId);

            // Check existence
            if (! $detail || ! $detail->exists) {
                return;
            }

            // Refresh to get latest data from database
            $detail->refresh();

            // Validate status hasn't changed (might have been processed already)
            if ($detail->status !== FbMessengerCampaignDetail::STATUS_PENDING) {
                return;
            }

            // Load relationships
            $detail->load(['campaign', 'campaign.fbTemplate']);

            $campaign = $detail->campaign;

            if (! $campaign) {
                $this->fail(new \Exception("Campaign not found for detail {$this->detailId}"));

                return;
            }

            $template = $campaign->fbTemplate;

            if (! $template) {
                $this->fail(new \Exception("Template not found for campaign {$this->campaignId}"));

                return;
            }

            // Get contact for merge fields
            $tenantSubdomain = Cache::remember(
                "tenant:{$this->tenantId}:subdomain",
                3600,
                fn () => tenant_subdomain_by_tenant_id($this->tenantId)
            );

            $contact = Contact::fromTenant($tenantSubdomain)
                ->find($detail->contact_id);

            // Prepare message text with merge fields
            $messageText = $this->processMessageText($template, $contact, $campaign);

            // Store the final message text
            $detail->message_text = $messageText;
            $detail->save();

            // Initialize Facebook Messenger service
            $fbService = new FacebookMessengerService($this->tenantId);

            if (! $fbService->isConfigured()) {
                $detail->markAsFailed('Facebook Messenger not configured for tenant');

                return;
            }

            // Send message based on template type
            $result = $this->sendMessage($fbService, $detail->facebook_psid, $template, $messageText);

            if ($result['success']) {
                $detail->markAsSent($result['message_id'] ?? null, 'sent');

                // Update campaign and template sending counts
                $campaign->increment('sending_count');
                $template->incrementSendingCount();

                Log::info('FB Messenger campaign message sent', [
                    'detail_id' => $this->detailId,
                    'campaign_id' => $this->campaignId,
                    'message_id' => $result['message_id'] ?? null,
                ]);
            } else {
                $detail->markAsFailed($result['error'] ?? 'Unknown error');

                Log::warning('FB Messenger campaign message failed', [
                    'detail_id' => $this->detailId,
                    'campaign_id' => $this->campaignId,
                    'error' => $result['error'] ?? 'Unknown error',
                ]);
            }
        } catch (\Exception $e) {
            $detail = FbMessengerCampaignDetail::find($this->detailId);
            if ($detail) {
                $detail->markAsFailed($e->getMessage());
            }

            Log::error('FB Messenger campaign job exception', [
                'detail_id' => $this->detailId,
                'campaign_id' => $this->campaignId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            throw $e;
        }
    }

    /**
     * Send message based on template content type
     */
    protected function sendMessage(
        FacebookMessengerService $fbService,
        string $recipientId,
        FacebookMessengerTemplate $template,
        string $messageText
    ): array {
        $buttons = $template->buttons ?? [];

        // If template has media attachment
        if ($template->hasMedia()) {
            $mediaType = match ($template->content_type) {
                FacebookMessengerTemplate::CONTENT_TYPE_IMAGE => 'image',
                FacebookMessengerTemplate::CONTENT_TYPE_VIDEO => 'video',
                FacebookMessengerTemplate::CONTENT_TYPE_DOCUMENT => 'file',
                FacebookMessengerTemplate::CONTENT_TYPE_AUDIO => 'audio',
                default => 'file',
            };

            // Send attachment
            $attachmentResult = $fbService->sendAttachment($recipientId, $mediaType, $template->media_url);

            // If attachment failed, return the error
            if (! $attachmentResult['success']) {
                return $attachmentResult;
            }

            // If there's also a text message, send it after attachment
            if (! empty($messageText)) {
                if (! empty($buttons)) {
                    return $fbService->sendQuickReplyMessage($recipientId, $messageText, $buttons);
                }

                return $fbService->sendTextMessage($recipientId, $messageText);
            }

            return $attachmentResult;
        }

        // Text message (possibly with buttons)
        if (! empty($buttons)) {
            return $fbService->sendQuickReplyMessage($recipientId, $messageText, $buttons);
        }

        return $fbService->sendTextMessage($recipientId, $messageText);
    }

    /**
     * Process message text with merge fields
     * Replaces {{1}}, {{2}}, etc. with mapped merge fields and then parses with contact data
     */
    protected function processMessageText(FacebookMessengerTemplate $template, ?Contact $contact, FbMessengerCampaign $campaign): string
    {
        $messageText = $template->message_text ?? '';

        if (! $contact) {
            return $messageText;
        }

        // Step 1: Replace numbered variables {{1}}, {{2}} with stored merge field mappings
        $variablesJson = $campaign->variables_json ?? [];

        if (! empty($variablesJson)) {
            foreach ($variablesJson as $index => $mergeFieldValue) {
                $variableNumber = $index + 1;
                $messageText = str_replace("{{{$variableNumber}}}", $mergeFieldValue, $messageText);
            }
        }

        // Step 2: Parse @{field_name} syntax using MergeFieldsService
        try {
            $mergeFieldsService = app(MergeFieldsService::class);

            // Prepare context for merge field parsing
            $context = [
                'contactId' => $contact->id,
                'relType' => 'contact',
                'tenantId' => $this->tenantId,
            ];

            // Convert @{field} to {field} for parsing
            $messageText = preg_replace('/@\{(.*?)\}/', '{$1}', $messageText);

            // Parse using both contact and other groups
            $messageText = $mergeFieldsService->parseTemplates(
                ['tenant-contact-group', 'tenant-other-group'],
                $messageText,
                $context
            );
        } catch (\Exception $e) {
            Log::warning('FB Messenger: MergeFieldsService parsing failed, falling back to basic replacements', [
                'error' => $e->getMessage(),
                'campaign_id' => $campaign->id,
            ]);

            // Fallback to basic replacement if MergeFieldsService fails
            $messageText = $this->basicMergeFieldReplacement($messageText, $contact);
        }

        return $messageText;
    }

    /**
     * Basic merge field replacement fallback
     */
    protected function basicMergeFieldReplacement(string $messageText, Contact $contact): string
    {
        $mergeFields = [
            '{contact_first_name}' => $contact->firstname ?? '',
            '{contact_last_name}' => $contact->lastname ?? '',
            '{contact_full_name}' => trim(($contact->firstname ?? '').' '.($contact->lastname ?? '')),
            '{contact_email}' => $contact->email ?? '',
            '{contact_phone}' => $contact->phone ?? '',
            '{contact_company}' => $contact->company ?? '',
            '{contact_website}' => $contact->website ?? '',
            '{contact_address}' => $contact->address ?? '',
            '{contact_city}' => $contact->city ?? '',
            '{contact_state}' => $contact->state ?? '',
            '{contact_country}' => $contact->country ?? '',
            '{contact_zip}' => $contact->zip ?? '',
            '{contact_facebook_psid}' => $contact->facebook_psid ?? '',
            // Legacy format support
            '{{firstname}}' => $contact->firstname ?? '',
            '{{lastname}}' => $contact->lastname ?? '',
            '{{email}}' => $contact->email ?? '',
            '{{phone}}' => $contact->phone ?? '',
            '{{name}}' => trim(($contact->firstname ?? '').' '.($contact->lastname ?? '')),
        ];

        return str_replace(array_keys($mergeFields), array_values($mergeFields), $messageText);
    }

    /**
     * Handle a job failure
     */
    public function failed(\Throwable $exception): void
    {
        $detail = FbMessengerCampaignDetail::find($this->detailId);
        if ($detail) {
            $detail->markAsFailed('Job failed: '.$exception->getMessage());
        }

        Log::error('FB Messenger campaign job failed permanently', [
            'detail_id' => $this->detailId,
            'campaign_id' => $this->campaignId,
            'error' => $exception->getMessage(),
        ]);
    }
}
