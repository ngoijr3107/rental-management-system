<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class LogoUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->hasPermission('settings.manage');
    }

    public function rules(): array
    {
        return [
            /*
             * SVG is deliberately excluded. An SVG can carry script, and this
             * file is served from the same origin as the app, so accepting one
             * would hand any owner a stored-XSS vector against their own team.
             * Raster formats only.
             */
            'logo' => [
                'required',
                'image',
                'mimes:png,jpg,jpeg,webp',
                'max:1024',
                'dimensions:max_width=2400,max_height=2400',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'logo.mimes' => 'Use a PNG, JPG or WEBP image. SVG files are not accepted.',
            'logo.max' => 'The logo must be smaller than 1 MB.',
        ];
    }
}
