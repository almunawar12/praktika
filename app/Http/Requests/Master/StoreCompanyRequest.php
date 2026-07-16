<?php

declare(strict_types=1);

namespace App\Http\Requests\Master;

use App\Models\Company;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreCompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()->can('create', Company::class);
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'business_field' => ['required', 'string', 'max:255'],
            'address' => ['required', 'string', 'max:1000'],
            'pic_name' => ['required', 'string', 'max:255'],
            'pic_phone' => ['required', 'string', 'max:20'],
            // Capped at the smallint column's range; a quota of 0 means "not accepting".
            'quota' => ['required', 'integer', 'min:0', 'max:65535'],
            'is_active' => ['sometimes', 'boolean'],
            // Coordinates drive the attendance geofence. Optional — a company without
            // them simply has no geofence — but latitude and longitude are meaningless
            // apart, so each requires the other (mirrors companies_coordinates_check).
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'geofence_radius_m' => ['nullable', 'integer', 'min:10', 'max:5000', 'required_with:latitude'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama',
            'business_field' => 'bidang usaha',
            'address' => 'alamat',
            'pic_name' => 'nama PIC',
            'pic_phone' => 'no HP PIC',
            'quota' => 'kuota',
            'latitude' => 'lintang',
            'longitude' => 'bujur',
            'geofence_radius_m' => 'radius absensi',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'geofence_radius_m.required_with' => 'Radius absensi wajib diisi jika koordinat diatur.',
        ];
    }
}
