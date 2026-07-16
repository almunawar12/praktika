<?php

declare(strict_types=1);

namespace App\Http\Requests\Master;

use App\Models\CompanySupervisor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use RuntimeException;

class UpdateCompanySupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->supervisor());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'company_id' => [
                'required', 'uuid',
                Rule::exists('companies', 'id')->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required', 'string', 'email', 'max:255',
                // Ignores the supervisor's own user row, so saving an unchanged email
                // does not fail against itself.
                Rule::unique('users', 'email')
                    ->ignore($this->supervisor()->user_id)
                    ->whereNull('deleted_at'),
            ],
            'position' => ['nullable', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'company_id' => 'perusahaan',
            'name' => 'nama',
            'email' => 'email',
            'position' => 'jabatan',
            'phone' => 'no HP',
        ];
    }

    private function supervisor(): CompanySupervisor
    {
        $supervisor = $this->route('supervisor');

        if (! $supervisor instanceof CompanySupervisor) {
            throw new RuntimeException('Route parameter [supervisor] is not bound to a CompanySupervisor.');
        }

        return $supervisor;
    }
}
