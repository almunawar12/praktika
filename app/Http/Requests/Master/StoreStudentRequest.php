<?php

declare(strict_types=1);

namespace App\Http\Requests\Master;

use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Student::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nis' => [
                'required', 'string', 'max:30',
                Rule::unique('students', 'nis')->whereNull('deleted_at'),
            ],
            'nisn' => [
                'required', 'string', 'max:30',
                Rule::unique('students', 'nisn')->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
            'class_id' => [
                'required', 'uuid',
                Rule::exists('school_classes', 'id')->whereNull('deleted_at'),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'nis' => 'NIS',
            'nisn' => 'NISN',
            'name' => 'nama',
            'email' => 'email',
            'phone' => 'no HP',
            'class_id' => 'kelas',
        ];
    }
}
