<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'department_id',
    'created_by',
    'title',
    'description',
    'year',
    'month',
    'target_value',
    'unit_label',
    'progress_percent',
    'status',
])]
class DepartmentTarget extends Model
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
            'year' => 'integer',
            'month' => 'integer',
            'target_value' => 'integer',
            'progress_percent' => 'float',
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
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<DivisionTarget, $this>
     */
    public function divisionTargets(): HasMany
    {
        return $this->hasMany(DivisionTarget::class);
    }

    /**
     * @return HasMany<DivisionTarget, $this>
     */
    public function directDivisionTargets(): HasMany
    {
        return $this->hasMany(DivisionTarget::class)->whereNull('parent_division_target_id');
    }

    /**
     * @return Collection<int, DivisionTarget>
     */
    public function resolvedDirectTargets(): Collection
    {
        if ($this->relationLoaded('directDivisionTargets')) {
            return $this->directDivisionTargets;
        }

        if ($this->relationLoaded('divisionTargets')) {
            return $this->divisionTargets
                ->whereNull('parent_division_target_id')
                ->values();
        }

        return $this->directDivisionTargets()->get();
    }

    public function allocatedWeight(): float
    {
        return round((float) $this->resolvedDirectTargets()->sum('weight_percent'), 2);
    }

    public function progressPercent(): float
    {
        $targets = $this->resolvedDirectTargets();

        if ($targets->isEmpty()) {
            return 0.0;
        }

        $totalWeight = (float) $targets->sum('weight_percent');
        $denominator = max(100.0, $totalWeight);

        if ($denominator <= 0.0) {
            return 0.0;
        }

        $weightedSum = (float) $targets->sum(
            static fn (DivisionTarget $target): float => ((float) $target->progress_percent) * ((float) $target->weight_percent)
        );

        return min(100.0, round($weightedSum / $denominator, 2));
    }

    public function recalculateProgress(): void
    {
        $targets = $this->directDivisionTargets()->get();
        $this->setRelation('directDivisionTargets', $targets);

        $percent = $this->progressPercent();

        $this->update([
            'progress_percent' => $percent,
            'status' => $percent >= 100.0 ? 'completed' : 'active',
        ]);
    }
}
