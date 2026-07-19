<?php

namespace App\Http\Requests\Tenant;

use App\Models\Tenant\FacebookMessengerTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class FacebookMessengerTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Handle authorization in middleware or gates
    }

    public function rules(): array
    {
        $rules = [
            'name' => 'required|string|max:255',
            'description' => 'nullable|string|max:1000',
            'content_type' => [
                'required',
                Rule::in(array_keys(FacebookMessengerTemplate::contentTypes())),
            ],
            'message_text' => 'nullable|string|max:2000',
            'media' => 'nullable|file|max:25600', // 25MB max
            'media_url' => 'nullable|string|url|max:2048',
            'remove_media' => 'nullable|boolean',
            'buttons' => 'nullable',
            'is_active' => 'nullable|boolean',
        ];

        // Require message_text or media based on content_type
        if ($this->content_type === 'text') {
            $rules['message_text'] = 'required|string|max:2000';
        }

        return $rules;
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Template name is required.',
            'name.max' => 'Template name cannot exceed 255 characters.',
            'content_type.required' => 'Please select a content type.',
            'content_type.in' => 'Invalid content type selected.',
            'message_text.required' => 'Message text is required for text templates.',
            'message_text.max' => 'Message text cannot exceed 2000 characters.',
            'media.max' => 'Media file cannot exceed 25MB.',
            'media.file' => 'Invalid media file.',
        ];
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        // Parse buttons if it's a JSON string
        if ($this->has('buttons') && is_string($this->buttons)) {
            $decoded = json_decode($this->buttons, true);
            if (json_last_error() === JSON_ERROR_NONE) {
                $this->merge(['buttons' => $decoded]);
            }
        }

        // Validate button structure
        if ($this->has('buttons') && is_array($this->buttons)) {
            $validatedButtons = [];
            foreach ($this->buttons as $index => $button) {
                if (! isset($button['type']) || ! isset($button['title'])) {
                    continue;
                }

                // Enforce title length limit (20 chars for quick replies)
                $title = substr($button['title'] ?? '', 0, 20);

                if ($button['type'] === 'postback') {
                    $validatedButtons[] = [
                        'type' => 'postback',
                        'title' => $title,
                        'payload' => $button['payload'] ?? strtoupper(str_replace(' ', '_', $title)),
                    ];
                } elseif ($button['type'] === 'web_url' && ! empty($button['url'])) {
                    $validatedButtons[] = [
                        'type' => 'web_url',
                        'title' => $title,
                        'url' => $button['url'],
                    ];
                }

                // Max 13 quick reply buttons
                if (count($validatedButtons) >= 13) {
                    break;
                }
            }

            $this->merge(['buttons' => $validatedButtons]);
        }
    }
}
