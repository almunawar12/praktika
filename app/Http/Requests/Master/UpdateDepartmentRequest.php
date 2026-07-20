<?php

declare(strict_types=1);

namespace App\Http\Requests\Master;

use App\Models\Department;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RuntimeException;

class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->department());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'code' => [
                'required', 'string', 'max:20',
                Rule::unique('departments', 'code')
                    ->ignore($this->department()->id)
                    ->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    /**
     * The bound route model. `route()` is typed `object|string`, so this narrows it
     * once here rather than assuming the binding held.
     */
    private function department(): Department
    {
        $department = $this->route('department');

        if (! $department instanceof Department) {
            throw new RuntimeException('Route parameter [department] is not bound to a Department.');
        }

        return $department;
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['code' => 'kode', 'name' => 'nama'];
    }
}
