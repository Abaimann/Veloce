<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\WorkSubmissionStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

#[Fillable([
    'employee_id',
    'division_target_id',
    'work_date',
    'title',
    'description',
    'contribution_value',
    'drive_file_id',
    'drive_file_name',
    'drive_web_view_link',
    'drive_folder_path',
    'file_size_bytes',
    'mime_type',
    'status',
    'reviewed_by',
    'reviewed_at',
    'review_note',
])]
class DailyWorkSubmission extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['title', 'work_date', 'division_target_id', 'contribution_value', 'status', 'reviewed_by', 'review_note'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'work_date' => 'immutable_date',
            'contribution_value' => 'integer',
            'file_size_bytes' => 'integer',
            'status' => WorkSubmissionStatus::class,
            'reviewed_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /**
     * @return BelongsTo<DivisionTarget, $this>
     */
    public function divisionTarget(): BelongsTo
    {
        return $this->belongsTo(DivisionTarget::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }
}
