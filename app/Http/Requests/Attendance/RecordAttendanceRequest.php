<?php

declare(strict_types=1);

namespace App\Http\Requests\Attendance;

use App\Enums\RoleName;
use Illuminate\Foundation\Http\FormRequest;

class RecordAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user?->employee !== null
            && ! $user->hasRole(RoleName::PAYROLL->value);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'photo' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
            'accuracy' => ['required', 'numeric', 'min:0', 'max:100000'],
            'client_time' => ['required', 'date'],
        ];
    }
}
