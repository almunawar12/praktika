<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Models\Attendance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

class VerifyAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('verify', $this->attendance());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['required', 'in:verified,rejected'],
            // A rejection means "this did not happen as claimed" — that needs a reason
            // the student can read. An approval does not.
            'note' => ['nullable', 'string', 'max:1000', 'required_if:decision,rejected'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['decision' => 'keputusan', 'note' => 'catatan'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'note.required_if' => 'Alasan penolakan wajib diisi.',
        ];
    }

    public function isRejection(): bool
    {
        return $this->string('decision')->toString() === 'rejected';
    }

    private function attendance(): Attendance
    {
        $attendance = $this->route('attendance');

        if (! $attendance instanceof Attendance) {
            throw new RuntimeException('Route parameter [attendance] is not bound to an Attendance.');
        }

        return $attendance;
    }
}
