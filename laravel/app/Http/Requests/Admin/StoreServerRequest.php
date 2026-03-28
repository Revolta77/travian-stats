<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/', 'unique:servers,slug'],
            'base_url' => ['nullable', 'string', 'max:2048'],
            'timezone' => ['required', 'string', 'max:64'],
            'is_active' => ['boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $url = $this->input('base_url');
        if (is_string($url) && trim($url) === '') {
            $this->merge(['base_url' => null]);
        }
        if (! $this->has('is_active')) {
            $this->merge(['is_active' => true]);
        }
    }
}
