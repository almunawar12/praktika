<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A student declaring izin or sakit for today.
 */
class StoreAbsenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('checkIn', Attendance::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Only izin and sakit: `present` must come from a real check-in, and
            // `absent` is the nightly job's to assign — neither is claimable here.
            'status' => [
                'required',
                Rule::in(array_column(AttendanceStatus::requestable(), 'value')),
            ],
            'notes' => ['required', 'string', 'min:5', 'max:1000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['status' => 'status', 'notes' => 'keterangan'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'notes.required' => 'Keterangan wajib diisi agar pembimbing dapat memverifikasi.',
        ];
    }

    public function status(): AttendanceStatus
    {
        return AttendanceStatus::from((string) $this->string('status'));
    }
}
