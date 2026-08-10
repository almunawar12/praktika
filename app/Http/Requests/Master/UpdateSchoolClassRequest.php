<?php

declare(strict_types=1);

namespace App\Http\Requests\Master;

use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RuntimeException;

class UpdateSchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->schoolClass());
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
            'department_id' => [
                'required', 'uuid',
                Rule::exists('departments', 'id')->whereNull('deleted_at'),
            ],
            // Optional: omitting it leaves the class's current academic year
            // untouched — see SchoolClassService::update().
            'academic_year_id' => [
                'nullable', 'uuid',
                Rule::exists('academic_years', 'id')->whereNull('deleted_at'),
            ],
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('school_classes', 'name')
                    ->ignore($this->schoolClass()->id)
                    ->where('department_id', $this->input('department_id'))
                    ->whereNull('deleted_at'),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['department_id' => 'jurusan', 'academic_year_id' => 'tahun ajaran', 'name' => 'nama'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.unique' => 'Nama kelas sudah dipakai pada jurusan ini.',
        ];
    }

    /**
     * The bound route model. `route()` is typed `object|string`, so this narrows it
     * once here rather than assuming the binding held.
     */
    private function schoolClass(): SchoolClass
    {
        $class = $this->route('class');

        if (! $class instanceof SchoolClass) {
            throw new RuntimeException('Route parameter [class] is not bound to a SchoolClass.');
        }

        return $class;
    }
}
