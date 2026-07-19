<?php

namespace App\Livewire\Tenant\FlowBot;

use App\Models\Tenant\BotFlow;
use App\Rules\PurifiedInput;
use App\Services\FeatureService;
use Livewire\Component;
use Livewire\WithFileUploads;
use Livewire\WithPagination;

class FlowList extends Component
{
    use WithFileUploads, WithPagination;

    public BotFlow $botFlow;

    public $showFlowModal = false;

    public $showImportModal = false;

    public $confirmingDeletion = false;

    public $confirmCloneModal = false;

    public $importFile;

    protected $featureLimitChecker;

    public $botFlowId = null;

    protected $listeners = [
        'editFlow' => 'editFlow',
        'confirmDelete' => 'confirmDelete',
        'editRedirect' => 'editRedirect',
        'export-flow' => 'handleExportFlow',
        'confirmClone' => 'confirmClone',
    ];

    public $tenant_id;

    public function mount()
    {
        if (! checkPermission(['tenant.bot_flow.view', 'tenant.bot_flow.create'])) {
            $this->notify(['type' => 'danger', 'message' => t('access_denied_note')], true);

            return redirect(tenant_route('tenant.dashboard'));
        }
        $this->resetForm();
        $this->botFlow = new BotFlow;
        $this->tenant_id = tenant_id();
    }

    public function boot(FeatureService $featureLimitChecker)
    {
        $this->featureLimitChecker = $featureLimitChecker;
    }

    protected function rules()
    {
        return [
            'botFlow.name' => [
                'required',
                'unique:sources,name,'.($this->botFlow->id ?? 'NULL'),
                new PurifiedInput(t('sql_injection_error')),
                'max:150',
            ],
            'botFlow.description' => [
                'nullable',
                new PurifiedInput(t('sql_injection_error')),
                'max:150',
            ],
        ];
    }

    public function createBotFlow()
    {
        $this->resetForm();
        $this->showFlowModal = true;
    }

    public function save()
    {
        $this->validate();

        $isNew = ! $this->botFlow->exists;

        // For new records, check if creating one more would exceed the limit
        if ($isNew) {
            $limit = $this->featureLimitChecker->getLimit('bot_flow');

            // Skip limit check if unlimited (-1) or no limit set (null)
            if ($limit !== null && $limit !== -1) {
                $currentCount = BotFlow::where('tenant_id', tenant_id())->count();

                if ($currentCount >= $limit) {
                    $this->showFlowModal = false;
                    // Show upgrade notification
                    $this->notify([
                        'type' => 'warning',
                        'message' => t('bot_flow_limit_reached_message'),
                    ]);

                    return;
                }
            }
        }

        if ($this->botFlow->isDirty()) {
            $this->botFlow->tenant_id = tenant_id();
            if ($isNew) {
                // Only set flow_data to null for completely new flows
                // This ensures new flows start with empty flow data
                $this->botFlow->flow_data = null;
            }
            // For existing flows, preserve the existing flow_data
            $this->botFlow->save();

            if ($isNew) {
                $this->featureLimitChecker->trackUsage('bot_flow');
            }

            $this->showFlowModal = false;

            $message = $this->botFlow->wasRecentlyCreated
                ? t('bot_flow_saved_successfully')
                : t('bot_flow_update_successfully');

            $this->notify(['type' => 'success', 'message' => $message]);
            $this->dispatch('flow-bot-table-refresh');
        } else {
            $this->showFlowModal = false;
        }
    }

    public function confirmDelete($flowId)
    {
        $this->botFlowId = $flowId;
        $this->confirmingDeletion = true;
    }

    public function confirmClone($flowId)
    {
        $this->botFlowId = $flowId;
        $this->confirmCloneModal = true;
    }

