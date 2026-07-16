<?php

declare(strict_types=1);

namespace App\Http\Requests\Master;

use App\Models\CompanySupervisor;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCompanySupervisorRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', CompanySupervisor::class);
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
            // Unique against `users`: the email is the login identity and must not
            // collide with any account, whatever its role.
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
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
}
