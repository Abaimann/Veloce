<?php

declare(strict_types=1);

namespace App\Http\Requests\Management;

use App\Enums\EmploymentType;
use App\Enums\RoleName;
use App\Models\Department;
use App\Models\Division;
use App\Models\Employee;
use App\Models\User;
use App\Models\WorkSchedule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class StoreEmployeeRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Employee::class) ?? false;
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('email') && is_string($this->input('email'))) {
            $this->merge([
                'email' => Str::lower(trim($this->input('email'))),
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'employee_number' => ['required', 'string', 'max:50', Rule::unique(Employee::class, 'employee_number')],
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
            'role' => ['required', 'string', Rule::enum(RoleName::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'supervisor_id' => 'atasan langsung',
            'position_title' => 'jabatan',
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'division_id.exists' => 'Divisi yang dipilih tidak sesuai dengan departemen tersebut.',
        ];
    }
}
