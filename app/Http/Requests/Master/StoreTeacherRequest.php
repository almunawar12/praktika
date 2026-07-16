<?php

declare(strict_types=1);

namespace App\Http\Requests\Master;

use App\Models\Teacher;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTeacherRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Teacher::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'nip' => [
                'required', 'string', 'max:30',
                Rule::unique('teachers', 'nip')->whereNull('deleted_at'),
            ],
            'name' => ['required', 'string', 'max:255'],
            // Unique against `users`, not `teachers`: the email is the login identity
            // and must not collide with any account, whatever its role.
            'email' => [
                'required', 'string', 'email', 'max:255',
                Rule::unique('users', 'email')->whereNull('deleted_at'),
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
}
