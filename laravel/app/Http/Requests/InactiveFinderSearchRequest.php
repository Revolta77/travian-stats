<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class InactiveFinderSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $min = (int) config('travian.map.coordinate_min', -400);
        $max = (int) config('travian.map.coordinate_max', 400);

        return [
            'server_id' => [
                'required',
                'integer',
                Rule::exists('servers', 'id')->where('is_active', true),
            ],
            'x' => ['required', 'integer', "between:{$min},{$max}"],
            'y' => ['required', 'integer', "between:{$min},{$max}"],
            'page' => ['sometimes', 'integer', 'min:1', 'max:5'],
        ];
    }

    public function messages(): array
    {
        return [
            'server_id.required' => 'Vyberte server.',
            'server_id.exists' => 'Server neexistuje alebo nie je aktívny.',
            'x.required' => 'Zadajte súradnicu X.',
            'y.required' => 'Zadajte súradnicu Y.',
            'x.between' => 'Súradnica X je mimo povoleného rozsahu.',
            'y.between' => 'Súradnica Y je mimo povoleného rozsahu.',
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
    }
}
