<?php

declare(strict_types=1);

namespace App\Http\Requests\Master;

use App\Models\SchoolClass;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreSchoolClassRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', SchoolClass::class);
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
            // Scoped to the department, matching the composite partial unique index:
            // RPL "XII A" and TKJ "XII A" are different classes.
            'name' => [
                'required', 'string', 'max:255',
                Rule::unique('school_classes', 'name')
                    ->where('department_id', $this->input('department_id'))
                    ->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['department_id' => 'jurusan', 'name' => 'nama'];
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
}
