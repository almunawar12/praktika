<?php

declare(strict_types=1);

namespace App\Http\Requests\Assessment;

use App\Enums\AssessmentCriteria;
use App\Enums\AssessmentType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

/**
 * Authorization lives in the controller: the ability differs per side
 * (submitAsIndustry vs submitAsTeacher) and needs the bound placement.
 */
class StoreAssessmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $codes = array_column($this->type()->criteria(), 'value');

        $rules = [
            'scores' => ['required', 'array'],
            'note' => ['nullable', 'string', 'max:2000'],
            'submit' => ['sometimes', 'boolean'],
        ];

        foreach ($codes as $code) {
            // Every criterion for this side is required even on a draft: a partial
            // average would be meaningless, and the stored average is what the final
            // score is computed from.
            $rules["scores.{$code}"] = ['required', 'numeric', 'between:0,100'];
        }

        // Reject anything outside this side's criteria — a teacher must not be able to
        // post a score for `disiplin`, which is the company's to judge.
        $rules['scores.*'] = ['numeric', 'between:0,100'];

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $allowed = array_column($this->type()->criteria(), 'value');

            // The `required` rules above ensure this side's criteria are present; this
            // ensures nothing else is. A teacher must not be able to post a score for
            // `disiplin`, which is the company's to judge.
            foreach (array_keys((array) $this->input('scores', [])) as $code) {
                if (! in_array((string) $code, $allowed, true)) {
                    $validator->errors()->add(
                        "scores.{$code}",
                        'Kriteria ini bukan bagian dari '.$this->type()->label().'.',
                    );
                }
            }
        });
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        $attributes = ['note' => 'catatan'];

        foreach (AssessmentCriteria::cases() as $criteria) {
            $attributes["scores.{$criteria->value}"] = strtolower($criteria->label());
        }

        return $attributes;
    }

    public function type(): AssessmentType
    {
        $type = $this->route('type');

        if (! is_string($type) || AssessmentType::tryFrom($type) === null) {
            throw new RuntimeException('Route parameter [type] is not a valid assessment type.');
        }

        return AssessmentType::from($type);
    }
}
