<?php

namespace App\Http\Controllers\FacebookMessenger;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Tenant\ManageChat;
use App\Models\Tenant\Chat;
use App\Models\Tenant\ChatMessage;
use App\Models\Tenant\Contact;
use App\Models\Tenant\Source;
use App\Models\Tenant\Status;
use App\Services\FacebookMessengerService;
use App\Services\FeatureService;
use App\Services\pusher\PusherService;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FacebookMessengerWebhookController extends Controller
{
    protected ?int $tenant_id = null;

    protected ?string $tenant_subdomain = null;

    protected ?string $page_id = null;

    protected $featureLimitChecker;

    protected ?FacebookMessengerService $messengerService = null;

    /**
     * Handle incoming Facebook Messenger webhook requests
     */
    public function __invoke(Request $request)
    {
        // Handle verification (GET request)
        if ($request->isMethod('get')) {
            return $this->handleVerification($request);
        }

        // Handle incoming webhook (POST request)
        if ($request->isMethod('post')) {
            return $this->handleIncomingWebhook($request);
        }

        return response('Method not allowed', 405);
    }

    /**
     * Handle webhook verification from Facebook
     */
    protected function handleVerification(Request $request)
    {
        $mode = $request->query('hub_mode');
        $token = $request->query('hub_verify_token');
        $challenge = $request->query('hub_challenge');

        // If no verification parameters provided, return OK (webhook endpoint is active)
        if (empty($mode) && empty($token) && empty($challenge)) {
            return response('Webhook endpoint active', 200);
        }

        // Check verify token against any tenant's settings
        try {
            // Query tenant_settings table to find matching verify token
            // Settings are stored in 'whats-mark' group by the FacebookMessengerSettings Livewire component
            $settings = DB::table('tenant_settings')
                ->where('group', 'whats-mark')
                ->where('key', 'fb_messenger_verify_token')
                ->get();

            foreach ($settings as $s) {
                // Tenant settings store value as JSON-encoded string
                $verifyToken = json_decode($s->value ?? '""', true);

                if ($mode === 'subscribe' && $token === $verifyToken) {
                    return response($challenge, 200)
                        ->header('Content-Type', 'text/plain');
                }
            }
        } catch (\Exception $e) {
            app_log('Facebook Messenger verification error: '.$e->getMessage(), 'error', $e);
        }

        return response('Forbidden', 403);
    }

    /**
     * Handle incoming webhook from Facebook Messenger
     */
    protected function handleIncomingWebhook(Request $request)
    {
        try {
            // Get raw payload from php://input (same as WhatsApp webhook)
            $feedData = file_get_contents('php://input');

            if (empty($feedData)) {
                return response('Empty payload', 400);
            }

            $payload = json_decode($feedData, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                app_log('Facebook Messenger webhook JSON decode error', 'error', null, [
                    'error' => json_last_error_msg(),
                    'raw_data' => substr($feedData, 0, 500),
                ]);

                return response('Invalid JSON', 400);
            }

            // Validate it's from Facebook
            if (! isset($payload['object']) || $payload['object'] !== 'page') {

                return response('Invalid object type', 400);
            }

            // Check if there are entries
            if (! isset($payload['entry']) || ! is_array($payload['entry'])) {
                return response('Missing entries', 400);
            }

            // Process each entry
            foreach ($payload['entry'] ?? [] as $entry) {
                $this->processEntry($entry);
            }

            return response('EVENT_RECEIVED', 200);
        } catch (\Exception $e) {
            app_log('Facebook Messenger webhook processing error: '.$e->getMessage(), 'error', $e, [
                'payload' => $payload ?? [],
            ]);

            return response('Error processing webhook', 500);
        }
    }

    /**
     * Process a single entry from the webhook
     */
    protected function processEntry(array $entry)
    {
        $pageId = $entry['id'] ?? null;
        $this->page_id = $pageId;

        // Get tenant from page ID
        $tenantData = $this->getTenantByPageId($pageId);

        if (! $tenantData) {
            return;
        }

        $this->tenant_id = $tenantData['tenant_id'];
        $this->tenant_subdomain = $tenantData['subdomain'];
        $this->featureLimitChecker = app(FeatureService::class);
        $this->messengerService = new FacebookMessengerService($this->tenant_id);

        // Process messaging events
        foreach ($entry['messaging'] ?? [] as $messagingEvent) {
            $this->processMessagingEvent($messagingEvent);
        }
    }

    /**
     * Process a single messaging event
     */
    protected function processMessagingEvent(array $event)
    {
        $senderId = $event['sender']['id'] ?? null;
        $recipientId = $event['recipient']['id'] ?? null;
        $timestamp = $event['timestamp'] ?? now()->timestamp * 1000;

        if (! $senderId || ! $recipientId) {
            return;
        }

        // Handle different message types
        if (isset($event['message'])) {
            $this->processMessage($event['message'], $senderId, $recipientId, $timestamp);
        }

        // Handle delivery receipts
        if (isset($event['delivery'])) {
            $this->processDeliveryReceipt($event['delivery']);
        }

        // Handle read receipts
        if (isset($event['read'])) {
            $this->processReadReceipt($event['read']);
        }

        // TODO: Handle other event types (postback, referral, etc.)
    }

    /**
     * Process incoming message
     */
    protected function processMessage(array $message, string $senderId, string $recipientId, int $timestamp)
    {
        try {
            // Extract message content
            $messageText = $message['text'] ?? '';
            $messageId = $message['mid'] ?? null;

            // Check if message already processed (prevent duplicates)
            if (! empty($messageId)) {
                $found = ChatMessage::fromTenant($this->tenant_subdomain)
                    ->where('message_id', $messageId)
                    ->exists();

                if ($found) {
                    return;
                }
            }

            // Get real user name from Facebook Graph API
            $senderName = $this->getRealUserName($senderId);
            $contactData = $this->getContactData($senderId, $senderName);

            if (empty($contactData) || ! isset($contactData->id)) {
                app_log('Failed to get/create contact', 'error', null, ['sender_id' => $senderId], $this->tenant_id);

                return;
            }

            // Create or update chat/interaction
            $interactionId = $this->createOrUpdateInteraction(
                $senderId,
                $this->page_id,
                $senderName,
                $messageText,
                $contactData
            );

            // Handle attachments
            $attachments = $message['attachments'] ?? [];
            $attachmentUrl = '';
            if (! empty($attachments)) {
                $attachmentUrl = $attachments[0]['payload']['url'] ?? '';
            }

            // Store the message
            $storedMessageId = $this->storeInteractionMessage(
                $interactionId,
                $senderId,
                $messageId ?? '',
                $messageText,
                'text',
                $attachmentUrl
            );

            // Update chat with last message
            Chat::fromTenant($this->tenant_subdomain)->where('id', $interactionId)->update([
                'last_message' => $messageText,
                'last_msg_time' => now(),
                'time_sent' => now(),
            ]);

            // Trigger real-time notification via Pusher
            $this->triggerChatNotification($interactionId, $storedMessageId);

        } catch (\Exception $e) {
            app_log('Error processing Facebook Messenger message: '.$e->getMessage(), 'error', $e, [
                'sender_id' => $senderId,
            ], $this->tenant_id);
        }
    }

    /**
     * Process delivery receipt - update message status to 'delivered'
     */
    protected function processDeliveryReceipt(array $delivery): void
    {
        try {
            $messageIds = $delivery['mids'] ?? [];
            $watermark = $delivery['watermark'] ?? null;

            if (empty($messageIds)) {
                return;
            }

            // Update status of delivered messages
            ChatMessage::fromTenant($this->tenant_subdomain)
                ->where('tenant_id', $this->tenant_id)
                ->whereIn('message_id', $messageIds)
                ->where('status', '!=', 'read') // Don't downgrade from read to delivered
                ->update([
                    'status' => 'delivered',
                    'updated_at' => now(),
                ]);

        } catch (\Exception $e) {
            app_log('Error processing Facebook Messenger delivery receipt', 'error', $e, [
                'delivery' => $delivery,
            ], $this->tenant_id);
        }
    }

    /**
     * Process read receipt - update message status to 'read'
     */
    protected function processReadReceipt(array $read): void
    {
        try {
            $watermark = $read['watermark'] ?? null;

            if (empty($watermark)) {
                return;
            }

            // Facebook sends watermark timestamp - all messages sent before this time are read
            // Convert watermark (milliseconds) to a Carbon date
            $readTime = \Carbon\Carbon::createFromTimestampMs($watermark);

            // Update status of all messages sent before watermark to 'read'
            // We match by time_sent being before the watermark
            ChatMessage::fromTenant($this->tenant_subdomain)
                ->where('tenant_id', $this->tenant_id)
                ->where('staff_id', '!=', null) // Only outgoing messages (sent by staff)
                ->where('time_sent', '<=', $readTime)
                ->where('status', '!=', 'read')
                ->update([
                    'status' => 'read',
                    'updated_at' => now(),
                ]);

        } catch (\Exception $e) {
            app_log('Error processing Facebook Messenger read receipt', 'error', $e, [
                'read' => $read,
            ], $this->tenant_id);
        }
    }

    /**
     * Get or create contact from Facebook sender ID
     */
    protected function getContactData(string $senderId, string $senderName): ?object
    {
        // Check if contact exists by facebook_psid
        $contact = Contact::fromTenant($this->tenant_subdomain)
            ->where('tenant_id', $this->tenant_id)
            ->where('facebook_psid', $senderId)
            ->first();

        if ($contact) {
            // Update contact name if we got a real name from Graph API (not default fallback)
            if (! str_starts_with($senderName, 'Facebook User ')) {
                $nameParts = explode(' ', $senderName);
                $firstName = $nameParts[0];
                $lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '';

                // Only update if current name is the default fallback
                if (str_starts_with($contact->firstname, 'Facebook') || empty($contact->firstname)) {
                    $contact->update([
                        'firstname' => $firstName,
                        'lastname' => $lastName,
                    ]);
                }
            }

            return $contact;
        }

        // Check feature limit before creating new contact
        if ($this->featureLimitChecker && $this->featureLimitChecker->hasReachedLimit('contacts', Contact::class, [], true, $this->tenant_id)) {
            return null;
        }

        // Parse name
        $nameParts = explode(' ', $senderName);
        $firstName = $nameParts[0];
        $lastName = count($nameParts) > 1 ? implode(' ', array_slice($nameParts, 1)) : '';

        // Get default status and source
        $defaultStatusId = Status::where('tenant_id', $this->tenant_id)->value('id');
        $sourceId = $this->getFacebookMessengerSourceId();

        // Check if auto lead creation is enabled
        $autoLeadEnabled = get_tenant_setting_by_tenant_id('whats-mark', 'auto_lead_enabled', null, $this->tenant_id);

        $contact = Contact::fromTenant($this->tenant_subdomain)->create([
            'firstname' => $firstName,
            'lastname' => $lastName,
            'type' => $autoLeadEnabled ? 'lead' : 'guest',
            'facebook_psid' => $senderId,
            'assigned_id' => $autoLeadEnabled ? get_tenant_setting_by_tenant_id('whats-mark', 'lead_assigned_to', null, $this->tenant_id) : null,
            'status_id' => $autoLeadEnabled ? (get_tenant_setting_by_tenant_id('whats-mark', 'lead_status', null, $this->tenant_id) ?? $defaultStatusId) : $defaultStatusId,
            'source_id' => $sourceId,
            'addedfrom' => 0,
            'tenant_id' => $this->tenant_id,
        ]);

        if ($this->featureLimitChecker) {
            $this->featureLimitChecker->trackUsage('contacts', 1, $this->tenant_id);
        }

        return $contact;
    }

    /**
     * Create or update interaction (chat)
     */
    protected function createOrUpdateInteraction(
        string $senderId,
        string $pageId,
        string $name,
        string $message,
        object $contactData
    ): int {
        // Check if chat exists for this sender
        $existingChat = Chat::fromTenant($this->tenant_subdomain)
            ->where('tenant_id', $this->tenant_id)
            ->where('receiver_id', $senderId)
            ->first();

        if ($existingChat) {
            // Update existing chat
            Chat::fromTenant($this->tenant_subdomain)->where('id', $existingChat->id)->update([
                'wa_no' => $pageId, // Store page ID in wa_no field
                'wa_no_id' => 'fb_messenger', // Identifier for Facebook Messenger
                'name' => $name,
                'last_message' => $message,
                'time_sent' => now(),
                'type' => $contactData->type ?? 'guest',
                'type_id' => $contactData->id ?? '',
                'updated_at' => now(),
            ]);

            return $existingChat->id;
        }

        // Create new chat
        $newChatId = Chat::fromTenant($this->tenant_subdomain)->insertGetId([
            'receiver_id' => $senderId,
            'wa_no' => $pageId, // Store page ID
            'wa_no_id' => 'fb_messenger', // Identifier for Facebook Messenger
            'name' => $name,
            'last_message' => $message,
            'agent' => json_encode(['assign_id' => $contactData->assigned_id ?? 0, 'agents_id' => '']),
            'time_sent' => now(),
            'type' => $contactData->type ?? 'guest',
            'type_id' => $contactData->id ?? '',
            'session_reset_sent' => 0,
            'created_at' => now(),
            'updated_at' => now(),
            'tenant_id' => $this->tenant_id,
        ]);

        return $newChatId;
    }

    /**
     * Store interaction message
     */
    protected function storeInteractionMessage(
        int $interactionId,
        string $senderId,
        string $messageId,
        string $message,
        string $messageType,
        string $url = ''
    ): int {
        return ChatMessage::fromTenant($this->tenant_subdomain)->insertGetId([
            'interaction_id' => $interactionId,
            'sender_id' => $senderId,
            'message_id' => $messageId,
            'message' => $message,
            'type' => $messageType,
            'staff_id' => null,
            'status' => 'delivered',
            'time_sent' => now(),
            'ref_message_id' => '',
            'url' => $url,
            'is_read' => 0,
            'created_at' => now(),
            'updated_at' => now(),
            'tenant_id' => $this->tenant_id,
        ]);
    }

    /**
     * Trigger real-time chat notification via Pusher
     */
    protected function triggerChatNotification(int $chatId, int $messageDbId): void
    {
        try {
            $pusherSettings = get_settings_by_group('pusher');

            if (
                ! empty($pusherSettings->app_key) &&
                ! empty($pusherSettings->app_secret) &&
                ! empty($pusherSettings->app_id) &&
                ! empty($pusherSettings->cluster)
            ) {
                $pusherService = new PusherService;
                $chatData = ManageChat::newChatMessage($chatId, $messageDbId, $this->tenant_id);

                $chatData->notification = [
                    'type' => 'new_message',
                    'tenant_id' => $this->tenant_id,
                    'message_id' => $messageDbId,
                    'chat_id' => $chatId,
                    'timestamp' => now()->toISOString(),
                    'is_incoming' => true,
                ];

                $pusherService->triggerForTenant('chat', 'new-message', [
                    'chat' => $chatData,
                ], $this->tenant_id);

            }
        } catch (\Exception $e) {
            app_log('Error triggering Facebook Messenger chat notification', 'error', $e, [
                'chat_id' => $chatId,
                'message_db_id' => $messageDbId,
            ], $this->tenant_id);
        }
    }

    /**
     * Get Facebook Messenger source ID
     */
    protected function getFacebookMessengerSourceId(): int
    {
        $source = Source::where('tenant_id', $this->tenant_id)
            ->where(function ($query) {
                $query->where('name', 'Facebook Messenger')
                    ->orWhere('name', 'facebook_messenger')
                    ->orWhere('name', 'Facebook');
            })
            ->first();

        if ($source) {
            return $source->id;
        }

        // Return first available source
        return Source::where('tenant_id', $this->tenant_id)->value('id') ?? 1;
    }

    /**
     * Get tenant by Facebook page ID
     */
    protected function getTenantByPageId(string $pageId): ?array
    {
        return Cache::remember("fb_page_tenant_{$pageId}", 3600, function () use ($pageId) {
            // Query tenant_settings table to find tenant with this page_id
            // Settings are stored in 'whats-mark' group by the FacebookMessengerSettings Livewire component
            $setting = DB::table('tenant_settings')
                ->where('group', 'whats-mark')
                ->where('key', 'fb_messenger_page_id')
                ->whereRaw('JSON_UNQUOTE(value) = ?', [$pageId])
                ->first();

            if (! $setting) {
                return null;
            }

            // Get tenant information
            $tenant = DB::table('tenants')->find($setting->tenant_id ?? null);

            if (! $tenant) {
                return null;
            }

            return [
                'tenant_id' => $tenant->id,
                'subdomain' => $tenant->subdomain,
            ];
        });
    }

    /**
     * Get real user name from Facebook Graph API
     */
    protected function getRealUserName(string $senderId): string
    {
        if (! $this->messengerService) {
            return 'Facebook User '.$senderId;
        }

        $profile = $this->messengerService->getUserProfile($senderId);

        if ($profile && (! empty($profile['first_name']) || ! empty($profile['last_name']))) {
            $firstName = $profile['first_name'] ?? '';
            $lastName = $profile['last_name'] ?? '';

            return trim("{$firstName} {$lastName}") ?: 'Facebook User '.$senderId;
        }

        return 'Facebook User '.$senderId;
    }

    /**
     * Send a message via Facebook Messenger
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function send_message(Request $request, $subdomain)
    {
        $this->tenant_subdomain = $subdomain;

        try {
            // Get request data
            $id = $request->input('id', '');
            $type = $request->input('type');
            $type_id = $request->input('type_id');

            // Find existing chat/interaction
            $query = Chat::fromTenant($this->tenant_subdomain);
            if (! empty($type_id)) {
                $query->where('type', $type)
                    ->where('type_id', $type_id);
            }
            $existing_interaction = $query->where('id', $id)->first();

            if (! $existing_interaction) {
                return response()->json(['error' => 'Interaction not found'], 404);
            }

            // Verify this is a Facebook Messenger chat
            if ($existing_interaction->wa_no_id !== 'fb_messenger') {
                return response()->json(['error' => 'This is not a Facebook Messenger chat'], 400);
            }

            $this->tenant_id = $existing_interaction->tenant_id;
            $this->messengerService = new FacebookMessengerService($this->tenant_id);

            if (! $this->messengerService->isConfigured()) {
                return response()->json(['error' => 'Facebook Messenger not configured'], 400);
            }

            $to = $existing_interaction->receiver_id;
            $message = strip_tags($request->input('message', ''));

            // Parse message text for contacts or leads
            $user_id = null;
            if ($type == 'customer' || $type == 'lead') {
                $contact = Contact::fromTenant($this->tenant_subdomain)->find($type_id);
                $user_id = $contact->user_id ?? null;
            }

            $message_data = parseMessageText([
                'rel_type' => $type,
                'rel_id' => $type_id,
                'reply_text' => $message,
                'userid' => $user_id,
                'tenant_id' => $this->tenant_id,
            ]);

            $message = $message_data['reply_text'] ?? $message;
            $messageIds = [];
            $success = false;

            // Handle file attachments
            $attachments = [
                'audio' => $request->file('audio'),
                'image' => $request->file('image'),
                'video' => $request->file('video'),
                'document' => $request->file('document'),
            ];

            // Send text message if provided
            if (! empty($message)) {
                $result = $this->messengerService->sendTextMessage($to, $message);

                if ($result['success']) {
                    $success = true;
                    $messageIds[] = $result['message_id'];

                    // Store outgoing message in database
                    $storedMessageId = $this->storeOutgoingMessage(
                        $existing_interaction->id,
                        $result['message_id'],
                        $message,
                        'text'
                    );

                    // Update chat with last message
                    Chat::fromTenant($this->tenant_subdomain)->where('id', $existing_interaction->id)->update([
                        'last_message' => $message,
                        'last_msg_time' => now(),
                        'time_sent' => now(),
                    ]);

                    // Trigger real-time notification
                    $this->triggerChatNotification($existing_interaction->id, $storedMessageId);
                } else {
                    return response()->json(['error' => $result['error']], 500);
                }
            }

            // Handle file attachments
            foreach ($attachments as $mediaType => $file) {
                if (! empty($file)) {
                    $fileUrl = $this->handleAttachmentUpload($file);
                    $fullUrl = url('storage/whatsapp-attachments/'.$fileUrl);

                    $fbType = $mediaType === 'document' ? 'file' : $mediaType;
                    $result = $this->messengerService->sendAttachment($to, $fbType, $fullUrl);

                    if ($result['success']) {
                        $success = true;
                        $messageIds[] = $result['message_id'];

                        // Store outgoing message
                        $storedMessageId = $this->storeOutgoingMessage(
                            $existing_interaction->id,
                            $result['message_id'],
                            '',
                            $mediaType,
                            $fileUrl
                        );

                        // Trigger notification
                        $this->triggerChatNotification($existing_interaction->id, $storedMessageId);
                    }
                }
            }

            if (! $success && empty($message) && empty(array_filter($attachments))) {
                return response()->json(['error' => 'No message content provided'], 400);
            }

            return response()->json([
                'success' => true,
                'message_ids' => $messageIds,
                'chat_id' => $existing_interaction->id,
            ]);
        } catch (\Exception $e) {
            app_log('Error sending Facebook Messenger message', 'error', $e, [], $this->tenant_id);

            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Store outgoing message in database
     */
    protected function storeOutgoingMessage(
        int $interactionId,
        string $messageId,
        string $message,
        string $messageType,
        string $url = ''
    ): int {
        return ChatMessage::fromTenant($this->tenant_subdomain)->insertGetId([
            'interaction_id' => $interactionId,
            'sender_id' => $this->messengerService->getPageId() ?? 'page',
            'message_id' => $messageId,
            'message' => $message,
            'type' => $messageType,
            'staff_id' => Auth::id(),
            'status' => 'sent',
            'time_sent' => now(),
            'ref_message_id' => '',
            'url' => $url,
            'is_read' => 1,
            'created_at' => now(),
            'updated_at' => now(),
            'tenant_id' => $this->tenant_id,
        ]);
    }

    /**
     * Handle attachment upload
     */
    protected function handleAttachmentUpload(UploadedFile $file): string
    {
        $original = str_replace(' ', '_', $file->getClientOriginalName());
        $filename = pathinfo($original, PATHINFO_FILENAME).'_'.time().'.'.$file->extension();

        $file->storeAs('public/whatsapp-attachments', $filename);

        return $filename;
    }
}
