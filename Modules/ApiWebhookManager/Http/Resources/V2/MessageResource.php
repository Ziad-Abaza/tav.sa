<?php

namespace Modules\ApiWebhookManager\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

class MessageResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'message_id' => $this->message_id ?? null,
            'contact_id' => $this->contact_id,
            'phone' => $this->phone ?? null,
            'message' => $this->message,
            'type' => $this->type ?? 'text',
            'status' => $this->status ?? 'sent',
            'direction' => $this->direction ?? 'outgoing',
            'sent_at' => $this->created_at?->toIso8601String(),
            'delivered_at' => $this->delivered_at?->toIso8601String(),
            'read_at' => $this->read_at?->toIso8601String(),
            'failed_at' => $this->failed_at?->toIso8601String(),
            'error_message' => $this->error_message ?? null,
            $this->mergeWhen($this->relationLoaded('contact'), [
                'contact' => new ContactResource($this->whenLoaded('contact')),
            ]),
        ];
    }
}
