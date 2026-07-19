<?php

namespace Modules\ApiWebhookManager\Models;

use App\Traits\BelongsToTenant;
use Illuminate\Database\Eloquent\Model;

class OtpVerification extends Model
{
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'phone',
        'code',
        'purpose',
        'template_name',
        'expiry_minutes',
        'expires_at',
        'is_verified',
        'verified_at',
        'verification_attempts',
        'max_attempts',
        'ip_address',
        'user_agent',
        'metadata',
    ];

    protected $casts = [
        'expires_at' => 'datetime',
        'verified_at' => 'datetime',
        'is_verified' => 'boolean',
        'metadata' => 'array',
        'verification_attempts' => 'integer',
        'max_attempts' => 'integer',
        'expiry_minutes' => 'integer',
    ];

    /**
     * Check if OTP is expired
     */
    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }

    /**
     * Check if OTP is still valid (not expired and not verified)
     */
    public function isValid(): bool
    {
        return ! $this->is_verified && ! $this->isExpired();
    }

    /**
     * Check if max verification attempts reached
     */
    public function hasReachedMaxAttempts(): bool
    {
        return $this->verification_attempts >= $this->max_attempts;
    }

    /**
     * Increment verification attempts
     */
    public function incrementAttempts(): void
    {
        $this->increment('verification_attempts');
    }

    /**
     * Mark OTP as verified
     */
    public function markAsVerified(): bool
    {
        return $this->update([
            'is_verified' => true,
            'verified_at' => now(),
        ]);
    }

    /**
     * Scope to get active OTP for phone and purpose
     */
    public function scopeActiveFor($query, int $tenantId, string $phone, string $purpose)
    {
        return $query->where('tenant_id', $tenantId)
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->where('is_verified', false)
            ->where('expires_at', '>', now())
            ->latest();
    }

    /**
     * Scope to clean expired OTPs
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<', now())
            ->orWhere('is_verified', true);
    }

    /**
     * Generate a random OTP code
     */
    public static function generateCode(int $length = 6): string
    {
        return str_pad((string) random_int(0, (int) str_repeat('9', $length)), $length, '0', STR_PAD_LEFT);
    }

    /**
     * Create new OTP verification
     */
    public static function createOtp(
        int $tenantId,
        string $phone,
        string $code,
        string $purpose,
        int $expiryMinutes = 10,
        ?string $templateName = null,
        int $maxAttempts = 5,
        ?array $metadata = null
    ): self {
        // Delete any existing OTPs for this phone and purpose to avoid unique constraint violation
        // This removes both verified and unverified OTPs
        self::where('tenant_id', $tenantId)
            ->where('phone', $phone)
            ->where('purpose', $purpose)
            ->delete();

        return self::create([
            'tenant_id' => $tenantId,
            'phone' => $phone,
            'code' => $code,
            'purpose' => $purpose,
            'template_name' => $templateName,
            'expiry_minutes' => $expiryMinutes,
            'expires_at' => now()->addMinutes($expiryMinutes),
            'max_attempts' => $maxAttempts,
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'metadata' => $metadata,
        ]);
    }

    /**
     * Verify OTP code
     */
    public static function verify(
        int $tenantId,
        string $phone,
        string $code,
        string $purpose
    ): array {
        $otp = self::activeFor($tenantId, $phone, $purpose)->first();

        if (! $otp) {
            return [
                'success' => false,
                'message' => 'No active OTP found for this phone number.',
                'error_code' => 'OTP_NOT_FOUND',
            ];
        }

        // Check if max attempts reached
        if ($otp->hasReachedMaxAttempts()) {
            return [
                'success' => false,
                'message' => 'Maximum verification attempts exceeded. Please request a new OTP.',
                'error_code' => 'MAX_ATTEMPTS_EXCEEDED',
                'attempts_remaining' => 0,
            ];
        }

        // Check if expired
        if ($otp->isExpired()) {
            return [
                'success' => false,
                'message' => 'OTP has expired. Please request a new one.',
                'error_code' => 'OTP_EXPIRED',
                'expired_at' => $otp->expires_at->toIso8601String(),
            ];
        }

        // Increment attempts
        $otp->incrementAttempts();

        // Verify code
        if ($otp->code !== $code) {
            $attemptsRemaining = $otp->max_attempts - $otp->verification_attempts;

            return [
                'success' => false,
                'message' => 'Invalid OTP code.',
                'error_code' => 'INVALID_CODE',
                'attempts_remaining' => $attemptsRemaining,
            ];
        }

        // Mark as verified
        $otp->markAsVerified();

        return [
            'success' => true,
            'message' => 'OTP verified successfully.',
            'data' => [
                'phone' => $otp->phone,
                'purpose' => $otp->purpose,
                'verified_at' => $otp->verified_at->toIso8601String(),
            ],
        ];
    }
}
