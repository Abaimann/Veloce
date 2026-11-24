<?php

declare(strict_types=1);

namespace App\Http\Requests\Management;

use App\Models\Department;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDepartmentRequest extends FormRequest
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
            'code' => ['required', 'string', 'max:20', Rule::unique(Department::class, 'code')],
            'name' => ['required', 'string', 'max:255'],
            'head_employee_id' => ['nullable', 'integer', Rule::exists(Employee::class, 'id')],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'code' => 'kode departemen',
            'name' => 'nama departemen',
            'head_employee_id' => 'kepala departemen',
        ];
    }
}
