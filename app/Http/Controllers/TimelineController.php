<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\RoleName;
use App\Enums\TargetPeriodType;
use App\Enums\WorkSubmissionStatus;
use App\Models\Attendance;
use App\Models\DailyWorkSubmission;
use App\Models\Department;
use App\Models\DepartmentTarget;
use App\Models\Division;
use App\Models\DivisionTarget;
use App\Models\Employee;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\View\View;

class TimelineController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $user->loadMissing(['roles', 'employee.department', 'employee.division']);

        $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');
        $nowWib = CarbonImmutable::now($timezone);
        $today = $nowWib->toDateString();

        $rawDate = $request->query('date');
        $selectedDate = is_string($rawDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate) === 1
            ? $rawDate
            : $today;

        $rawMonth = $request->query('month');
        if (is_string($rawMonth) && preg_match('/^\d{4}-\d{2}$/', $rawMonth) === 1) {
            $monthAnchor = CarbonImmutable::createFromFormat('!Y-m', $rawMonth, $timezone) ?: $nowWib->startOfMonth();
        } elseif (is_string($rawDate) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $rawDate) === 1) {
            $monthAnchor = CarbonImmutable::parse($selectedDate, $timezone)->startOfMonth();
        } else {
            $monthAnchor = $nowWib->startOfMonth();
        }

        $year = $monthAnchor->year;
        $month = $monthAnchor->month;
        $monthStart = $monthAnchor->startOfMonth();
        $monthEnd = $monthAnchor->endOfMonth();
        $daysInMonth = $monthAnchor->daysInMonth;

        $isAdminOrHr = $user->hasAnyRole([RoleName::SUPER_ADMIN->value, RoleName::HR_MANAGER->value]);
        $isDeptManager = $user->hasRole(RoleName::DEPT_MANAGER->value);
        $isDivisionHead = $user->hasRole(RoleName::DIVISION_HEAD->value);
        $employee = $user->employee;

        $selectedPeriod = is_string($request->query('period_type'))
            ? TargetPeriodType::tryFrom((string) $request->query('period_type'))
            : null;

        $rawDeptId = $request->query('department_id');
        $selectedDepartmentId = $isAdminOrHr && is_scalar($rawDeptId) && ctype_digit((string) $rawDeptId)
            ? (int) $rawDeptId
            : (! $isAdminOrHr ? $employee?->department_id : null);

        $rawDivId = $request->query('division_id');
        $selectedDivisionId = ($isAdminOrHr || $isDeptManager) && is_scalar($rawDivId) && ctype_digit((string) $rawDivId)
            ? (int) $rawDivId
            : (! $isAdminOrHr && ! $isDeptManager ? $employee?->division_id : null);

        $availableDepartments = $isAdminOrHr
            ? Department::query()->active()->orderBy('name')->get(['id', 'code', 'name'])
            : ($employee?->department !== null ? collect([$employee->department]) : collect());

        $availableDivisions = $isAdminOrHr
            ? Division::query()
                ->active()
                ->when($selectedDepartmentId !== null, static fn (Builder $q): Builder => $q->where('department_id', $selectedDepartmentId))
                ->orderBy('name')
                ->get(['id', 'department_id', 'code', 'name'])
            : ($isDeptManager && $employee !== null
                ? Division::query()->active()->where('department_id', $employee->department_id)->orderBy('name')->get(['id', 'department_id', 'code', 'name'])
                : ($employee?->division !== null ? collect([$employee->division]) : collect()));

        // 1. Target Bulanan Departemen
        $deptTargets = DepartmentTarget::query()
            ->with(['department', 'creator', 'directDivisionTargets.division', 'divisionTargets.division'])
            ->where('year', $year)
            ->where('month', $month)
            ->when($selectedDepartmentId !== null, static fn (Builder $q): Builder => $q->where('department_id', $selectedDepartmentId))
            ->when(! $isAdminOrHr && $employee === null, static fn (Builder $q): Builder => $q->whereRaw('1 = 0'))
            ->latest('id')
            ->get();

        // 2. Target Divisi (Bulanan, Mingguan, Harian) dalam rentang bulan terpilih
        $divisionTargets = DivisionTarget::query()
            ->with([
                'department',
                'division',
                'departmentTarget',
                'parentTarget',
                'childTargets',
                'creator',
                'submissions' => static fn ($q) => $q->with(['employee.user', 'reviewer'])->latest('work_date')->latest('id'),
            ])
            ->whereDate('start_date', '<=', $monthEnd->toDateString())
            ->whereDate('end_date', '>=', $monthStart->toDateString())
            ->when($selectedPeriod !== null, static fn (Builder $q): Builder => $q->where('period_type', $selectedPeriod->value))
            ->when($selectedDepartmentId !== null, static fn (Builder $q): Builder => $q->where('department_id', $selectedDepartmentId))
            ->when($selectedDivisionId !== null, static fn (Builder $q): Builder => $q->where('division_id', $selectedDivisionId))
            ->when(! $isAdminOrHr && $employee === null, static fn (Builder $q): Builder => $q->whereRaw('1 = 0'))
            ->orderBy('start_date')
            ->orderBy('period_type')
            ->orderBy('id')
            ->get();

        // 3. Kelompokkan ke Lajur Gantt: Bulanan -> Mingguan -> Harian
        $monthlyDivisionTargets = $divisionTargets->filter(
            static fn (DivisionTarget $target): bool => $target->period_type === TargetPeriodType::MONTHLY
        );
        $weeklyDivisionTargets = $divisionTargets->filter(
            static fn (DivisionTarget $target): bool => $target->period_type === TargetPeriodType::WEEKLY
        );
        $dailyDivisionTargets = $divisionTargets->filter(
            static fn (DivisionTarget $target): bool => $target->period_type === TargetPeriodType::DAILY
        );

        $includeMonthly = $selectedPeriod === null || $selectedPeriod === TargetPeriodType::MONTHLY;
        $monthlyTracks = collect();

        if ($includeMonthly) {
            foreach ($deptTargets as $deptTarget) {
                $monthlyTracks->push($this->formatDepartmentTimelineItem($deptTarget, $monthStart, $monthEnd, $daysInMonth));
            }
            foreach ($monthlyDivisionTargets as $divTarget) {
                $monthlyTracks->push($this->formatDivisionTimelineItem($divTarget, $monthStart, $monthEnd, $daysInMonth));
            }
        }

        $weeklyTracks = $weeklyDivisionTargets
            ->map(fn (DivisionTarget $target): array => $this->formatDivisionTimelineItem($target, $monthStart, $monthEnd, $daysInMonth))
            ->values();

        $dailyTracks = $dailyDivisionTargets
            ->map(fn (DivisionTarget $target): array => $this->formatDivisionTimelineItem($target, $monthStart, $monthEnd, $daysInMonth))
            ->values();

        $allSubmissions = DailyWorkSubmission::query()
            ->with(['employee.user', 'employee.division', 'divisionTarget', 'reviewer'])
            ->whereBetween('work_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->when($selectedDepartmentId !== null, static function (Builder $q) use ($selectedDepartmentId): void {
                $q->whereHas('divisionTarget', static fn (Builder $t): Builder => $t->where('department_id', $selectedDepartmentId));
            })
            ->when($selectedDivisionId !== null, static function (Builder $q) use ($selectedDivisionId): void {
                $q->whereHas('divisionTarget', static fn (Builder $t): Builder => $t->where('division_id', $selectedDivisionId));
            })
            ->when(! $isAdminOrHr && $employee === null, static fn (Builder $q): Builder => $q->whereRaw('1 = 0'))
            ->latest('work_date')
            ->latest('id')
            ->get();

        $approvedSubmissionsCount = $allSubmissions->where('status', WorkSubmissionStatus::APPROVED)->count();
        $pendingSubmissionsCount = $allSubmissions->where('status', WorkSubmissionStatus::PENDING_REVIEW)->count();
        $totalSubmissionsCount = $allSubmissions->count();

        $summary = [
            'monthly_count' => $monthlyTracks->count(),
            'monthly_avg_progress' => $monthlyTracks->isEmpty() ? 0.0 : round((float) $monthlyTracks->avg('progress_percent'), 1),
            'weekly_count' => $weeklyTracks->count(),
            'weekly_avg_progress' => $weeklyTracks->isEmpty() ? 0.0 : round((float) $weeklyTracks->avg('progress_percent'), 1),
            'daily_count' => $totalSubmissionsCount,
            'daily_avg_progress' => $totalSubmissionsCount > 0 ? round(($approvedSubmissionsCount / $totalSubmissionsCount) * 100, 1) : 0.0,
            'approved_submissions' => $approvedSubmissionsCount,
            'pending_submissions' => $pendingSubmissionsCount,
        ];

        $weeks = $this->buildMonthWeeks($monthStart, $daysInMonth);
        $todayOffsetPercent = ($nowWib->year === $year && $nowWib->month === $month)
            ? round((($nowWib->day - 0.5) / $daysInMonth) * 100, 2)
            : null;

        // 4. Riwayat Kronologis Harian per Karyawan (Absensi + Drive PT) pada tanggal terpilih
        $canSelectEmployee = $isAdminOrHr || $isDeptManager || $isDivisionHead;
        $visibleEmployees = $canSelectEmployee
            ? Employee::query()->visibleTo($user)->with(['user', 'department', 'division'])->orderBy('employee_number')->get()
            : ($employee !== null ? collect([$employee->loadMissing(['user', 'department', 'division'])]) : collect());

        $rawEmployeeId = $request->query('employee_id');
        $selectedEmployee = null;
        if ($canSelectEmployee && is_scalar($rawEmployeeId) && ctype_digit((string) $rawEmployeeId)) {
            $selectedEmployee = $visibleEmployees->firstWhere('id', (int) $rawEmployeeId);
        }
        $selectedEmployee ??= $employee ?? $visibleEmployees->first();

        $timelineEvents = $selectedEmployee !== null
            ? $this->buildDailyEmployeeEvents($selectedEmployee, $selectedDate, $timezone)
            : collect();

        return view('timeline.index', [
            'selectedMonth' => sprintf('%04d-%02d', $year, $month),
            'selectedMonthLabel' => $monthStart->translatedFormat('F Y'),
            'selectedPeriod' => $selectedPeriod,
            'periodTypes' => TargetPeriodType::cases(),
            'availableDepartments' => $availableDepartments,
            'availableDivisions' => $availableDivisions,
            'selectedDepartmentId' => $selectedDepartmentId,
            'selectedDivisionId' => $selectedDivisionId,
            'canFilterDepartment' => $isAdminOrHr,
            'canFilterDivision' => $isAdminOrHr || $isDeptManager,
            'weeks' => $weeks,
            'daysInMonth' => $daysInMonth,
            'todayOffsetPercent' => $todayOffsetPercent,
            'todayWib' => $today,
            'monthlyTracks' => $monthlyTracks,
            'weeklyTracks' => $weeklyTracks,
            'dailyTracks' => $dailyTracks,
            'dailyWorkLogs' => $allSubmissions,
            'summary' => $summary,
            'canManageTargets' => $isDeptManager || $isDivisionHead || $isAdminOrHr,
            'canUploadWork' => $employee !== null,
            'canReviewWork' => $isDivisionHead || $isAdminOrHr,
            'timelineEvents' => $timelineEvents,
            'visibleEmployees' => $visibleEmployees,
            'selectedEmployee' => $selectedEmployee,
            'selectedDate' => $selectedDate,
            'selectedDateLabel' => CarbonImmutable::parse($selectedDate, $timezone)->translatedFormat('l, d F Y'),
            'canSelectEmployee' => $canSelectEmployee,
        ]);
    }

    /**
     * @return list<array{label: string, range: string, widthPercent: float}>
     */
    private function buildMonthWeeks(CarbonImmutable $monthStart, int $daysInMonth): array
    {
        $weeks = [];
        $ranges = [
            [1, min(7, $daysInMonth)],
            [8, min(14, $daysInMonth)],
            [15, min(21, $daysInMonth)],
            [22, min(28, $daysInMonth)],
        ];

        if ($daysInMonth > 28) {
            $ranges[] = [29, $daysInMonth];
        }

        $shortMonth = $monthStart->translatedFormat('M');

        foreach ($ranges as $index => [$startDay, $endDay]) {
            if ($startDay > $daysInMonth) {
                continue;
            }

            $weeks[] = [
                'label' => 'Pekan '.($index + 1),
                'range' => "{$startDay}–{$endDay} {$shortMonth}",
                'widthPercent' => round((($endDay - $startDay + 1) / $daysInMonth) * 100, 4),
            ];
        }

        return $weeks;
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDepartmentTimelineItem(
        DepartmentTarget $target,
        CarbonImmutable $monthStart,
        CarbonImmutable $monthEnd,
        int $daysInMonth,
    ): array {
        $progress = $target->progressPercent();
        $directCount = $target->resolvedDirectTargets()->count();
        $allocatedWeight = $target->allocatedWeight();

        return [
            'kind' => 'department',
            'period_label' => 'Bulanan Dept',
            'period_variant' => 'navy',
            'title' => $target->title,
            'description' => $target->description,
            'scope_label' => ($target->department?->code ?? 'DEPT').' — '.($target->department?->name ?? '-'),
            'creator_name' => $target->creator?->name ?? 'Manager Departemen',
            'parent_title' => null,
            'parent_kind_label' => null,
            'weight_percent' => null,
            'contribution_percent' => null,
            'has_children' => $directCount > 0,
            'start_label' => $monthStart->translatedFormat('d M'),
            'end_label' => $monthEnd->translatedFormat('d M'),
            'offset_percent' => 0.0,
            'width_percent' => 100.0,
            'progress_percent' => $progress,
            'metric_text' => number_format($progress, 1).'% progres berbobot dari '.$directCount.' target turunan (total bobot '.number_format($allocatedWeight, 0).'%)',
            'approved_count' => 0,
            'pending_count' => 0,
            'submissions' => collect(),
            'is_overdue' => false,
            'is_completed' => $progress >= 100.0,
            'is_today_active' => true,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formatDivisionTimelineItem(
        DivisionTarget $target,
        CarbonImmutable $monthStart,
        CarbonImmutable $monthEnd,
        int $daysInMonth,
    ): array {
        $clampedStart = $target->start_date->lt($monthStart) ? $monthStart : $target->start_date;
        $clampedEnd = $target->end_date->gt($monthEnd) ? $monthEnd : $target->end_date;

        $startDay = max(1, $clampedStart->day);
        $endDay = max($startDay, min($daysInMonth, $clampedEnd->day));

        $offsetPercent = round((($startDay - 1) / $daysInMonth) * 100, 2);
        $widthPercent = round((($endDay - $startDay + 1) / $daysInMonth) * 100, 2);

        $approvedCount = $target->submissions->where('status', WorkSubmissionStatus::APPROVED)->count();
        $pendingCount = $target->submissions->where('status', WorkSubmissionStatus::PENDING_REVIEW)->count();
        $today = CarbonImmutable::now((string) config('attendance.timezone', 'Asia/Jakarta'))->startOfDay();
        $progress = (float) $target->progress_percent;
        $hasChildren = $target->childTargets->isNotEmpty();

        $parentTitle = $target->parentTarget?->title ?? $target->departmentTarget?->title;
        $parentKindLabel = $target->parentTarget !== null
            ? $target->parentTarget->period_type->label()
            : ($target->departmentTarget !== null ? 'Bulanan Dept' : null);

        $metricText = $hasChildren
            ? number_format($progress, 1).'% rollup dari '.$target->childTargets->count().' target turunan (bobot dialokasikan '.number_format($target->allocatedChildWeight(), 0).'%)'
            : "{$target->approved_value}/{$target->target_value} {$target->unit_label} (".number_format($progress, 1).'%)';

        return [
            'kind' => 'division',
            'period_label' => $target->period_type->label().' Divisi',
            'period_variant' => $target->period_type->badgeVariant(),
            'title' => $target->title,
            'description' => $target->description,
            'scope_label' => ($target->division?->code ?? 'DIV').' · '.($target->division?->name ?? '-'),
            'creator_name' => $target->creator?->name ?? 'Ketua Divisi',
            'parent_title' => $parentTitle,
            'parent_kind_label' => $parentKindLabel,
            'weight_percent' => $parentTitle !== null ? (float) $target->weight_percent : null,
            'contribution_percent' => $parentTitle !== null ? $target->contributionToParentPercent() : null,
            'has_children' => $hasChildren,
            'start_label' => $target->start_date->translatedFormat('d M'),
            'end_label' => $target->end_date->translatedFormat('d M'),
            'offset_percent' => $offsetPercent,
            'width_percent' => max(3.5, $widthPercent),
            'progress_percent' => $progress,
            'metric_text' => $metricText,
            'approved_count' => $approvedCount,
            'pending_count' => $pendingCount,
            'submissions' => $target->submissions->take(4),
            'is_overdue' => $progress < 100.0 && $target->end_date->lt($today),
            'is_completed' => $progress >= 100.0,
            'is_today_active' => $target->start_date->lte($today) && $target->end_date->gte($today),
        ];
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function buildDailyEmployeeEvents(Employee $selectedEmployee, string $selectedDate, string $timezone): Collection
    {
        $events = collect();

        $attendance = Attendance::query()
            ->with(['officeLocation', 'leaveRequest.leaveType'])
            ->where('employee_id', $selectedEmployee->id)
            ->whereDate('work_date', $selectedDate)
            ->first();

        if ($attendance !== null) {
            if ($attendance->status->blocksSelfieCheckIn()) {
                $events->push([
                    'time' => '08:00',
                    'sort_at' => CarbonImmutable::parse("{$selectedDate} 08:00:00", $timezone)->getTimestamp(),
                    'badge' => $attendance->status->label(),
                    'variant' => $attendance->status->badgeVariant(),
                    'title' => 'Status Kehadiran: '.$attendance->status->label(),
                    'description' => $attendance->leaveRequest?->reason ?? 'Tercatat dari pengajuan izin/cuti/sakit yang disetujui HR.',
                    'link' => null,
                    'link_label' => null,
                ]);
            }

            if ($attendance->check_in_at !== null) {
                $inWib = $attendance->check_in_at->setTimezone($timezone);
                $locationText = $attendance->officeLocation?->name
                    ?? ($attendance->status->value === 'wfh' ? 'Lokasi WFH' : 'Luar Kantor');
                $distanceText = $attendance->check_in_distance_meters !== null
                    ? number_format($attendance->check_in_distance_meters, 0).' m dari kantor'
                    : '';

                $events->push([
                    'time' => $inWib->format('H:i'),
                    'sort_at' => $inWib->getTimestamp(),
                    'badge' => 'Absen Masuk',
                    'variant' => $attendance->status->badgeVariant(),
                    'title' => 'Check-In ('.$attendance->status->label().')',
                    'description' => trim("{$locationText} {$distanceText}"),
                    'link' => null,
                    'link_label' => null,
                ]);
            }

            if ($attendance->check_out_at !== null) {
                $outWib = $attendance->check_out_at->setTimezone($timezone);
                $events->push([
                    'time' => $outWib->format('H:i'),
                    'sort_at' => $outWib->getTimestamp(),
                    'badge' => 'Absen Pulang',
                    'variant' => 'navy',
                    'title' => 'Check-Out Selesai Kerja',
                    'description' => $attendance->officeLocation?->name ?? 'Tercatat selesai bertugas hari ini.',
                    'link' => null,
                    'link_label' => null,
                ]);
            }
        }

        $submissions = DailyWorkSubmission::query()
            ->with(['divisionTarget', 'reviewer'])
            ->where('employee_id', $selectedEmployee->id)
            ->whereDate('work_date', $selectedDate)
            ->get();

        foreach ($submissions as $submission) {
            $uploadedAt = ($submission->created_at ?? CarbonImmutable::parse("{$selectedDate} 12:00:00", $timezone))
                ->setTimezone($timezone);

            $events->push([
                'time' => $uploadedAt->format('H:i'),
                'sort_at' => $uploadedAt->getTimestamp(),
                'badge' => 'Upload Drive PT',
                'variant' => 'info',
                'title' => 'Mengunggah Kerjaan: '.$submission->title,
                'description' => 'Target: '.($submission->divisionTarget?->title ?? '-').' (+'.$submission->contribution_value.' poin).',
                'link' => $submission->drive_web_view_link,
                'link_label' => $submission->drive_file_name ? "Buka di Google Drive PT ({$submission->drive_file_name})" : null,
            ]);

            if ($submission->reviewed_at !== null) {
                $reviewedWib = $submission->reviewed_at->setTimezone($timezone);
                $events->push([
                    'time' => $reviewedWib->format('H:i'),
                    'sort_at' => $reviewedWib->getTimestamp(),
                    'badge' => $submission->status->label(),
                    'variant' => $submission->status->badgeVariant(),
                    'title' => 'Konfirmasi Ketua Divisi: '.$submission->status->label(),
                    'description' => 'Oleh '.($submission->reviewer?->name ?? 'Atasan')
                        .($submission->review_note ? " — \"{$submission->review_note}\"" : ''),
                    'link' => null,
                    'link_label' => null,
                ]);
            }
        }

        return $events->sortBy('sort_at')->values();
    }
}