    public function delete()
    {
        $botFlow = BotFlow::find($this->botFlowId);

        if ($botFlow) {
            $botFlow->delete();
        }

        $this->confirmingDeletion = false;
        $this->resetForm();
        $this->botFlowId = null;
        $this->resetPage();

        $this->notify(['type' => 'success', 'message' => t('flow_delete_successfully')]);
        $this->dispatch('flow-bot-table-refresh');
    }

    public function cloneFlow()
    {
        if (checkPermission('tenant.message_bot.clone')) {
            // Check feature limit before cloning
            if ($this->featureLimitChecker->hasReachedLimit('bot_flow', BotFlow::class)) {
                $this->notify([
                    'type' => 'warning',
                    'message' => t('bot_flow_limit_reached_message'),
                ]);

                return;
            }

            $existingBot = BotFlow::findOrFail($this->botFlowId);

            if (! $existingBot) {
                $this->notify(['type' => 'info', 'message' => t('flow_not_found')]);

                return false;
            }

            $existingBot->is_active = false;

            // Clone the bot and update the filename
            $cloneBot = $existingBot->replicate();

            // CRITICAL: Regenerate node IDs to prevent button routing conflicts
            // Without this, cloned flows share node IDs with originals, causing
            // button clicks on cloned flows to route to original flow's messages
            $cloneBot->flow_data = $this->regenerateFlowNodeIds($existingBot->flow_data);

            $this->featureLimitChecker->trackUsage('bot_flow');
            $cloneBot->save();

            if ($cloneBot) {
                $this->confirmingDeletion = false;
                $this->resetForm();
                $this->botFlowId = null;
                $this->resetPage();

                $this->notify(['type' => 'success', 'message' => t('flow_cloned_successfully')]);
                $this->dispatch('flow-bot-table-refresh');

            }
        }
    }

    public function editRedirect($flowId)
    {
        return redirect()->to(tenant_route('tenant.bot-flows.edit', [
            'id' => $flowId,
        ]));
    }

    public function editFlow($flowId)
    {
        $source = BotFlow::findOrFail($flowId);
        $this->botFlow = $source;
        $this->resetValidation();
        $this->showFlowModal = true;
    }

    private function resetForm()
    {
        $this->reset();
        $this->resetValidation();
        $this->botFlow = new BotFlow;
    }

    public function getRemainingLimitProperty()
    {
        return $this->featureLimitChecker->getRemainingLimit('bot_flow', BotFlow::class);
    }

    public function getIsUnlimitedProperty()
    {
        return $this->remainingLimit === null;
    }

    public function getHasReachedLimitProperty()
    {
        return $this->featureLimitChecker->hasReachedLimit('bot_flow', BotFlow::class);
    }

    public function getTotalLimitProperty()
    {
        return $this->featureLimitChecker->getLimit('bot_flow');
    }

    public function openImportModal(): void
    {
        $this->reset(['importFile']);
        $this->resetValidation();
        $this->showImportModal = true;
    }

