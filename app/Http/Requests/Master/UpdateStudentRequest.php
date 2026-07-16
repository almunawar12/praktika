<?php

declare(strict_types=1);

namespace App\Http\Requests\Master;

use App\Models\Student;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RuntimeException;

class UpdateStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->student());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $student = $this->student();

        return [
            'nis' => [
                'required', 'string', 'max:30',
                Rule::unique('students', 'nis')->ignore($student->id)->whereNull('deleted_at'),
            ],
            'nisn' => [
                'required', 'string', 'max:30',
                Rule::unique('students', 'nisn')->ignore($student->id)->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->ignore($student->user_id)->whereNull('deleted_at'),
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

    /**
     * The bound route model. `route()` is typed `object|string`, so this narrows it
     * once here rather than assuming the binding held.
     */
    private function student(): Student
    {
        $student = $this->route('student');

        if (! $student instanceof Student) {
            throw new RuntimeException('Route parameter [student] is not bound to a Student.');
        }

        return $student;
    }
}
