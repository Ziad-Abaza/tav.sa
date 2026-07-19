<?php

namespace App\Livewire\Tenant\Campaign;

use App\Models\Tenant\Campaign;
use App\Services\FeatureService;
use Livewire\Component;

class CampaignList extends Component
{
    public $campaign_id = null;

    public $confirmingDeletion = false;

    protected $listeners = [
        'confirmDelete' => 'confirmDelete',
    ];

    protected $featureLimitChecker;

    public function mount()
    {
        if (! checkPermission('tenant.campaigns.view')) {
            $this->notify(['type' => 'danger', 'message' => t('access_denied_note')], true);

            return redirect()->to(tenant_route('tenant.dashboard'));
        }
    }

    public function boot(FeatureService $featureLimitChecker)
    {
        $this->featureLimitChecker = $featureLimitChecker;
    }

    public function confirmDelete($campaignId)
    {
        $this->campaign_id = $campaignId;
        $this->confirmingDeletion = true;
    }

    public function delete()
    {
        if (checkPermission('tenant.campaigns.delete')) {
            try {
                $campaign = Campaign::where('tenant_id', tenant_id())
                    ->findOrFail($this->campaign_id);

                // Delete associated file if exists
                if ($campaign->filename) {
                    \Illuminate\Support\Facades\Storage::disk('public')->delete($campaign->filename);
                }

                // Delete carousel media files if they exist
                if ($campaign->cards_params && is_array($campaign->cards_params)) {
                    foreach ($campaign->cards_params as $card) {
                        if (! empty($card['components']) && is_array($card['components'])) {
                            foreach ($card['components'] as $component) {
                                if ($component['type'] === 'HEADER' &&
                                    isset($component['example']['header_handle']) &&
                                    is_array($component['example']['header_handle'])) {

                                    foreach ($component['example']['header_handle'] as $mediaUrl) {
                                        if (! empty($mediaUrl)) {
                                            $relativePath = str_replace([
                                                url('storage').'/',
                                                url('/storage/'),
                                                config('app.url').'/storage/',
                                            ], '', $mediaUrl);

                                            if (\Illuminate\Support\Facades\Storage::disk('public')->exists($relativePath)) {
                                                \Illuminate\Support\Facades\Storage::disk('public')->delete($relativePath);
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }
                }

                // Delete campaign (cascade will handle campaign_details)
                $campaign->delete();

                $this->confirmingDeletion = false;
                $this->notify(['type' => 'success', 'message' => t('campaign_deleted_successfully')]);
                $this->dispatch('campaign-table-refresh');
            } catch (\Exception $e) {
                app_log('Campaign deletion failed', 'error', $e, [
                    'campaign_id' => $this->campaign_id,
                    'error' => $e->getMessage(),
                    'user_id' => auth()->id(),
                    'tenant_id' => tenant_id(),
                ]);

                $this->confirmingDeletion = false;
                $this->notify(['type' => 'danger', 'message' => t('campaign_delete_failed').': '.$e->getMessage()]);
            }
        }
    }

    public function getRemainingLimitProperty()
    {
        return $this->featureLimitChecker->getRemainingLimit('campaigns', Campaign::class);
    }

    public function getIsUnlimitedProperty()
    {
        return $this->remainingLimit === null;
    }

    public function getHasReachedLimitProperty()
    {
        return $this->featureLimitChecker->hasReachedLimit('campaigns', Campaign::class);
    }

    public function getTotalLimitProperty()
    {
        return $this->featureLimitChecker->getLimit('campaigns');
    }

    public function refreshTable()
    {
        $this->dispatch('campaign-table-refresh');
    }

    public function render()
    {
        return view('livewire.tenant.campaign.campaign-list');
    }
}
