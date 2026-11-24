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

final class CheckOutAction
{
    public function __construct(
        private readonly GeofenceEvaluator $geofenceEvaluator,
        private readonly AnomalyFlagDetector $anomalyFlagDetector,
        private readonly SelfieWatermarker $selfieWatermarker,
    ) {}

    /**
     * Mencatat absensi pulang karyawan beserta watermark selfie, geofence (bila aktif), dan flag anomali.
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
            $serverNow,
            $workDate,
        ): Attendance {
            Employee::query()->whereKey($employee->id)->lockForUpdate()->first();
            $employee->loadMissing(['user']);

            $attendance = Attendance::query()
                ->where('employee_id', $employee->id)
                ->whereDate('work_date', $workDate)
                ->lockForUpdate()
                ->first();

            if ($attendance === null) {
                throw ValidationException::withMessages([
                    'attendance' => __('attendance.messages.must_check_in_first'),
                ]);
            }

            if ($attendance->status->blocksSelfieCheckIn()) {
                throw ValidationException::withMessages([
                    'attendance' => __('attendance.messages.blocked_by_leave', [
                        'status' => $attendance->status->label(),
                    ]),
                ]);
            }

            if ($attendance->check_in_at === null) {
                throw ValidationException::withMessages([
                    'attendance' => __('attendance.messages.must_check_in_first'),
                ]);
            }

            if ($attendance->check_out_at !== null) {
                throw ValidationException::withMessages([
                    'attendance' => __('attendance.messages.already_checked_out'),
                ]);
            }

            $hasApprovedWfh = ($attendance->status === AttendanceStatus::WFH)
                || $this->hasApprovedWfh($employee, $workDate);

            $geofence = null;

            if (config('attendance.enforce_geofence_on_checkout', true)
                && $attendance->status !== AttendanceStatus::WFH
                && ! $hasApprovedWfh) {
                $geofence = $this->validateInsideGeofence($lat, $lng);
            }

            $rawHash = $this->selfieWatermarker->rawHash($photo);
            $outFlags = $this->anomalyFlagDetector->detect(
                phase: 'out',
                accuracyMeters: $accuracy,
                serverTime: $serverNow,
                clientTime: $clientTime,
                rawPhotoHash: $rawHash,
                ignoreAttendanceId: $attendance->id,
            );

            $stored = $this->selfieWatermarker->watermarkAndStore($photo, $employee, $serverNow, $lat, $lng);

            try {
                $existingFlags = is_array($attendance->flags) ? $attendance->flags : [];

                $attendance->fill([
                    'check_out_at' => $serverNow,
                    'check_out_lat' => $lat,
                    'check_out_lng' => $lng,
                    'check_out_accuracy' => $accuracy,
                    'check_out_distance_meters' => $geofence?->distanceMeters,
                    'selfie_out_path' => $stored['path'],
                    'selfie_out_hash' => $stored['raw_hash'],
                    'client_check_out_at' => $clientTime,
                    'flags' => array_values(array_merge($existingFlags, $outFlags)),
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
                    'phase' => 'out',
                    'work_date' => $workDate,
                    'distance_meters' => $geofence?->distanceMeters,
                    'flags_count' => count($outFlags),
                ])
                ->log('Mencatat absen pulang');

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

    private function hasApprovedWfh(Employee $employee, string $workDate): bool
    {
        return LeaveRequest::query()
            ->where('employee_id', $employee->id)
            ->where('status', LeaveRequestStatus::APPROVED->value)
            ->whereDate('start_date', '<=', $workDate)
            ->whereDate('end_date', '>=', $workDate)
            ->whereHas('leaveType', static function (Builder $query): void {
                $query->where('code', 'wfh');
            })
            ->exists();
    }

    private function formatMeters(float $meters): string
    {
        $formatted = number_format($meters, 2, '.', '');

        return str_contains($formatted, '.')
            ? rtrim(rtrim($formatted, '0'), '.')
            : $formatted;
    }
}
