<?php

declare(strict_types=1);

namespace App\Http\Requests\Journal;

use App\Models\Journal;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreJournalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Journal::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The service re-checks the period and duplicates against the placement;
            // this only stops the obviously impossible before it gets that far.
            'date' => ['required', 'date', 'before_or_equal:today'],
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
            'date' => 'tanggal',
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
            'date.before_or_equal' => 'Jurnal tidak dapat diisi untuk tanggal yang belum terjadi.',
            'activity.min' => 'Uraian kegiatan terlalu singkat, minimal 10 karakter.',
            'work_result.min' => 'Hasil pekerjaan terlalu singkat, minimal 10 karakter.',
            'attachments.max' => 'Maksimal 5 dokumentasi per jurnal.',
        ];
    }
}
