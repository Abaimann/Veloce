<?php

declare(strict_types=1);

namespace App\Http\Requests\Management;

use App\Models\Department;
use App\Models\Holiday;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreHolidayRequest extends FormRequest
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
            'date' => ['required', 'date', Rule::unique(Holiday::class, 'date')],
            'name' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'date' => 'tanggal libur',
            'name' => 'nama hari libur',
        ];
    }
}
