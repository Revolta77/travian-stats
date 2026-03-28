<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class ServerImportUploadRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'snapshot_date' => ['required', 'date_format:Y-m-d'],
            'sql' => ['nullable', 'string', 'max:50000000'],
            'sql_file' => ['nullable', 'file', 'max:51200'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $v): void {
            $hasFile = $this->hasFile('sql_file');
            $text = trim((string) $this->input('sql', ''));
            if (! $hasFile && $text === '') {
                $v->errors()->add('sql', 'Vlož SQL text alebo nahraj súbor.');
            }
        });
    }
}
