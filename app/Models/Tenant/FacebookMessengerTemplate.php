<?php

namespace App\Models\Tenant;

use App\Models\BaseModel;
use App\Models\Tenant;
use App\Traits\BelongsToTenant;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Class FacebookMessengerTemplate
 *
 * @property int $id
 * @property int $tenant_id
 * @property string $name
 * @property string|null $description
 * @property string $content_type
 * @property string|null $media_url
 * @property string|null $media_filename
 * @property string|null $message_text
 * @property array|null $buttons
 * @property bool $is_active
 * @property int|null $copied_from_template_id
 * @property int $sending_count
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Tenant $tenant
 * @property WhatsappTemplate|null $copiedFromTemplate
 *
 * @method static Builder<static>|FacebookMessengerTemplate forTenant($tenant)
 * @method static Builder<static>|FacebookMessengerTemplate active()
 * @method static Builder<static>|FacebookMessengerTemplate newModelQuery()
 * @method static Builder<static>|FacebookMessengerTemplate newQuery()
 * @method static Builder<static>|FacebookMessengerTemplate query()
 *
 * @mixin \Eloquent
 */
class FacebookMessengerTemplate extends BaseModel
{
    use BelongsToTenant;

    public const CONTENT_TYPE_TEXT = 'text';

    public const CONTENT_TYPE_IMAGE = 'image';

    public const CONTENT_TYPE_VIDEO = 'video';

    public const CONTENT_TYPE_DOCUMENT = 'document';

    public const CONTENT_TYPE_AUDIO = 'audio';

    public const BUTTON_TYPE_POSTBACK = 'postback';

    public const BUTTON_TYPE_URL = 'web_url';

    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'content_type',
        'media_url',
        'media_filename',
        'message_text',
        'buttons',
        'is_active',
        'copied_from_template_id',
        'sending_count',
    ];

    protected $casts = [
        'tenant_id' => 'int',
        'buttons' => 'array',
        'is_active' => 'boolean',
        'copied_from_template_id' => 'int',
        'sending_count' => 'int',
    ];

    /**
     * Content type options for forms
     */
    public static function contentTypes(): array
    {
        return [
            self::CONTENT_TYPE_TEXT => 'Text',
            self::CONTENT_TYPE_IMAGE => 'Image',
            self::CONTENT_TYPE_VIDEO => 'Video',
            self::CONTENT_TYPE_DOCUMENT => 'Document',
            self::CONTENT_TYPE_AUDIO => 'Audio',
        ];
    }

    /**
     * Scope to only active templates
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /**
     * Get the WhatsApp template this was copied from
     */
    public function copiedFromTemplate(): BelongsTo
    {
        return $this->belongsTo(WhatsappTemplate::class, 'copied_from_template_id');
    }

    /**
     * Get tenant relationship
     */
    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    /**
     * Replace merge fields in message text with contact data
     */
    public function getMergedMessage(?Contact $contact = null): string
    {
        if (empty($this->message_text)) {
            return '';
        }

        $message = $this->message_text;

        if ($contact) {
            $replacements = [
                '{{first_name}}' => $contact->firstname ?? '',
                '{{last_name}}' => $contact->lastname ?? '',
                '{{full_name}}' => trim(($contact->firstname ?? '').' '.($contact->lastname ?? '')),
                '{{company}}' => $contact->company ?? '',
                '{{email}}' => $contact->email ?? '',
                '{{phone}}' => $contact->phone ?? '',
                '{{city}}' => $contact->city ?? '',
                '{{state}}' => $contact->state ?? '',
                '{{country}}' => $contact->country_name ?? '',
                '{{address}}' => $contact->address ?? '',
            ];

            $message = str_replace(array_keys($replacements), array_values($replacements), $message);
        }

        return $message;
    }

    /**
     * Check if template has media
     */
    public function hasMedia(): bool
    {
        return $this->content_type !== self::CONTENT_TYPE_TEXT && ! empty($this->media_url);
    }

    /**
     * Check if template has buttons
     */
    public function hasButtons(): bool
    {
        return ! empty($this->buttons) && is_array($this->buttons) && count($this->buttons) > 0;
    }

    /**
     * Get button count
     */
    public function getButtonCount(): int
    {
        return $this->hasButtons() ? count($this->buttons) : 0;
    }

    /**
     * Get campaigns that use this template
     */
    public function campaigns(): HasMany
    {
        return $this->hasMany(FbMessengerCampaign::class, 'fb_template_id');
    }

    /**
     * Get total sent count across all campaigns using this template
     */
    public function getTotalSentCount(): int
    {
        return FbMessengerCampaignDetail::whereHas('campaign', function (Builder $query): void {
            $query->where('fb_template_id', $this->id);
        })->sent()->count();
    }

    /**
     * Increment sending count
     */
    public function incrementSendingCount(): void
    {
        $this->increment('sending_count');
    }
}
