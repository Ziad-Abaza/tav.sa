<?php

namespace Modules\ApiWebhookManager\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'firstname' => $this->firstname,
            'lastname' => $this->lastname,
            'company' => $this->company,
            'type' => $this->type,
            'email' => $this->email,
            'phone' => $this->phone,
            'status_id' => $this->status_id,
            'source_id' => $this->source_id,
            'description' => $this->description,
            'city' => $this->city,
            'state' => $this->state,
            'zip' => $this->zip,
            'address' => $this->address,
            'country_id' => $this->country_id,
            'assigned_id' => $this->assigned_id,
            'group_id' => $this->group_id ?? [],
            'is_opted_out' => $this->is_opted_out ?? false,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
            $this->mergeWhen($this->relationLoaded('groups'), [
                'groups' => GroupResource::collection($this->whenLoaded('groups')),
            ]),
            $this->mergeWhen($this->relationLoaded('source'), [
                'source' => new SourceResource($this->whenLoaded('source')),
            ]),
            $this->mergeWhen($this->relationLoaded('status'), [
                'status' => new StatusResource($this->whenLoaded('status')),
            ]),
        ];
    }
}
