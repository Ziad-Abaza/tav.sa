<?php

namespace App\Models\Tenant;

use App\Models\BaseModel;
use App\Models\Tenant;
use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class FbMessengerCampaign
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property int $fb_template_id
 * @property Carbon|null $scheduled_send_time
 * @property bool $send_now
 * @property bool $pause_campaign
 * @property bool $is_sent
 * @property int $sending_count
 * @property int $total_count
 * @property bool $select_all
 * @property array|null $rel_data
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Tenant $tenant
 * @property FacebookMessengerTemplate $fbTemplate
 * @property Collection|FbMessengerCampaignDetail[] $details
 * @property-read int|null $details_count
 *
 * @method static Builder<static>|FbMessengerCampaign forTenant($tenant)
 * @method static Builder<static>|FbMessengerCampaign pending()
 * @method static Builder<static>|FbMessengerCampaign readyToSend()
 * @method static Builder<static>|FbMessengerCampaign newModelQuery()
 * @method static Builder<static>|FbMessengerCampaign newQuery()
 * @method static Builder<static>|FbMessengerCampaign query()
 *
 * @mixin \Eloquent
 */
class FbMessengerCampaign extends BaseModel
{
    use BelongsToTenant;

    protected $table = 'fb_messenger_campaigns';

    protected $fillable = [
        'tenant_id',
        'name',
        'rel_type',
        'body_params',
        'fb_template_id',
        'scheduled_send_time',
        'send_now',
        'pause_campaign',
        'is_sent',
        'sending_count',
        'total_count',
        'select_all',
        'rel_data',
        'variables_json',
        'media_url',
        'media_filename',
    ];

    protected $casts = [
        'tenant_id' => 'int',
        'fb_template_id' => 'int',
        'scheduled_send_time' => 'datetime',
        'send_now' => 'boolean',
        'pause_campaign' => 'boolean',
        'is_sent' => 'boolean',
        'sending_count' => 'int',
        'total_count' => 'int',
        'select_all' => 'boolean',
        'rel_data' => 'array',
        'variables_json' => 'array',
    ];

    /**
     * Get the FB Messenger template for this campaign
     */
    public function fbTemplate(): BelongsTo
    {
        return $this->belongsTo(FacebookMessengerTemplate::class, 'fb_template_id');
    }

    /**
     * Get the campaign details (individual message records)
     */
    public function details(): HasMany
    {
        return $this->hasMany(FbMessengerCampaignDetail::class, 'campaign_id');
    }

    /**
     * Get tenant relationship
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to get pending campaigns (not yet sent)
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('is_sent', false);
    }

    /**
     * Scope to get campaigns ready to send
     */
    public function scopeReadyToSend(Builder $query): Builder
    {
        return $query->where('is_sent', false)
            ->where('pause_campaign', false)
            ->where(function (Builder $q) {
                $q->where('send_now', true)
                    ->orWhere(function (Builder $q2) {
                        $q2->whereNotNull('scheduled_send_time')
                            ->where('scheduled_send_time', '<=', now());
                    });
            });
    }

    /**
     * Get count of pending details
     */
    public function getPendingCount(): int
    {
        return $this->details()->pending()->count();
    }

    /**
     * Get count of sent details
     */
    public function getSentCount(): int
    {
        return $this->details()->sent()->count();
    }

    /**
     * Get count of failed details
     */
    public function getFailedCount(): int
    {
        return $this->details()->failed()->count();
    }

    /**
     * Check if campaign is complete (all messages processed)
     */
    public function isComplete(): bool
    {
        return $this->details()->pending()->count() === 0;
    }

    /**
     * Get campaign status label
     */
    public function getStatusLabel(): string
    {
        if ($this->is_sent && $this->isComplete()) {
            return 'Completed';
        }

        if ($this->pause_campaign) {
            return 'Paused';
        }

        if ($this->is_sent) {
            return 'In Progress';
        }

        if ($this->send_now) {
            return 'Queued';
        }

        if ($this->scheduled_send_time) {
            return 'Scheduled';
        }

        return 'Draft';
    }

    /**
     * Get campaign status color for badges
     */
    public function getStatusColor(): string
    {
        return match ($this->getStatusLabel()) {
            'Completed' => 'success',
            'Paused' => 'warning',
            'In Progress' => 'info',
            'Queued' => 'info',
            'Scheduled' => 'secondary',
            default => 'secondary',
        };
    }
}
