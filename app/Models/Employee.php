<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\EmploymentType;
use App\Enums\RoleName;
use Carbon\CarbonImmutable;
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
    'contract_start_date',
    'contract_end_date',
    'base_salary',
    'fixed_allowance',
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
            'contract_start_date' => 'immutable_date',
            'contract_end_date' => 'immutable_date',
            'base_salary' => 'integer',
            'fixed_allowance' => 'integer',
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
     * @return HasMany<DailyWorkSubmission, $this>
     */
    public function workSubmissions(): HasMany
    {
        return $this->hasMany(DailyWorkSubmission::class);
    }

    /**
     * @return HasMany<PayrollItem, $this>
     */
    public function payrollItems(): HasMany
    {
        return $this->hasMany(PayrollItem::class);
    }

    /**
     * Ringkasan masa kontrak karyawan untuk tampilan manajemen dan filter kontrak.
     *
     * @return array{label: string, variant: string, days_remaining: ?int, period_text: string}
     */
    public function contractSummary(?CarbonImmutable $referenceDate = null): array
    {
        $today = ($referenceDate ?? CarbonImmutable::now((string) config('attendance.timezone', 'Asia/Jakarta')))
            ->startOfDay();

        if ($this->contract_end_date === null) {
            if ($this->employment_type === EmploymentType::INTERN) {
                return [
                    'label' => 'Magang Berjalan',
                    'variant' => 'info',
                    'days_remaining' => null,
                    'period_text' => ($this->contract_start_date ?? $this->join_date)?->translatedFormat('d M Y').' – Selesai',
                ];
            }

            return [
                'label' => 'Tetap (PKWTT)',
                'variant' => 'navy',
                'days_remaining' => null,
                'period_text' => 'Mulai '.($this->contract_start_date ?? $this->join_date)?->translatedFormat('d M Y'),
            ];
        }

        $startLabel = ($this->contract_start_date ?? $this->join_date)?->translatedFormat('d M Y') ?? '-';
        $endLabel = $this->contract_end_date->translatedFormat('d M Y');
        $daysRemaining = (int) $today->diffInDays($this->contract_end_date, false);

        if ($daysRemaining < 0) {
            return [
                'label' => 'Kontrak Berakhir',
                'variant' => 'danger',
                'days_remaining' => $daysRemaining,
                'period_text' => "{$startLabel} – {$endLabel}",
            ];
        }

        if ($daysRemaining <= 30) {
            return [
                'label' => "Segera Berakhir ({$daysRemaining} hr)",
                'variant' => 'warning',
                'days_remaining' => $daysRemaining,
                'period_text' => "{$startLabel} – {$endLabel}",
            ];
        }

        return [
            'label' => "Kontrak Aktif ({$daysRemaining} hr)",
            'variant' => 'success',
            'days_remaining' => $daysRemaining,
            'period_text' => "{$startLabel} – {$endLabel}",
        ];
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
