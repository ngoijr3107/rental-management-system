<?php

namespace App\Http\Requests;

use App\Enums\UnitStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UnitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('properties.manage');
    }

    public function rules(): array
    {
        $required = $this->isMethod('POST') ? 'required' : 'sometimes';

        return [
            'property_id' => [$required, 'integer', 'exists:properties,id'],
            'label' => [$required, 'string', 'max:60'],
            'bedrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'bathrooms' => ['nullable', 'integer', 'min:0', 'max:50'],
            'size_sqm' => ['nullable', 'numeric', 'min:0'],
            'rent_amount' => [$required, 'numeric', 'min:0'],
            'deposit_amount' => ['nullable', 'numeric', 'min:0'],
            'status' => ['sometimes', Rule::in(UnitStatus::values())],
            'description' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
