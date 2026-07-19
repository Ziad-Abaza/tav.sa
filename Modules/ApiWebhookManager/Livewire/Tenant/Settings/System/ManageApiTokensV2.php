<?php

namespace Modules\ApiWebhookManager\Livewire\Tenant\Settings\System;

use Livewire\Component;
use Livewire\WithPagination;
use Modules\ApiWebhookManager\Enums\ApiScope;
use Modules\ApiWebhookManager\Models\ApiToken;

class ManageApiTokensV2 extends Component
{
    use WithPagination;

    public string $subdomain;

    public int $tenantId;

    public bool $showCreateModal = false;

    public bool $showViewModal = false;

    public ?int $selectedTokenId = null;

    public ?string $plainToken = null;

    // Create form fields
    public string $name = '';

    public array $selectedScopes = [];

    public ?int $expiresInDays = null;

    public array $ipWhitelist = [];

    public string $ipWhitelistInput = '';

    public int $rateLimitPerMinute = 60;

    // Edit mode
    public bool $editMode = false;

    public ?ApiToken $editingToken = null;

    protected array $rules = [
        'name' => ['required', 'string', 'max:255'],
        'selectedScopes' => ['required', 'array', 'min:1'],
        'selectedScopes.*' => ['string'],
        'expiresInDays' => ['nullable', 'integer', 'min:1', 'max:3650'],
        'ipWhitelistInput' => ['nullable', 'string'],
        'rateLimitPerMinute' => ['required', 'integer', 'min:1', 'max:10000'],
    ];

    protected array $messages = [
        'name.required' => 'Token name is required.',
        'name.max' => 'Token name cannot exceed 255 characters.',
        'selectedScopes.required' => 'At least one scope must be selected.',
        'selectedScopes.min' => 'At least one scope must be selected.',
        'rateLimitPerMinute.required' => 'Rate limit is required.',
        'rateLimitPerMinute.min' => 'Rate limit must be at least 1.',
        'rateLimitPerMinute.max' => 'Rate limit cannot exceed 10,000.',
    ];

    public function mount(): void
    {
        if (! checkPermission('system_settings.view')) {
            $this->notify(['type' => 'danger', 'message' => t('access_denied_note')], true);
            $this->redirect(route('admin.dashboard'));

            return;
        }

        $this->subdomain = request()->route('subdomain') ?? '';
        $this->tenantId = tenant_id();
    }

    public function openCreateModal(): void
    {
        $this->resetForm();
        $this->editMode = false;
        $this->showCreateModal = true;
    }

    public function closeCreateModal(): void
    {
        $this->showCreateModal = false;
        $this->resetForm();
    }

    public function openViewModal(int $tokenId): void
    {
        $this->selectedTokenId = $tokenId;
        $this->showViewModal = true;
    }

    public function closeViewModal(): void
    {
        $this->showViewModal = false;
        $this->selectedTokenId = null;
    }

    public function resetForm(): void
    {
        $this->name = '';
        $this->selectedScopes = [];
        $this->expiresInDays = null;
        $this->ipWhitelistInput = '';
        $this->ipWhitelist = [];
        $this->rateLimitPerMinute = 60;
        $this->plainToken = null;
        $this->editingToken = null;
        $this->resetErrorBag();
    }

    public function createToken(): void
    {
        if (! checkPermission('system_settings.edit')) {
            $this->notify(['type' => 'danger', 'message' => t('access_denied_note')]);

            return;
        }

        $this->validate();

        // Check for duplicate name
        $exists = ApiToken::where('tenant_id', $this->tenantId)
            ->where('name', $this->name)
            ->exists();

        if ($exists) {
            $this->addError('name', 'A token with this name already exists.');

            return;
        }

        // Parse IP whitelist
        $ipWhitelist = null;
        if (! empty($this->ipWhitelistInput)) {
            $rawIps = array_map('trim', explode(',', $this->ipWhitelistInput));
            $validIps = [];
            $invalidIps = [];

            foreach ($rawIps as $ip) {
                if (empty($ip)) {
                    continue;
                }

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $validIps[] = $ip;
                } else {
                    $invalidIps[] = $ip;
                }
            }

            if (! empty($invalidIps)) {
                $this->addError('ipWhitelistInput', 'Invalid IP addresses: '.implode(', ', $invalidIps));

                return;
            }

            if (! empty($validIps)) {
                $ipWhitelist = $validIps;
            }
        }

        $token = ApiToken::generate(
            $this->tenantId,
            $this->name,
            $this->selectedScopes,
            $this->expiresInDays,
            $ipWhitelist,
            $this->rateLimitPerMinute
        );

        $this->plainToken = $token->token;

        $this->notify([
            'type' => 'success',
            'message' => 'API token created successfully. Please copy it now - you won\'t see it again!',
        ]);

