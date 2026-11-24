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

    public function countWorkdays(Employee $employee, CarbonInterface $start, CarbonInterface $end): int
    {
        return count($this->workdaysInRange($employee, $start, $end));
    }

    /**
     * Menghasilkan daftar tanggal hari kerja efektif dalam rentang [start, end] inklusif.
     *
     * @return array<int, CarbonImmutable>
     */
    public function workdaysInRange(Employee $employee, CarbonInterface $start, CarbonInterface $end): array
    {
        $schedule = $employee->workSchedule;

        if ($schedule === null) {
            return [];
        }

        $startDay = CarbonImmutable::instance($start)->startOfDay();
        $endDay = CarbonImmutable::instance($end)->startOfDay();

        if ($startDay->greaterThan($endDay)) {
            return [];
        }

        $holidayDates = Holiday::query()
            ->whereDate('date', '>=', $startDay->toDateString())
            ->whereDate('date', '<=', $endDay->toDateString())
            ->get(['date'])
            ->mapWithKeys(static fn (Holiday $holiday): array => [
                $holiday->date->toDateString() => true,
            ])
            ->all();

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
}
