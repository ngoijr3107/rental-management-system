<?php

namespace App\Http\Requests;

use App\Enums\MaintenancePriority;
use App\Enums\MaintenanceStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MaintenanceRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('maintenance.manage');
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'unit_id' => [$required, 'integer', 'exists:units,id'],
            'tenant_id' => ['nullable', 'integer', 'exists:tenants,id'],
            'title' => [$required, 'string', 'max:180'],
            'description' => ['nullable', 'string', 'max:2000'],
            'priority' => ['sometimes', Rule::in(MaintenancePriority::values())],
            'status' => ['sometimes', Rule::in(MaintenanceStatus::values())],
            'cost' => ['nullable', 'numeric', 'min:0'],
            'reported_at' => ['nullable', 'date'],
        ];
    }
}
