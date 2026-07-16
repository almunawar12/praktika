<?php

declare(strict_types=1);

namespace App\Http\Requests\Journal;

use App\Enums\ReviewStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Authorization is left to the controller: the ability differs per stage
 * (reviewAsIndustry vs reviewAsTeacher) and both need the bound journal.
 */
class ReviewJournalRequest extends FormRequest
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
        return [
            // `pending` is a state, not a decision — a reviewer may only approve or
            // reject.
            'decision' => [
                'required',
                Rule::in(array_column(ReviewStatus::decisions(), 'value')),
            ],
            // A rejection sends the student back to rewrite; they need to know why.
            'note' => ['nullable', 'string', 'max:1000', 'required_if:decision,rejected'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['decision' => 'keputusan', 'note' => 'catatan'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required_if' => 'Alasan penolakan wajib diisi agar siswa dapat memperbaikinya.',
        ];
    }

    public function decision(): ReviewStatus
    {
        return ReviewStatus::from((string) $this->string('decision'));
    }

    public function note(): ?string
    {
        return $this->filled('note') ? (string) $this->string('note') : null;
    }
}
