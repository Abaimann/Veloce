<?php

declare(strict_types=1);

namespace App\Http\Requests\Management;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;

class StoreOfficeLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage', Department::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'radius_meters' => ['required', 'integer', 'min:10', 'max:50000'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama lokasi kantor',
            'address' => 'alamat kantor',
            'lat' => 'garis lintang',
            'lng' => 'garis bujur',
            'radius_meters' => 'radius geofence',
        ];
    }
}
