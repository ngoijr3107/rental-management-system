<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UnitPhotoRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('properties.manage');
    }

    public function rules(): array
    {
        return [
            // Several at once — photographing a flat means five or six shots,
            // and uploading them one by one is tedious on a phone.
            'photos' => ['required', 'array', 'max:10'],
            'photos.*' => [
                'image',
                // SVG excluded for the same reason as the logo: it can carry
                // script and is served from the app's own origin.
                'mimes:png,jpg,jpeg,webp',
                'max:4096',
                'dimensions:max_width=6000,max_height=6000',
            ],
            'caption' => ['nullable', 'string', 'max:120'],
        ];
    }

    public function messages(): array
    {
        return [
            'photos.*.mimes' => 'Photos must be PNG, JPG or WEBP.',
            'photos.*.max' => 'Each photo must be smaller than 4 MB.',
        ];
    }
}
