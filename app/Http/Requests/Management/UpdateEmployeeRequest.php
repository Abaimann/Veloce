<?php

declare(strict_types=1);

namespace App\Http\Requests\Management;

use App\Enums\EmploymentType;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Employee|null $employee */
        $employee = $this->route('employee');

        return $employee !== null && ($this->user()?->can('update', $employee) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Employee $employee */
        $employee = $this->route('employee');

        return [
            'name' => ['required', 'string', 'max:255'],
            'employee_number' => [
                'required',
                'string',
                'max:50',
                Rule::unique(Employee::class, 'employee_number')->ignore($employee->id),
            ],
            'department_id' => ['required', 'integer', Rule::exists(Department::class, 'id')],
            'division_id' => [
                'nullable',
                'integer',
                Rule::exists(Division::class, 'id')->where(
                    fn ($query) => $query->where('department_id', $this->integer('department_id'))
                ),
            ],
            'supervisor_id' => ['nullable', 'integer', Rule::exists(Employee::class, 'id')],
            'work_schedule_id' => ['required', 'integer', Rule::exists(WorkSchedule::class, 'id')],
            'employment_type' => ['required', 'string', Rule::enum(EmploymentType::class)],
            'position_title' => ['nullable', 'string', 'max:255'],
            'join_date' => ['required', 'date'],
            'contract_start_date' => ['nullable', 'date'],
            'contract_end_date' => ['nullable', 'date', 'after_or_equal:contract_start_date'],
            'base_salary' => ['nullable', 'integer', 'min:0'],
            'fixed_allowance' => ['nullable', 'integer', 'min:0'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }
}
