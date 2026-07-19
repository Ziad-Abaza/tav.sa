<?php

namespace Modules\ApiWebhookManager\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

class ApiTokenResource extends JsonResource
{
    protected $showToken = false;

    public function showToken(): self
    {
        $this->showToken = true;

        return $this;
    }

    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            $this->mergeWhen($this->showToken, [
                'token' => $this->token,
            ]),
            'scopes' => $this->scopes,
            'is_active' => $this->is_active,
            'expires_at' => $this->expires_at?->toIso8601String(),
            'last_used_at' => $this->last_used_at?->toIso8601String(),
            'last_used_ip' => $this->last_used_ip,
            'rate_limit_per_minute' => $this->rate_limit_per_minute,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
