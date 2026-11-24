<?php

declare(strict_types=1);

namespace App\Http\Requests\Management;

use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreDivisionRequest extends FormRequest
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
            'department_id' => ['required', 'integer', Rule::exists(Department::class, 'id')],
            'code' => [
                'required',
                'string',
                'max:20',
                Rule::unique(Division::class, 'code')->where(
                    fn ($query) => $query->where('department_id', $this->integer('department_id'))
                ),
            ],
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
            'department_id' => 'departemen',
            'code' => 'kode divisi',
            'name' => 'nama divisi',
            'head_employee_id' => 'kepala divisi',
        ];
    }
}
