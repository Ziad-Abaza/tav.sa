<?php

namespace App\Livewire\Tenant\Bot;

use App\Models\Tenant\MessageBot;
use App\Services\FeatureService;
use Illuminate\Support\Facades\Storage;
use Livewire\Component;

class MessageBotList extends Component
{
    public $confirmingDeletion = false;

    public $messagebotId = null;

    protected $featureLimitChecker;

    protected $listeners = [
        'confirmDelete' => 'confirmDelete',
        'cloneRecord' => 'cloneRecord',
    ];

    public function mount()
    {
        if (! checkPermission(['tenant.message_bot.view'])) {
            $this->notify(['type' => 'danger', 'message' => t('access_denied_note')], true);

            return redirect()->to(tenant_route('tenant.dashboard'));
        }
    }

    public function boot(FeatureService $featureLimitChecker)
    {
        $this->featureLimitChecker = $featureLimitChecker;
    }

    public function confirmDelete($messagebotId)
    {
        $this->messagebotId = $messagebotId;
        $this->confirmingDeletion = true;
    }

    public function cloneRecord($messagebotId)
    {
        if (checkPermission('tenant.message_bot.clone')) {
            // Check feature limit before cloning
            if ($this->featureLimitChecker->hasReachedLimit('message_bots', MessageBot::class)) {
                $this->notify([
                    'type' => 'warning',
                    'message' => t('message_bot_limit_reached_upgrade_plan'),
                ]);

                return;
            }

            $existingBot = MessageBot::findOrFail($messagebotId);
            if (! $existingBot) {
                $this->notify(['type' => 'info', 'message' => t('message_bot_not_found')]);

                return false;
            }

            $oldFilePath = $existingBot->filename;
            $newFilePath = null;

            if ($oldFilePath) {
                $folderPath = 'tenant/'.tenant_id().'/message-bot';
                $originalName = pathinfo($oldFilePath, PATHINFO_BASENAME);
                $newFilePath = $originalName;

                if (Storage::disk('public')->exists($oldFilePath)) {
                    Storage::disk('public')->copy($oldFilePath, $newFilePath);
                } else {
                    $newFilePath = null;
                }
            }

            // Clone the bot and update the filename
            $cloneBot = $existingBot->replicate();
            $cloneBot->filename = $newFilePath;
            $this->featureLimitChecker->trackUsage('message_bots');
            $cloneBot->save();

            if ($cloneBot) {
                $this->notify(['type' => 'success', 'message' => t('bot_clone_successfully')], true);

                return redirect()->to(tenant_route('tenant.messagebot.create', ['messagebotId' => $cloneBot->id]));
            }
        }
    }

    public function delete()
    {
        if (checkPermission(['tenant.message_bot.delete'])) {
            $messageBot = MessageBot::findOrFail($this->messagebotId);
            $files = storage_path('/app/public/'.$messageBot->filename);
            if (is_file($files)) {
                unlink($files);
            }
            $messageBot->delete();
            $this->confirmingDeletion = false;
            $this->notify(['type' => 'success', 'message' => t('delete_message_bot_successfully')]);
            $this->dispatch('message-bot-table-refresh');
        }
    }

    public function getRemainingLimitProperty()
    {
        return $this->featureLimitChecker->getRemainingLimit('message_bots', MessageBot::class);
    }

    public function getIsUnlimitedProperty()
    {
        return $this->remainingLimit === null;
    }

    public function getHasReachedLimitProperty()
    {
        return $this->featureLimitChecker->hasReachedLimit('message_bots', MessageBot::class);
    }

    public function getTotalLimitProperty()
    {
        return $this->featureLimitChecker->getLimit('message_bots');
    }

    public function refreshTable()
    {
        $this->dispatch('message-bot-table-refresh');
    }

    public function render()
    {
        return view('livewire.tenant.bot.message-bot-list');
    }
}
