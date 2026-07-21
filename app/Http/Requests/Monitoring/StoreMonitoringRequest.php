<?php

declare(strict_types=1);

namespace App\Http\Requests\Monitoring;

use App\Models\Monitoring;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreMonitoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Monitoring::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'placement_id' => [
                'required', 'uuid',
                Rule::exists('placements', 'id')->whereNull('deleted_at'),
            ],
            // The service re-checks the placement status and period; this only stops
            // the obviously impossible before it gets that far.
            'visit_date' => ['required', 'date', 'before_or_equal:now'],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
            'recommendation' => ['nullable', 'string', 'max:2000'],
            // Optional evidence, never a gate — a teacher filing from school must
            // still be able to file.
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
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
            'placement_id' => 'penempatan',
            'visit_date' => 'tanggal kunjungan',
            'note' => 'catatan',
            'recommendation' => 'rekomendasi',
            'attachments' => 'dokumentasi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'visit_date.before_or_equal' => 'Tanggal kunjungan tidak boleh di masa depan.',
            'note.min' => 'Catatan terlalu singkat, minimal 10 karakter.',
            'attachments.max' => 'Maksimal 5 dokumentasi per kunjungan.',
        ];
    }
}
