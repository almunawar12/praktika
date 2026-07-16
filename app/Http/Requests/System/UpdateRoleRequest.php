<?php

declare(strict_types=1);

namespace App\Http\Requests\System;

use App\Support\PermissionCatalog;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->route('role'));
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'permissions' => ['present', 'array'],
            // Only real permissions from the catalogue may be granted.
            'permissions.*' => [Rule::in(PermissionCatalog::all())],
        ];
    }

    /**
     * @return array<int, string>
     */
    public function permissions(): array
    {
        /** @var array<int, string> $permissions */
        $permissions = $this->input('permissions', []);

        return array_values(array_unique($permissions));
    }
}
