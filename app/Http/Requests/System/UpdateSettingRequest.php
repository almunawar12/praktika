<?php

declare(strict_types=1);

namespace App\Http\Requests\System;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

class UpdateSettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('settings.update');
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'industry_weight' => ['required', 'numeric', 'min:0', 'max:1'],
            'teacher_weight' => ['required', 'numeric', 'min:0', 'max:1'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // The two shares must split 100% exactly, or a final score can not be
            // reproduced from its own inputs — the same rule final_scores_weights_check
            // enforces at the database.
            $sum = (float) $this->input('industry_weight') + (float) $this->input('teacher_weight');

            if (abs($sum - 1.0) > 0.0001) {
                $validator->errors()->add('teacher_weight', 'Bobot industri dan guru harus berjumlah 1 (100%).');
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'industry_weight' => 'bobot industri',
            'teacher_weight' => 'bobot guru',
        ];
    }
}
