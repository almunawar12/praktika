<?php

declare(strict_types=1);

namespace App\Http\Requests\Report;

use App\Enums\ReportFormat;
use App\Enums\ReportType;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreReportRequest extends FormRequest
{
    /**
     * Only someone permitted to generate the requested type may ask for it.
     * Guards the endpoint even though the UI already hides types they lack.
     */
    public function authorize(): bool
    {
        $type = ReportType::tryFrom((string) $this->input('type'));

        return $type !== null && $this->user()->can($type->permission());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(ReportType::class)],
            'format' => ['required', Rule::enum(ReportFormat::class)],

            // All optional refinements. Absent = the report's full default scope.
            'department_id' => ['nullable', 'uuid', Rule::exists('departments', 'id')],
            'company_id' => ['nullable', 'uuid', Rule::exists('companies', 'id')],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ];
    }

    /**
     * The filters, normalised for storage on the export row.
     *
     * @return array<string, string>
     */
    public function filters(): array
    {
        return array_filter([
            'department_id' => (string) $this->input('department_id', ''),
            'company_id' => (string) $this->input('company_id', ''),
            'date_from' => (string) $this->input('date_from', ''),
            'date_to' => (string) $this->input('date_to', ''),
        ], fn (string $value): bool => $value !== '');
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'type' => 'jenis laporan',
            'format' => 'format',
            'department_id' => 'jurusan',
            'company_id' => 'perusahaan',
            'date_from' => 'tanggal mulai',
            'date_to' => 'tanggal akhir',
        ];
    }
}
