<?php

declare(strict_types=1);

namespace App\Http\Requests\System;

use App\Models\Role;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class StoreRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Role::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            // Roles are looked up by their slug; two display names that slug to the
            // same machine name would collide silently.
            if (Role::query()->where('name', $this->slug())->exists()) {
                $validator->errors()->add('name', 'Role dengan nama tersebut sudah ada.');
            }
        });
    }

    /**
     * The machine name stored on the role.
     */
    public function slug(): string
    {
        return Str::slug($this->string('name')->toString());
    }
}
