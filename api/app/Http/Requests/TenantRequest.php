<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TenantRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('tenants.manage');
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';
        $tenantId = $this->route('tenant');

        return [
            'first_name' => [$required, 'string', 'max:120'],
            'last_name' => [$required, 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone' => [
                $required,
                'string',
                'max:40',
                Rule::unique('tenants', 'phone')
                    ->where('user_id', $this->user()->accountOwnerId())
                    ->ignore($tenantId),
            ],
            'national_id' => ['nullable', 'string', 'max:60'],
            'emergency_contact_name' => ['nullable', 'string', 'max:120'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
