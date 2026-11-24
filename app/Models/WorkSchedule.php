<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable(['name', 'check_in_time', 'check_out_time', 'late_tolerance_minutes', 'work_days', 'is_active'])]
class WorkSchedule extends Model
{
    use LogsActivity;

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
            'late_tolerance_minutes' => 'integer',
            'work_days' => 'array',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Employee, $this>
     */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function isWorkday(int $isoDayOfWeek): bool
    {
        $days = array_map('intval', $this->work_days ?? []);

        return in_array($isoDayOfWeek, $days, true);
    }

    /**
     * @param  Builder<WorkSchedule>  $query
     * @return Builder<WorkSchedule>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
