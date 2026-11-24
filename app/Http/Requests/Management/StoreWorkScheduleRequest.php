<?php

declare(strict_types=1);

namespace App\Http\Requests\Management;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;

class StoreWorkScheduleRequest extends FormRequest
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
            'check_in_time' => ['required', 'date_format:H:i,H:i:s'],
            'check_out_time' => ['required', 'date_format:H:i,H:i:s'],
            'late_tolerance_minutes' => ['required', 'integer', 'min:0', 'max:480'],
            'work_days' => ['required', 'array', 'min:1'],
            'work_days.*' => ['required', 'integer', 'distinct', 'between:1,7'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'name' => 'nama jadwal kerja',
            'check_in_time' => 'jam masuk',
            'check_out_time' => 'jam pulang',
            'late_tolerance_minutes' => 'toleransi keterlambatan',
            'work_days' => 'hari kerja',
            'work_days.*' => 'hari kerja',
        ];
    }
}
