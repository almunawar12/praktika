<?php

declare(strict_types=1);

namespace App\Http\Requests\Pkl;

use App\Models\Placement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Placement::class);
    }

    /**
     * The picker's "Otomatis" option submits an empty string, not an absent field —
     * `nullable` only recognises the latter, so this is what actually lets it through.
     */
    protected function prepareForValidation(): void
    {
        if ($this->input('academic_year_id') === '') {
            $this->merge(['academic_year_id' => null]);
        }
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'student_id' => [
                'required', 'uuid',
                Rule::exists('students', 'id')->whereNull('deleted_at'),
            ],
            'company_id' => [
                'required', 'uuid',
                Rule::exists('companies', 'id')->whereNull('deleted_at'),
            ],
            'teacher_id' => [
                'required', 'uuid',
                Rule::exists('teachers', 'id')->whereNull('deleted_at'),
            ],
            // Optional: the service defaults to the active academic year when omitted.
            'academic_year_id' => [
                'nullable', 'uuid',
                Rule::exists('academic_years', 'id')->whereNull('deleted_at'),
            ],
            'start_date' => ['required', 'date'],
            // Mirrors the placements_dates_check constraint, so the user gets a field
            // error instead of a database error.
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'student_id' => 'siswa',
            'company_id' => 'perusahaan',
            'teacher_id' => 'guru pembimbing',
            'academic_year_id' => 'tahun ajaran',
            'start_date' => 'tanggal mulai',
            'end_date' => 'tanggal selesai',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'end_date.after_or_equal' => 'Tanggal selesai tidak boleh sebelum tanggal mulai.',
        ];
    }
}
