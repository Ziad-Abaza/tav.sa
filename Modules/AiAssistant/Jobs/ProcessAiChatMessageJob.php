<?php

namespace Modules\AiAssistant\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use App\Models\Tenant\Chat;
use Modules\AiAssistant\Traits\AiAssistant;
use Spatie\Multitenancy\Jobs\TenantAware;
use Spatie\Multitenancy\Models\Tenant;

class ProcessAiChatMessageJob implements ShouldQueue, TenantAware
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, AiAssistant;

    protected int $chatInteractionId;
    protected string $userMessage;
    protected ?int $assistantId;

    /**
     * The tenant this job belongs to.
     *
     * @var Tenant
     */
    public $tenant;

    /**
     * Create a new job instance.
     */
    public function __construct(int $chatInteractionId, string $userMessage, ?int $assistantId = null)
    {
        $this->chatInteractionId = $chatInteractionId;
        $this->userMessage = $userMessage;
        $this->assistantId = $assistantId;
        $this->tenant = Tenant::current();

        $this->onQueue('ai_assistant');
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Find chat interaction
            $chatInteraction = Chat::find($this->chatInteractionId);
            if (! $chatInteraction) {
                Log::error('AI Chat Job: Chat interaction not found', ['id' => $this->chatInteractionId]);

                return;
            }

            if ($this->assistantId !== null) {
                // Initialize AI Chat
                $this->initializeAIChat($chatInteraction, $this->assistantId, $this->userMessage);
            } else {
                // Process existing AI Chat message
                if ($this->shouldProcessAIChat($chatInteraction, $this->userMessage)) {
                    $this->processAIMessage($chatInteraction, $this->userMessage);
                }
            }
        } catch (\Exception $e) {
            Log::error('AI Chat Job failed', [
                'chat_id' => $this->chatInteractionId,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
        }
    }
}
