<?php

namespace Modules\ApiWebhookManager\Models;

use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    protected $fillable = [
        'tenant_id',
        'name',
        'token',
        'scopes',
        'expires_at',
        'last_used_at',
        'last_used_ip',
        'ip_whitelist',
        'rate_limit_per_minute',
        'is_active',
    ];

    protected $hidden = [
        'token',
    ];

    protected $casts = [
        'scopes' => 'array',
        'ip_whitelist' => 'array',
        'expires_at' => 'datetime',
        'last_used_at' => 'datetime',
        'is_active' => 'boolean',
        'rate_limit_per_minute' => 'integer',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public static function generate(
        int $tenantId,
        string $name,
        array $scopes = [],
        ?int $expiresInDays = null,
        ?array $ipWhitelist = null,
        int $rateLimit = 60
    ): self {
        return self::create([
            'tenant_id' => $tenantId,
            'name' => $name,
            'token' => self::generateToken(),
            'scopes' => $scopes,
            'expires_at' => $expiresInDays ? now()->addDays($expiresInDays) : null,
            'ip_whitelist' => $ipWhitelist,
            'rate_limit_per_minute' => $rateLimit,
            'is_active' => true,
        ]);
    }

    public static function generateToken(): string
    {
        return 'wm_'.Str::random(60);
    }

    public function hasScope(string $scope): bool
    {
        if (in_array('*', $this->scopes ?? [])) {
            return true;
        }

        return in_array($scope, $this->scopes ?? []);
    }

    public function hasAnyScope(array $scopes): bool
    {
        if (in_array('*', $this->scopes ?? [])) {
            return true;
        }

        foreach ($scopes as $scope) {
            if ($this->hasScope($scope)) {
                return true;
            }
        }

        return false;
    }

    public function hasAllScopes(array $scopes): bool
    {
        if (in_array('*', $this->scopes ?? [])) {
            return true;
        }

        foreach ($scopes as $scope) {
            if (! $this->hasScope($scope)) {
                return false;
            }
        }

        return true;
    }

    public function isExpired(): bool
    {
        if (! $this->expires_at) {
            return false;
        }

        return $this->expires_at->isPast();
    }

    public function isValid(): bool
    {
        return $this->is_active && ! $this->isExpired();
    }

    public function isIpAllowed(string $ip): bool
    {
        if (empty($this->ip_whitelist)) {
            return true;
        }

        return in_array($ip, $this->ip_whitelist);
    }

    public function rotate(): self
    {
        $this->update([
            'token' => self::generateToken(),
        ]);

        return $this->fresh();
    }

    public function revoke(): bool
    {
        return $this->update(['is_active' => false]);
    }

    public function activate(): bool
    {
        return $this->update(['is_active' => true]);
    }

    public function updateLastUsed(string $ip): void
    {
        $this->update([
            'last_used_at' => now(),
            'last_used_ip' => $ip,
        ]);
    }

    public function getScopesAttribute($value): array
    {
        if (is_string($value)) {
            return json_decode($value, true) ?? [];
        }

        return $value ?? [];
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeValid($query)
    {
        return $query->where('is_active', true)
            ->where(function ($q) {
                $q->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
            });
    }

    public function scopeForTenant($query, int $tenantId)
    {
        return $query->where('tenant_id', $tenantId);
    }

    public function getPlainTextToken(): ?string
    {
        return $this->token ?? null;
    }

    public function toArrayWithToken(): array
    {
        $data = $this->toArray();
        $data['token'] = $this->token;

        return $data;
    }
}
