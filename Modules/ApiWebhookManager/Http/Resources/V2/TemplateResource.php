<?php

namespace Modules\ApiWebhookManager\Http\Resources\V2;

use Illuminate\Http\Resources\Json\JsonResource;

class TemplateResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'template_id' => $this->template_id,
            'template_name' => $this->template_name,
            'name' => $this->template_name, // Alias for backwards compatibility
            'language' => $this->language ?? 'en',
            'category' => $this->category ?? null,
            'status' => $this->status ?? 'APPROVED',

            // Header data
            'header_data_format' => $this->header_data_format,
            'header_data_text' => $this->header_data_text,
            'header_params_count' => $this->header_params_count ?? 0,
            'header_file_url' => $this->header_file_url ?? null,
            'header_variable_value' => $this->header_variable_value ?? null,

            // Body data
            'body_data' => $this->body_data,
            'body_params_count' => $this->body_params_count ?? 0,
            'body_variable_value' => $this->body_variable_value ?? null,

            // Footer data
            'footer_data' => $this->footer_data,
            'footer_params_count' => $this->footer_params_count ?? 0,

            // Buttons data
            'buttons_data' => $this->buttons_data,

            // Authentication template specific fields
            'message_send_ttl_seconds' => $this->message_send_ttl_seconds ?? null,
            'add_security_recommendation' => $this->add_security_recommendation ?? false,
            'code_expiration_minutes' => $this->code_expiration_minutes ?? null,
            'otp_button_config' => $this->otp_button_config ?? null,

            // Components (for compatibility)
            'components' => $this->components ?? [],

            // Timestamps
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