    public function importFlow(): void
    {
        $this->validate([
            'importFile' => 'required|file|mimes:json|max:2048',
        ]);

        try {
            // Read and decode JSON file
            $jsonContent = file_get_contents($this->importFile->getRealPath());
            $flowData = json_decode($jsonContent, true);

            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->notify([
                    'type' => 'error',
                    'message' => t('invalid_json_file'),
                ]);

                return;
            }

            // Validate required fields
            if (! isset($flowData['name']) || ! isset($flowData['flow_data'])) {
                $this->notify([
                    'type' => 'error',
                    'message' => t('invalid_flow_format'),
                ]);

                return;
            }

            // Check feature limit before importing
            $limit = $this->featureLimitChecker->getLimit('bot_flow');

            if ($limit !== null && $limit !== -1) {
                $currentCount = BotFlow::where('tenant_id', tenant_id())->count();

                if ($currentCount >= $limit) {
                    $this->showImportModal = false;
                    $this->notify([
                        'type' => 'warning',
                        'message' => t('bot_flow_limit_reached_message'),
                    ]);

                    return;
                }
            }

            // Check if flow name already exists, if so, append timestamp
            $name = $flowData['name'];
            $existingFlow = BotFlow::where('tenant_id', tenant_id())
                ->where('name', $name)
                ->first();

            if ($existingFlow) {
                $name = $name.' ('.now()->format('Y-m-d H:i').')';
            }

            // Regenerate node IDs to prevent conflicts with existing flows
            // This ensures imported flows don't share node IDs with any existing flow
            $regeneratedFlowData = $this->regenerateFlowNodeIds(json_encode($flowData['flow_data']));

            // Create new flow
            $flow = BotFlow::create([
                'tenant_id' => tenant_id(),
                'name' => $name,
                'description' => $flowData['description'] ?? '',
                'flow_data' => $regeneratedFlowData,
                'is_active' => $flowData['is_active'] ?? false,
            ]);

            // Track usage for new flow
            $this->featureLimitChecker->trackUsage('bot_flow');

            $this->showImportModal = false;
            $this->reset(['importFile']);

            $this->notify([
                'type' => 'success',
                'message' => t('flow_imported_successfully'),
            ]);

            $this->dispatch('flow-bot-table-refresh');
        } catch (\Exception $e) {
            $this->notify([
                'type' => 'error',
                'message' => t('flow_import_failed'),
            ]);
        }
    }

    public function handleExportFlow(int $flowId): void
    {
        $exportUrl = tenant_route('tenant.bot_flows.export', ['id' => $flowId]);
        $this->dispatch('download-flow', url: $exportUrl);
    }

    /**
     * Regenerate all node IDs in flow data to prevent collisions between flows.
     * This is critical for cloning and importing flows - without unique node IDs,
     * button responses will route to the wrong flow.
     *
     * @param  string|null  $flowData  JSON-encoded flow data
     * @return string|null JSON-encoded flow data with new unique node IDs
     */
    protected function regenerateFlowNodeIds(?string $flowData): ?string
    {
        if (empty($flowData)) {
            return null;
        }

        $data = json_decode($flowData, true);
        if (json_last_error() !== JSON_ERROR_NONE || empty($data)) {
            return $flowData;
        }

        // Create mapping of old node ID to new unique ID
        $idMap = [];
        $baseTimestamp = intval(microtime(true) * 1000);

        // Process nodes - generate new unique IDs
        if (isset($data['nodes']) && is_array($data['nodes'])) {
            foreach ($data['nodes'] as $index => &$node) {
                if (! isset($node['id'])) {
                    continue;
                }

                $oldId = $node['id'];
                // Generate unique ID: base timestamp + index + random suffix
                // This matches the frontend format (timestamp string)
                $newId = (string) ($baseTimestamp + $index * 10 + rand(1, 9));
                $idMap[$oldId] = $newId;
                $node['id'] = $newId;
            }
            unset($node); // Break reference
        }

        // Process edges - update source and target references
        if (isset($data['edges']) && is_array($data['edges'])) {
            foreach ($data['edges'] as &$edge) {
                // Update source node reference
                if (isset($edge['source']) && isset($idMap[$edge['source']])) {
                    $edge['source'] = $idMap[$edge['source']];
                }
                // Update target node reference
                if (isset($edge['target']) && isset($idMap[$edge['target']])) {
                    $edge['target'] = $idMap[$edge['target']];
                }
                // Update edge ID if it contains node references
                // Sort by key length (longest first) to avoid partial replacements
                // e.g., "17512672596951" must be replaced before "1751267259695"
                if (isset($edge['id'])) {
                    $sortedIdMap = $idMap;
                    uksort($sortedIdMap, fn ($a, $b) => strlen($b) - strlen($a));
                    foreach ($sortedIdMap as $oldId => $newId) {
                        $edge['id'] = str_replace($oldId, $newId, $edge['id']);
                    }
                }
            }
            unset($edge); // Break reference
        }

        return json_encode($data);
    }

    public function render()
    {
        return view('livewire.tenant.flow-bot.flow-list');
    }
}
