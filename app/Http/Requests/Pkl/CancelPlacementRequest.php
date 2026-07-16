<?php

declare(strict_types=1);

namespace App\Http\Requests\Pkl;

use App\Models\Placement;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

class CancelPlacementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('cancel', $this->placement());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Required: a cancelled PKL is a question someone will ask about later,
            // and "why" needs to survive in the audit trail.
            'cancel_reason' => ['required', 'string', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['cancel_reason' => 'alasan pembatalan'];
    }

    private function placement(): Placement
    {
        $placement = $this->route('placement');

        if (! $placement instanceof Placement) {
            throw new RuntimeException('Route parameter [placement] is not bound to a Placement.');
        }

        return $placement;
    }
}
