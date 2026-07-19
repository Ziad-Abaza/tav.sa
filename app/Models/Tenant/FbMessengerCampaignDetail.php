<?php

namespace App\Models\Tenant;

use App\Models\BaseModel;
use App\Models\Tenant;
use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Class FbMessengerCampaignDetail
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $campaign_id
 * @property int $contact_id
 * @property string $facebook_psid
 * @property string|null $message_text
 * @property int $status 1=pending, 2=sent, 0=failed
 * @property string $message_status
 * @property string|null $fb_message_id
 * @property string|null $response_message
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Tenant $tenant
 * @property FbMessengerCampaign $campaign
 *
 * @method static Builder<static>|FbMessengerCampaignDetail forTenant($tenant)
 * @method static Builder<static>|FbMessengerCampaignDetail pending()
 * @method static Builder<static>|FbMessengerCampaignDetail sent()
 * @method static Builder<static>|FbMessengerCampaignDetail failed()
 * @method static Builder<static>|FbMessengerCampaignDetail newModelQuery()
 * @method static Builder<static>|FbMessengerCampaignDetail newQuery()
 * @method static Builder<static>|FbMessengerCampaignDetail query()
 *
 * @mixin \Eloquent
 */
class FbMessengerCampaignDetail extends BaseModel
{
    use BelongsToTenant;

    public const STATUS_FAILED = 0;

    public const STATUS_PENDING = 1;

    public const STATUS_SENT = 2;

    protected $table = 'fb_messenger_campaign_details';

    protected $fillable = [
        'tenant_id',
        'campaign_id',
        'contact_id',
        'facebook_psid',
        'message_text',
        'status',
        'message_status',
        'fb_message_id',
        'response_message',
    ];

    protected $casts = [
        'tenant_id' => 'int',
        'campaign_id' => 'int',
        'contact_id' => 'int',
        'status' => 'int',
    ];

    /**
     * Get the campaign
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(FbMessengerCampaign::class, 'campaign_id');
    }

    /**
     * Get tenant relationship
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Scope to get pending details only
     */
    public function scopePending(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_PENDING);
    }

    /**
     * Scope to get sent details only
     */
    public function scopeSent(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_SENT);
    }

    /**
     * Scope to get failed details only
     */
    public function scopeFailed(Builder $query): Builder
    {
        return $query->where('status', self::STATUS_FAILED);
    }

    /**
     * Mark as sent
     */
    public function markAsSent(?string $fbMessageId = null, string $messageStatus = 'sent'): void
    {
        $this->update([
            'status' => self::STATUS_SENT,
            'message_status' => $messageStatus,
            'fb_message_id' => $fbMessageId,
        ]);
    }

    /**
     * Mark as failed
     */
    public function markAsFailed(string $errorMessage): void
    {
        $this->update([
            'status' => self::STATUS_FAILED,
            'message_status' => 'failed',
            'response_message' => $errorMessage,
        ]);
    }

    /**
     * Get status label
     */
    public function getStatusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pending',
            self::STATUS_SENT => 'Sent',
            self::STATUS_FAILED => 'Failed',
            default => 'Unknown',
        };
    }

    /**
     * Get status color for badges
     */
    public function getStatusColor(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'warning',
            self::STATUS_SENT => 'success',
            self::STATUS_FAILED => 'danger',
            default => 'secondary',
        };
    }
}
