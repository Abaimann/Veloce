<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\RoleName;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'user_id',
    'employee_number',
    'department_id',
    'division_id',
    'supervisor_id',
    'employment_type',
    'position_title',
    'work_schedule_id',
    'join_date',
    'is_active',
])]
class Employee extends Model
{
    use LogsActivity, SoftDeletes;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'employment_type' => EmploymentType::class,
            'join_date' => 'immutable_date',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * @return BelongsTo<Division, $this>
     */
    public function division(): BelongsTo
    {
        return $this->belongsTo(Division::class);
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function subordinates(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_id');
    }

    /**
     * @return BelongsTo<WorkSchedule, $this>
     */
    public function workSchedule(): BelongsTo
    {
        return $this->belongsTo(WorkSchedule::class);
    }

    /**
     * @return HasMany<LeaveBalance, $this>
     */
    public function leaveBalances(): HasMany
    {
        return $this->hasMany(LeaveBalance::class);
    }

    /**
     * @return HasMany<LeaveRequest, $this>
     */
    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    /**
     * @return HasMany<Attendance, $this>
     */
    public function attendances(): HasMany
    {
        return $this->hasMany(Attendance::class);
    }

    /**
     * Membatasi visibilitas data karyawan secara konsisten sesuai matriks role.
     *
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeVisibleTo(Builder $query, User $viewer): Builder
    {
        if ($viewer->hasAnyRole([RoleName::SUPER_ADMIN->value, RoleName::HR_MANAGER->value])) {
            return $query;
        }

        $viewerEmployee = $viewer->employee;

        if ($viewerEmployee === null) {
            return $query->whereRaw('1 = 0');
        }

        if ($viewer->hasRole(RoleName::DEPT_MANAGER->value)) {
            return $query->where('department_id', $viewerEmployee->department_id);
        }

        if ($viewer->hasRole(RoleName::DIVISION_HEAD->value)) {
            return $query->where(function (Builder $sub) use ($viewerEmployee): void {
                if ($viewerEmployee->division_id !== null) {
                    $sub->where('division_id', $viewerEmployee->division_id);
                }

                $sub->orWhereKey($viewerEmployee->id);
            });
        }

        if ($viewer->hasRole(RoleName::EMPLOYEE->value)) {
            return $query->whereKey($viewerEmployee->id);
        }

        // Role payroll tidak memiliki akses melihat detail daftar karyawan di Fase 1.
        return $query->whereRaw('1 = 0');
    }

    /**
     * @param  Builder<Employee>  $query
     * @return Builder<Employee>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
