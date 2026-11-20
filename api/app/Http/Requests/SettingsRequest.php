<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('settings.manage');
    }

    public function rules(): array
    {
        return [
            'business_name' => ['nullable', 'string', 'max:160'],
            'business_email' => ['nullable', 'email', 'max:255'],
            'business_phone' => ['nullable', 'string', 'max:40'],
            'business_address' => ['nullable', 'string', 'max:400'],
            // ISO 4217, e.g. USD, TZS, KES, GBP
            'currency' => ['sometimes', 'string', 'size:3', 'alpha'],
            'locale' => ['sometimes', 'string', 'in:en,sw'],
            'invoice_prefix' => ['sometimes', 'string', 'max:8', 'regex:/^[A-Za-z0-9-]+$/'],
            'default_billing_day' => ['sometimes', 'integer', 'min:1', 'max:28'],
            'auto_invoice' => ['sometimes', 'boolean'],
            'collect_deposit' => ['sometimes', 'boolean'],
            'default_billing_period_months' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'notify_tenants' => ['sometimes', 'boolean'],
            'remind_days_before' => ['sometimes', 'integer', 'min:0', 'max:30'],
            'grace_days' => ['sometimes', 'integer', 'min:0', 'max:31'],
        ];
    }

    public function prepareForValidation(): void
    {
        if ($this->filled('currency')) {
            $this->merge(['currency' => strtoupper($this->input('currency'))]);
        }
    }
}
