<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class AllianceStatsSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'server_id' => [
                'required',
                'integer',
                Rule::exists('servers', 'id')->where('is_active', true),
            ],
            'page' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'tag_filter' => ['sometimes', 'nullable', 'string', 'max:120'],
            'alliance_id' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                Rule::exists('alliances', 'id')->where(function ($q): void {
                    $sid = $this->input('server_id');
                    if ($sid !== null && $sid !== '') {
                        $q->where('server_id', (int) $sid);
                    }
                }),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'server_id.required' => 'Vyberte server.',
            'server_id.exists' => 'Server neexistuje alebo nie je aktívny.',
            'page.max' => 'Stránkovanie je obmedzené na 5 strán.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('page')) {
            $this->merge([
                'page' => (int) $this->input('page'),
            ]);
        }
        if ($this->has('alliance_id') && $this->input('alliance_id') !== null && $this->input('alliance_id') !== '') {
            $this->merge(['alliance_id' => (int) $this->input('alliance_id')]);
        }
    }
}
