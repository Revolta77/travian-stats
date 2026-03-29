<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class UserStatsSearchRequest extends FormRequest
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
            'x' => ['nullable', 'integer', "between:{$min},{$max}"],
            'y' => ['nullable', 'integer', "between:{$min},{$max}"],
            'page' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'account_filter' => ['sometimes', 'nullable', 'string', 'max:120'],
            'alliance_filter' => ['sometimes', 'nullable', 'string', 'max:120'],
            'player_id' => [
                'sometimes',
                'nullable',
                'integer',
                'min:1',
                Rule::exists('players', 'id')->where(function ($q): void {
                    $sid = $this->input('server_id');
                    if ($sid !== null && $sid !== '') {
                        $q->where('server_id', (int) $sid);
                    }
                }),
            ],
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
            'sort_by' => ['sometimes', 'string', Rule::in(['distance', 'account', 'population', 'villages'])],
            'sort_dir' => ['sometimes', 'string', Rule::in(['asc', 'desc'])],
        ];
    }

    public function messages(): array
    {
        return [
            'server_id.required' => 'Vyberte server.',
            'server_id.exists' => 'Server neexistuje alebo nie je aktívny.',
            'x.between' => 'Súradnica X je mimo povoleného rozsahu.',
            'y.between' => 'Súradnica Y je mimo povoleného rozsahu.',
            'page.max' => 'Stránkovanie je obmedzené na 5 strán.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $hasX = $this->filled('x');
            $hasY = $this->filled('y');
            if ($hasX xor $hasY) {
                $v->errors()->add('x', 'Zadajte obe súradnice X a Y, alebo ich nechajte prázdne.');
            }
        });
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('page')) {
            $this->merge([
                'page' => (int) $this->input('page'),
            ]);
        }
        if ($this->has('x') && $this->input('x') !== null && $this->input('x') !== '') {
            $this->merge(['x' => (int) $this->input('x')]);
        }
        if ($this->has('y') && $this->input('y') !== null && $this->input('y') !== '') {
            $this->merge(['y' => (int) $this->input('y')]);
        }
        if ($this->has('player_id') && $this->input('player_id') !== null && $this->input('player_id') !== '') {
            $this->merge(['player_id' => (int) $this->input('player_id')]);
        }
        if ($this->has('alliance_id') && $this->input('alliance_id') !== null && $this->input('alliance_id') !== '') {
            $this->merge(['alliance_id' => (int) $this->input('alliance_id')]);
        }
    }
}
