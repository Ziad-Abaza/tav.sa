<?php

namespace Modules\ApiWebhookManager\Http\Requests\V2\Message;

use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Modules\ApiWebhookManager\Enums\ErrorCode;
use Modules\ApiWebhookManager\Http\Responses\ApiResponse;

class SendTextMessageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'phone' => ['required', 'string', 'min:10', 'max:20', 'regex:/^[\d+\-\s()]+$/'],
            'message' => ['required', 'string', 'max:4096'],
            'contact_id' => ['nullable', 'integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'phone.required' => 'Phone number is required.',
            'phone.min' => 'Phone number must be at least 10 digits.',
            'phone.max' => 'Phone number cannot exceed 20 characters.',
            'phone.regex' => 'Phone number can only contain digits, +, -, spaces, and parentheses.',
            'message.required' => 'Message text is required.',
            'message.max' => 'Message cannot exceed 4096 characters.',
            'contact_id.integer' => 'Contact ID must be a valid integer.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                ErrorCode::VALIDATION_ERROR,
                'Validation failed',
                ['errors' => $validator->errors()],
                422
            )
        );
    }
}
