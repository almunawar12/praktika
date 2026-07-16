<?php

declare(strict_types=1);

namespace App\Http\Requests\Monitoring;

use App\Models\Monitoring;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use RuntimeException;

class UpdateMonitoringRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('update', $this->monitoring());
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // The placement is fixed at creation: moving a report to another PKL
            // would rewrite whose visit it was.
            'placement_id' => ['prohibited'],
            'visit_date' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['required', 'string', 'min:10', 'max:2000'],
            'recommendation' => ['nullable', 'string', 'max:2000'],
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
            'placement_id.prohibited' => 'Penempatan pada laporan kunjungan tidak dapat diubah.',
            'visit_date.before_or_equal' => 'Tanggal kunjungan tidak boleh di masa depan.',
            'note.min' => 'Catatan terlalu singkat, minimal 10 karakter.',
        ];
    }

    private function monitoring(): Monitoring
    {
        $monitoring = $this->route('monitoring');

        if (! $monitoring instanceof Monitoring) {
            throw new RuntimeException('Route parameter [monitoring] is not bound to a Monitoring.');
        }

        return $monitoring;
    }
}
