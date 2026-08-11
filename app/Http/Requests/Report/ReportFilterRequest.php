<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use App\Enums\ReportFormat;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query string a report is read at. Authorization lives in the
 * controller, against ReportPolicy and the bound ReportType.
 */
class ReportFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'department_id' => ['nullable', 'uuid', 'exists:departments,id'],
            'company_id' => ['nullable', 'uuid', 'exists:companies,id'],
            'school_class_id' => ['nullable', 'uuid', 'exists:school_classes,id'],
            'teacher_id' => ['nullable', 'uuid', 'exists:teachers,id'],
            'student_id' => ['nullable', 'uuid', 'exists:students,id'],
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:5', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'format' => ['nullable', Rule::enum(ReportFormat::class)],
        ];
    }

    /**
     * The shared FilterSelect submits `all` for "no filter"; drop those before
     * validation so they are not mistaken for ids.
     */
    protected function prepareForValidation(): void
    {
        $this->replace(array_filter(
            $this->all(),
            fn (mixed $value): bool => $value !== 'all' && $value !== '' && $value !== null,
        ));
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date_to.after_or_equal' => 'Tanggal akhir tidak boleh mendahului tanggal mulai.',
        ];
    }
}