        // Don't close modal yet - show the token
    }

    public function finishCreate(): void
    {
        $this->closeCreateModal();
        $this->resetPage();
    }

    public function editToken(int $tokenId): void
    {
        $token = ApiToken::where('tenant_id', $this->tenantId)
            ->where('id', $tokenId)
            ->first();

        if (! $token) {
            $this->notify(['type' => 'danger', 'message' => 'Token not found.']);

            return;
        }

        $this->editingToken = $token;
        $this->editMode = true;
        $this->selectedTokenId = $tokenId;

        $this->name = $token->name;
        $this->selectedScopes = $token->scopes ?? [];
        $this->rateLimitPerMinute = $token->rate_limit_per_minute;
        $this->ipWhitelistInput = $token->ip_whitelist ? implode(', ', $token->ip_whitelist) : '';

        $this->showCreateModal = true;
    }

    public function updateToken(): void
    {
        if (! checkPermission('system_settings.edit') || ! $this->editingToken) {
            $this->notify(['type' => 'danger', 'message' => t('access_denied_note')]);

            return;
        }

        $this->validate();

        // Check for duplicate name (excluding current token)
        $exists = ApiToken::where('tenant_id', $this->tenantId)
            ->where('name', $this->name)
            ->where('id', '!=', $this->editingToken->id)
            ->exists();

        if ($exists) {
            $this->addError('name', 'A token with this name already exists.');

            return;
        }

        // Parse IP whitelist
        $ipWhitelist = null;
        if (! empty($this->ipWhitelistInput)) {
            $rawIps = array_map('trim', explode(',', $this->ipWhitelistInput));
            $validIps = [];
            $invalidIps = [];

            foreach ($rawIps as $ip) {
                if (empty($ip)) {
                    continue;
                }

                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    $validIps[] = $ip;
                } else {
                    $invalidIps[] = $ip;
                }
            }

            if (! empty($invalidIps)) {
                $this->addError('ipWhitelistInput', 'Invalid IP addresses: '.implode(', ', $invalidIps));

                return;
            }

            if (! empty($validIps)) {
                $ipWhitelist = $validIps;
            }
        }

        $this->editingToken->update([
            'name' => $this->name,
            'scopes' => $this->selectedScopes,
            'ip_whitelist' => $ipWhitelist,
            'rate_limit_per_minute' => $this->rateLimitPerMinute,
        ]);

        $this->notify([
            'type' => 'success',
            'message' => 'API token updated successfully.',
        ]);

        $this->closeCreateModal();
    }

    public function rotateToken(int $tokenId): void
    {
        if (! checkPermission('system_settings.edit')) {
            $this->notify(['type' => 'danger', 'message' => t('access_denied_note')]);

            return;
        }

        $token = ApiToken::where('tenant_id', $this->tenantId)
            ->where('id', $tokenId)
            ->first();

        if (! $token) {
            $this->notify(['type' => 'danger', 'message' => 'Token not found.']);

            return;
        }

        if (! $token->is_active) {
            $this->notify(['type' => 'danger', 'message' => 'Cannot rotate an inactive token.']);

            return;
        }

        $newToken = $token->rotate();
        $this->plainToken = $newToken->token;
        $this->selectedTokenId = $tokenId;
        $this->showViewModal = true;

        $this->notify([
            'type' => 'success',
            'message' => 'API token rotated successfully. Please copy the new token now!',
        ]);
    }

    public function toggleTokenStatus(int $tokenId): void
    {
        if (! checkPermission('system_settings.edit')) {
            $this->notify(['type' => 'danger', 'message' => t('access_denied_note')]);

            return;
        }

        $token = ApiToken::where('tenant_id', $this->tenantId)
            ->where('id', $tokenId)
            ->first();

        if (! $token) {
            $this->notify(['type' => 'danger', 'message' => 'Token not found.']);

            return;
        }

        if ($token->is_active) {
            $token->revoke();
            $message = 'Token revoked successfully.';
        } else {
            $token->activate();
            $message = 'Token activated successfully.';
        }

        $this->notify(['type' => 'success', 'message' => $message]);
    }

    public function deleteToken(int $tokenId): void
    {
        if (! checkPermission('system_settings.edit')) {
            $this->notify(['type' => 'danger', 'message' => t('access_denied_note')]);

            return;
        }

        $token = ApiToken::where('tenant_id', $this->tenantId)
            ->where('id', $tokenId)
            ->first();

        if (! $token) {
            $this->notify(['type' => 'danger', 'message' => 'Token not found.']);

            return;
        }

        $token->delete();

        $this->notify(['type' => 'success', 'message' => 'API token deleted successfully.']);
    }

    public function getTokensProperty()
    {
        return ApiToken::where('tenant_id', $this->tenantId)
            ->orderBy('created_at', 'desc')
            ->paginate(10);
    }

    public function getGroupedScopesProperty(): array
    {
        return ApiScope::groupedByResource();
    }

    public function render()
    {
        return view('ApiWebhookManager::livewire.tenant.settings.system.manage-api-tokens-v2', [
            'tokens' => $this->tokens,
            'groupedScopes' => $this->groupedScopes,
        ]);
    }
}
