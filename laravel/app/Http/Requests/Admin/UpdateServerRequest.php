<?php

namespace App\Http\Requests\Admin;

use App\Models\Server;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateServerRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        /** @var Server $server */
        $server = $this->route('server');

        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('servers', 'slug')->ignore($server->id),
            ],
            'base_url' => ['nullable', 'string', 'max:2048'],
            'timezone' => ['required', 'string', 'max:64'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $url = $this->input('base_url');
        if (is_string($url) && trim($url) === '') {
            $this->merge(['base_url' => null]);
        }
    }
}
