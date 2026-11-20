<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateLeaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('leases.manage');
    }

    public function rules(): array
    {
        return [
            // Compared against the lease's own start date in the controller.
            'end_date' => ['nullable', 'date'],
            'rent_amount' => ['sometimes', 'numeric', 'min:0'],
            'deposit_amount' => ['sometimes', 'numeric', 'min:0'],
            'billing_day' => ['sometimes', 'integer', 'min:1', 'max:28'],
            'billing_period_months' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
