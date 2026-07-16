<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Models\Attendance;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class CheckOutRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('checkOut', Attendance::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
            'photo' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:4096'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return ['latitude' => 'lokasi', 'longitude' => 'lokasi', 'photo' => 'foto'];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'latitude.required' => 'Lokasi tidak terdeteksi. Izinkan akses lokasi pada browser Anda.',
            'longitude.required' => 'Lokasi tidak terdeteksi. Izinkan akses lokasi pada browser Anda.',
            'photo.required' => 'Foto wajib diambil saat check out.',
            'photo.max' => 'Ukuran foto maksimal 4 MB.',
        ];
    }
}
