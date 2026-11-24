<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use App\Models\Employee;
use App\Models\Holiday;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class WorkdayCalculator
{
    public function isWorkday(Employee $employee, CarbonInterface $date): bool
    {
        $schedule = $employee->workSchedule;

        if ($schedule === null || ! $schedule->isWorkday($date->dayOfWeekIso)) {
            return false;
        }

        return ! Holiday::query()
            ->whereDate('date', $date->toDateString())
            ->exists();
    }

    /**
     * @param  array<string, true>|null  $preloadedHolidayDates
     */
    public function countWorkdays(
        Employee $employee,
        CarbonInterface $start,
        CarbonInterface $end,
        ?array $preloadedHolidayDates = null,
    ): int {
        return count($this->workdaysInRange($employee, $start, $end, $preloadedHolidayDates));
    }

    /**
     * Menghasilkan daftar tanggal hari kerja efektif dalam rentang [start, end] inklusif.
     *
     * @param  array<string, true>|null  $preloadedHolidayDates
     * @return array<int, CarbonImmutable>
     */
    public function workdaysInRange(
        Employee $employee,
        CarbonInterface $start,
        CarbonInterface $end,
        ?array $preloadedHolidayDates = null,
    ): array {
        $schedule = $employee->workSchedule;

        if ($schedule === null) {
            return [];
        }

        $startDay = CarbonImmutable::instance($start)->startOfDay();
        $endDay = CarbonImmutable::instance($end)->startOfDay();

        if ($startDay->greaterThan($endDay)) {
            return [];
        }

        $holidayDates = $preloadedHolidayDates ?? self::holidayLookupMap($startDay, $endDay);

        $workdays = [];

        for ($cursor = $startDay; $cursor->lessThanOrEqualTo($endDay); $cursor = $cursor->addDay()) {
            if (! $schedule->isWorkday($cursor->dayOfWeekIso)) {
                continue;
            }

            if (isset($holidayDates[$cursor->toDateString()])) {
                continue;
            }

            $workdays[] = $cursor;
        }

        return $workdays;
    }

    /**
     * @return array<string, true>
     */
    public static function holidayLookupMap(CarbonInterface $start, CarbonInterface $end): array
    {
        $startDate = CarbonImmutable::instance($start)->toDateString();
        $endDate = CarbonImmutable::instance($end)->toDateString();

        return Holiday::query()
            ->whereBetween('date', [$startDate, $endDate])
            ->get(['date'])
            ->mapWithKeys(static fn (Holiday $holiday): array => [
                $holiday->date->toDateString() => true,
            ])
            ->all();
    }
}
