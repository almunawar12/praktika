<?php

declare(strict_types=1);

namespace App\Http\Requests\Journal;

use App\Models\Journal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

class UpdateJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->journal());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The date is fixed at creation: moving a journal to another day would
            // collide with that day's own entry and rewrite history.
            'date' => ['prohibited'],
            'activity' => ['required', 'string', 'min:10', 'max:2000'],
            'work_result' => ['required', 'string', 'min:10', 'max:2000'],
            'obstacle' => ['nullable', 'string', 'max:2000'],
            'attachments' => ['nullable', 'array', 'max:5'],
            'attachments.*' => ['file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'activity' => 'uraian kegiatan',
            'work_result' => 'hasil pekerjaan',
            'obstacle' => 'kendala',
            'attachments' => 'dokumentasi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'date.prohibited' => 'Tanggal jurnal tidak dapat diubah.',
            'activity.min' => 'Uraian kegiatan terlalu singkat, minimal 10 karakter.',
            'work_result.min' => 'Hasil pekerjaan terlalu singkat, minimal 10 karakter.',
        ];
    }

    private function journal(): Journal
    {
        $journal = $this->route('journal');

        if (! $journal instanceof Journal) {
            throw new RuntimeException('Route parameter [journal] is not bound to a Journal.');
        }

        return $journal;
    }
}
