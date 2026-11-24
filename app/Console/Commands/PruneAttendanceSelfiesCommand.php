<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Attendance;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;

class PruneAttendanceSelfiesCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'attendance:prune-selfies {--days= : Jumlah hari retensi selfie}';

    /**
     * @var string
     */
    protected $description = 'Menghapus file foto selfie absensi yang melewati batas retensi dari disk privat.';

    public function handle(): int
    {
        $daysOption = $this->option('days');
        $days = $daysOption !== null && $daysOption !== ''
            ? (int) $daysOption
            : (int) config('attendance.selfie_retention_days', 90);

        if ($days < 0) {
            $this->error('Nilai opsi --days tidak boleh negatif.');

            return self::FAILURE;
        }

        $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');
        $now = CarbonImmutable::now($timezone);
        $cutoff = $now->subDays($days);
        $disk = Storage::disk((string) config('attendance.private_disk', 'local'));
        $prunedCount = 0;

        Attendance::query()
            ->where(function (Builder $query) use ($cutoff): void {
                $query->whereDate('work_date', '<', $cutoff->toDateString())
                    ->orWhere('created_at', '<', $cutoff);
            })
            ->where(function (Builder $query): void {
                $query->whereNotNull('selfie_in_path')
                    ->orWhereNotNull('selfie_out_path');
            })
            ->chunkById(100, function (Collection $attendances) use ($disk, $now, &$prunedCount): void {
                foreach ($attendances as $attendance) {
                    foreach ([$attendance->selfie_in_path, $attendance->selfie_out_path] as $path) {
                        if (is_string($path) && $path !== '' && $disk->exists($path)) {
                            $disk->delete($path);
                        }
                    }

                    $attendance->update([
                        'selfie_in_path' => null,
                        'selfie_out_path' => null,
                        'selfie_pruned_at' => $now,
                    ]);

                    $prunedCount++;
                }
            });

        $this->info("Berhasil membersihkan {$prunedCount} catatan selfie absensi (retensi {$days} hari).");

        return self::SUCCESS;
    }
}
