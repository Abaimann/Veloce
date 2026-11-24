<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TargetPeriodType;
use App\Enums\WorkSubmissionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'department_id',
    'division_id',
    'department_target_id',
    'parent_division_target_id',
    'created_by',
    'title',
    'description',
    'period_type',
    'start_date',
    'end_date',
    'target_value',
    'approved_value',
    'progress_percent',
    'weight_percent',
    'unit_label',
    'status',
])]
class DivisionTarget extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly([
                'title',
                'period_type',
                'department_target_id',
                'parent_division_target_id',
                'start_date',
                'end_date',
                'target_value',
                'approved_value',
                'progress_percent',
                'weight_percent',
                'status',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'period_type' => TargetPeriodType::class,
            'start_date' => 'immutable_date',
            'end_date' => 'immutable_date',
            'target_value' => 'integer',
            'approved_value' => 'integer',
            'progress_percent' => 'float',
            'weight_percent' => 'float',
        ];
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
     * @return BelongsTo<DepartmentTarget, $this>
     */
    public function departmentTarget(): BelongsTo
    {
        return $this->belongsTo(DepartmentTarget::class);
    }

    /**
     * @return BelongsTo<DivisionTarget, $this>
     */
    public function parentTarget(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_division_target_id');
    }

    /**
     * @return HasMany<DivisionTarget, $this>
     */
    public function childTargets(): HasMany
    {
        return $this->hasMany(self::class, 'parent_division_target_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<DailyWorkSubmission, $this>
     */
    public function submissions(): HasMany
    {
        return $this->hasMany(DailyWorkSubmission::class);
    }

    public function allocatedChildWeight(): float
    {
        $children = $this->relationLoaded('childTargets')
            ? $this->childTargets
            : $this->childTargets()->get();

        return round((float) $children->sum('weight_percent'), 2);
    }

    public function contributionToParentPercent(?float $siblingWeightTotal = null): float
    {
        if ($this->parent_division_target_id === null && $this->department_target_id === null) {
            return 0.0;
        }

        if ($siblingWeightTotal === null) {
            $siblingWeightTotal = $this->parent_division_target_id !== null
                ? (float) self::query()->where('parent_division_target_id', $this->parent_division_target_id)->sum('weight_percent')
                : (float) self::query()->where('department_target_id', $this->department_target_id)->whereNull('parent_division_target_id')->sum('weight_percent');
        }

        $denominator = max(100.0, $siblingWeightTotal);

        return $denominator > 0.0
            ? round(((float) $this->progress_percent * (float) $this->weight_percent) / $denominator, 2)
            : 0.0;
    }

    /**
     * @param  array<int, int>  $visitedIds
     */
    public function recalculateProgress(array $visitedIds = []): void
    {
        if (in_array($this->id, $visitedIds, true)) {
            return;
        }
        $visitedIds[] = $this->id;

        $children = $this->childTargets()->get();
        $approvedSubmissions = (int) $this->submissions()
            ->where('status', WorkSubmissionStatus::APPROVED->value)
            ->sum('contribution_value');

        if ($children->isNotEmpty()) {
            $totalWeight = (float) $children->sum('weight_percent');
            $denominator = max(100.0, $totalWeight);
            $weightedSum = (float) $children->sum(
                static fn (self $child): float => ((float) $child->progress_percent) * ((float) $child->weight_percent)
            );
            $percent = $denominator > 0.0 ? min(100.0, round($weightedSum / $denominator, 2)) : 0.0;
            $approvedTotal = (int) $children->sum('approved_value') + $approvedSubmissions;
        } else {
            $approvedTotal = $approvedSubmissions;
            $percent = $this->target_value > 0
                ? min(100.0, round(($approvedTotal / $this->target_value) * 100, 2))
                : 0.0;
        }

        $this->update([
            'approved_value' => $approvedTotal,
            'progress_percent' => $percent,
            'status' => $percent >= 100.0 ? 'completed' : 'active',
        ]);

        if ($this->parent_division_target_id !== null) {
            $parent = self::query()->find($this->parent_division_target_id);
            $parent?->recalculateProgress($visitedIds);
        } elseif ($this->department_target_id !== null) {
            $deptParent = DepartmentTarget::query()->find($this->department_target_id);
            $deptParent?->recalculateProgress();
        }
    }
}
