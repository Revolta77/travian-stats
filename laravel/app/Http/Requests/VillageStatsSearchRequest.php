<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class VillageStatsSearchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $min = (int) config('travian.map.coordinate_min', -400);
        $max = (int) config('travian.map.coordinate_max', 400);
        $tribeIds = array_keys(config('travian.tribes', []));

        return [
            'server_id' => [
                'required',
                'integer',
                Rule::exists('servers', 'id')->where('is_active', true),
            ],
            'x' => ['required', 'integer', "between:{$min},{$max}"],
            'y' => ['required', 'integer', "between:{$min},{$max}"],
            'page' => ['sometimes', 'integer', 'min:1', 'max:5'],
            'account_filter' => ['sometimes', 'nullable', 'string', 'max:120'],
            'alliance_filter' => ['sometimes', 'nullable', 'string', 'max:120'],
            'village_filter' => ['sometimes', 'nullable', 'string', 'max:120'],
            'tribe' => ['sometimes', 'nullable', 'integer', Rule::in($tribeIds)],
            'exclude_my_account' => ['sometimes', 'boolean'],
            'my_account_name' => [
                'nullable',
                'string',
                'max:120',
                Rule::requiredIf(fn () => $this->boolean('exclude_my_account')),
            ],
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
            'my_account_name.required' => 'Zadajte názov svojho účtu.',
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $vf = trim((string) $this->input('village_filter', ''));
            if ($vf === '') {
                return;
            }
            if (preg_match('/^\s*(-?\d+)\s*\|\s*(-?\d+)\s*$/', $vf, $m)) {
                $min = (int) config('travian.map.coordinate_min', -400);
                $max = (int) config('travian.map.coordinate_max', 400);
                $vx = (int) $m[1];
                $vy = (int) $m[2];
                if ($vx < $min || $vx > $max || $vy < $min || $vy > $max) {
                    $v->errors()->add('village_filter', 'Súradnice v filtri dediny sú mimo povoleného rozsahu.');
                }
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
        if ($this->has('exclude_my_account')) {
            $raw = $this->input('exclude_my_account');
            $bool = filter_var($raw, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);
            $this->merge([
                'exclude_my_account' => $bool ?? false,
            ]);
        }
        if ($this->has('tribe')) {
            $tr = $this->input('tribe');
            if ($tr === '' || $tr === null) {
                $this->merge(['tribe' => null]);
            } else {
                $this->merge(['tribe' => (int) $tr]);
            }
        }
    }
}
