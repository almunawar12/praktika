<?php

declare(strict_types=1);

namespace App\Http\Requests\Master;

use App\Models\AcademicYear;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RuntimeException;

class UpdateAcademicYearRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->academicYear());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('academic_years', 'name')
                    ->ignore($this->academicYear()->id)
                    ->whereNull('deleted_at'),
            ],
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
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

    /**
     * The bound route model. `route()` is typed `object|string`, so this narrows it
     * once here rather than assuming the binding held.
     */
    private function academicYear(): AcademicYear
    {
        $academicYear = $this->route('academic_year');

        if (! $academicYear instanceof AcademicYear) {
            throw new RuntimeException('Route parameter [academic_year] is not bound to an AcademicYear.');
        }

        return $academicYear;
    }
}
