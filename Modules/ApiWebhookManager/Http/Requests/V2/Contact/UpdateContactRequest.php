<?php

namespace Modules\ApiWebhookManager\Http\Requests\V2\Contact;

use App\Models\Tenant\Contact;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Validation\Rule;
use Modules\ApiWebhookManager\Enums\ErrorCode;
use Modules\ApiWebhookManager\Http\Responses\ApiResponse;

class UpdateContactRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $tenantId = $this->get('tenant_id');
        $subdomain = $this->get('tenant_subdomain');
        $contactId = $this->route('id');

        $contactTable = Contact::fromTenant($subdomain)->getTable();
        $sourceTable = (new \App\Models\Tenant\Source)->getTable();
        $statusTable = (new \App\Models\Tenant\Status)->getTable();

        return [
            'firstname' => ['sometimes', 'required', 'string', 'max:255'],
            'lastname' => ['sometimes', 'required', 'string', 'max:255'],
            'company' => ['nullable', 'string', 'max:255'],
            'type' => ['sometimes', 'required', 'in:lead,customer,guest'],
            'email' => [
                'nullable',
                'email',
                'max:191',
                Rule::unique($contactTable, 'email')
                    ->where(function ($query) use ($tenantId) {
                        return $query->where('tenant_id', $tenantId);
                    })
                    ->ignore($contactId),
            ],
            'phone' => [
                'sometimes',
                'required',
                'string',
                'max:20',
                Rule::unique($contactTable, 'phone')
                    ->where(function ($query) use ($tenantId) {
                        return $query->where('tenant_id', $tenantId);
                    })
                    ->ignore($contactId),
            ],
            'source_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists($sourceTable, 'id')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                }),
            ],
            'status_id' => [
                'sometimes',
                'required',
                'integer',
                Rule::exists($statusTable, 'id')->where(function ($query) use ($tenantId) {
                    return $query->where('tenant_id', $tenantId);
                }),
            ],
            'description' => ['nullable', 'string'],
            'city' => ['nullable', 'string', 'max:255'],
            'state' => ['nullable', 'string', 'max:255'],
            'zip' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:500'],
            'country_id' => ['nullable', 'integer'],
            'assigned_id' => ['nullable', 'integer'],
            'groups' => ['nullable', 'array'],
            'groups.*' => ['integer'],
        ];
    }

    public function messages(): array
    {
        return [
            'firstname.required' => 'First name is required.',
            'lastname.required' => 'Last name is required.',
            'phone.required' => 'Phone number is required.',
            'phone.unique' => 'This phone number already exists.',
            'email.unique' => 'This email address already exists.',
            'type.required' => 'Type is required.',
            'type.in' => 'Type must be either lead, customer, or guest.',
            'source_id.required' => 'Source is required.',
            'source_id.exists' => 'The selected source does not exist or does not belong to your account.',
            'status_id.required' => 'Status is required.',
            'status_id.exists' => 'The selected status does not exist or does not belong to your account.',
        ];
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(
            ApiResponse::error(
                ErrorCode::VALIDATION_ERROR,
                'Validation failed',
                $validator->errors()
            )
        );
    }
}
