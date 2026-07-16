<?php

declare(strict_types=1);

namespace App\Http\Requests\Master;

use App\Models\Teacher;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RuntimeException;

class UpdateTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->teacher());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $teacher = $this->teacher();

        return [
            'nip' => [
                'required', 'string', 'max:30',
                Rule::unique('teachers', 'nip')->ignore($teacher->id)->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                // Ignores the teacher's *own* user row, so saving an unchanged email
                // does not fail against itself.
                Rule::unique('users', 'email')->ignore($teacher->user_id)->whereNull('deleted_at'),
            ],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['nip' => 'NIP', 'name' => 'nama', 'email' => 'email', 'phone' => 'no HP'];
    }

    /**
     * The bound route model. `route()` is typed `object|string`, so this narrows it
     * once here rather than assuming the binding held.
     */
    private function teacher(): Teacher
    {
        $teacher = $this->route('teacher');

        if (! $teacher instanceof Teacher) {
            throw new RuntimeException('Route parameter [teacher] is not bound to a Teacher.');
        }

        return $teacher;
    }
}
