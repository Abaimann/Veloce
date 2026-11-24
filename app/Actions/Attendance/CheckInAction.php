<?php

declare(strict_types=1);

namespace App\Actions\Attendance;

use App\Enums\AttendanceStatus;
use App\Enums\LeaveRequestStatus;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\LeaveRequest;
use App\Support\Attendance\AnomalyFlagDetector;
use App\Support\Attendance\SelfieWatermarker;
use App\Support\Attendance\WorkdayCalculator;
use App\Support\Geo\GeofenceEvaluator;
use App\Support\Geo\GeofenceResult;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

final class CheckInAction
{
    public function __construct(
        private readonly WorkdayCalculator $workdayCalculator,
        private readonly GeofenceEvaluator $geofenceEvaluator,
        private readonly AnomalyFlagDetector $anomalyFlagDetector,
        private readonly SelfieWatermarker $selfieWatermarker,
    ) {}

    /**
     * Mencatat absensi masuk karyawan beserta watermark selfie, evaluasi geofence, dan flag anomali.
     *
     * @throws ValidationException
     */
    public function execute(
        Employee $employee,
        UploadedFile $photo,
        float $lat,
        float $lng,
        float $accuracy,
        CarbonInterface $clientTime,
    ): Attendance {
        $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');
        $serverNow = CarbonImmutable::now($timezone);
        $workDate = $serverNow->toDateString();

        return DB::transaction(function () use (
            $employee,
            $photo,
            $lat,
            $lng,
            $accuracy,
            $clientTime,
            $timezone,
            $serverNow,
            $workDate,
        ): Attendance {
            // Kunci baris karyawan agar transaksi ganda pada tanggal yang sama terurut aman sebelum INSERT.
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();
            $employee->loadMissing(['workSchedule', 'user']);

            $existing = Attendance::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $workDate)
                ->lockForUpdate()
                ->first();

            if ($existing !== null && $existing->status->blocksSelfieCheckIn()) {
                throw ValidationException::withMessages([
                    'attendance' => __('attendance.messages.blocked_by_leave', [
                        'status' => $existing->status->label(),
                    ]),
                ]);
            }

            $isWorkday = $this->workdayCalculator->isWorkday($employee, $serverNow);

            if ($isWorkday) {
                $approvedNonWfhLeave = $this->findApprovedNonWfhRequest($employee, $workDate);

                if ($approvedNonWfhLeave !== null) {
                    $leaveStatusLabel = $approvedNonWfhLeave->leaveType?->attendance_status->label()
                        ?? $approvedNonWfhLeave->leaveType?->name
                        ?? AttendanceStatus::LEAVE->label();

                    throw ValidationException::withMessages([
                        'attendance' => __('attendance.messages.blocked_by_leave', [
                            'status' => $leaveStatusLabel,
                        ]),
                    ]);
                }
            }

            if ($existing !== null && $existing->check_in_at !== null) {
                throw ValidationException::withMessages([
                    'attendance' => __('attendance.messages.already_checked_in'),
                ]);
            }

            $approvedWfhRequestId = $this->findApprovedWfhRequestId($employee, $workDate);
            $hasApprovedWfh = ($existing?->status === AttendanceStatus::WFH) || $approvedWfhRequestId !== null;

            if (! config('attendance.allow_non_workday_attendance', false)
                && ! $hasApprovedWfh
                && ! $isWorkday) {
                throw ValidationException::withMessages([
                    'attendance' => __('attendance.messages.blocked_non_workday'),
                ]);
            }

            $geofence = $hasApprovedWfh ? null : $this->validateInsideGeofence($lat, $lng);
            $status = $this->resolveAttendanceStatus($employee, $workDate, $timezone, $serverNow, $hasApprovedWfh);

            $rawHash = $this->selfieWatermarker->rawHash($photo);
            $flags = $this->anomalyFlagDetector->detect(
                phase: 'in',
                accuracyMeters: $accuracy,
                serverTime: $serverNow,
                clientTime: $clientTime,
                rawPhotoHash: $rawHash,
                ignoreAttendanceId: $existing?->id,
            );

            $stored = $this->selfieWatermarker->watermarkAndStore($photo, $employee, $serverNow, $lat, $lng);

            try {
                $attendance = $existing ?? new Attendance([
                    'employee_id' => $employee->id,
                    'work_date' => $workDate,
                ]);

                $existingFlags = is_array($attendance->flags) ? $attendance->flags : [];

                $attendance->fill([
                    'check_in_at' => $serverNow,
                    'check_in_lat' => $lat,
                    'check_in_lng' => $lng,
                    'check_in_accuracy' => $accuracy,
                    'check_in_distance_meters' => $geofence?->distanceMeters,
                    'office_location_id' => $geofence?->nearestOffice?->id,
                    'selfie_in_path' => $stored['path'],
                    'selfie_in_hash' => $stored['raw_hash'],
                    'client_check_in_at' => $clientTime,
                    'status' => $status,
                    'leave_request_id' => $existing?->leave_request_id ?? $approvedWfhRequestId,
                    'flags' => array_values(array_merge($existingFlags, $flags)),
                ]);
                $attendance->save();
            } catch (Throwable $exception) {
                Storage::disk((string) config('attendance.private_disk', 'local'))->delete($stored['path']);
                throw $exception;
            }

            activity()
                ->causedBy($employee->user)
                ->performedOn($attendance)
                ->withProperties([
                    'phase' => 'in',
                    'work_date' => $workDate,
                    'status' => $status->value,
                    'office_location_id' => $geofence?->nearestOffice?->id,
                    'distance_meters' => $geofence?->distanceMeters,
                    'flags_count' => count($flags),
                ])
                ->log('Mencatat absen masuk');

            return $attendance;
        });
    }

    /**
     * @throws ValidationException
     */
    private function validateInsideGeofence(float $lat, float $lng): GeofenceResult
    {
        $geofence = $this->geofenceEvaluator->evaluate($lat, $lng);

        if (! $geofence->hasActiveOffices) {
            throw ValidationException::withMessages([
                'attendance' => __('attendance.messages.no_active_office'),
            ]);
        }

        if (! $geofence->isInside) {
            throw ValidationException::withMessages([
                'attendance' => __('attendance.messages.outside_geofence', [
                    'distance' => $this->formatMeters((float) ($geofence->distanceMeters ?? 0.0)),
                    'office' => $geofence->nearestOffice?->name ?? '',
                    'radius' => (string) ($geofence->nearestOffice?->radius_meters ?? 0),
                ]),
            ]);
        }

        return $geofence;
    }

    private function resolveAttendanceStatus(
        Employee $employee,
        string $workDate,
        string $timezone,
        CarbonImmutable $serverNow,
        bool $hasApprovedWfh,
    ): AttendanceStatus {
        if ($hasApprovedWfh) {
            return AttendanceStatus::WFH;
        }

        $schedule = $employee->workSchedule;
        $checkInTime = trim((string) ($schedule?->check_in_time ?? '08:00:00'));
        $deadline = CarbonImmutable::parse("{$workDate} {$checkInTime}", $timezone)
            ->addMinutes((int) ($schedule?->late_tolerance_minutes ?? 0));

        return $serverNow->greaterThan($deadline)
            ? AttendanceStatus::LATE
            : AttendanceStatus::PRESENT;
    }

    private function findApprovedWfhRequestId(Employee $employee, string $workDate): ?int
    {
        $id = LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequestStatus::APPROVED->value)
            ->whereDate('start_date', '<=', $workDate)
            ->whereDate('end_date', '>=', $workDate)
            ->whereHas('leaveType', static function (Builder $query): void {
                $query->where('code', 'wfh');
            })
            ->value('id');

        return $id !== null ? (int) $id : null;
    }

    private function findApprovedNonWfhRequest(Employee $employee, string $workDate): ?LeaveRequest
    {
        return LeaveRequest::query()
            ->with('leaveType')
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequestStatus::APPROVED->value)
            ->whereDate('start_date', '<=', $workDate)
            ->whereDate('end_date', '>=', $workDate)
            ->whereHas('leaveType', static function (Builder $query): void {
                $query->where('code', '!=', 'wfh');
            })
            ->first();
    }

    private function formatMeters(float $meters): string
    {
        $formatted = number_format($meters, 2, '.', '');

        return str_contains($formatted, '.')
            ? rtrim(rtrim($formatted, '0'), '.')
            : $formatted;
    }
}
