<?php

declare(strict_types=1);

namespace App\Support\Attendance;

use App\Models\Attendance;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

class AnomalyFlagDetector
{
    /**
     * Mendeteksi flag anomali non-blocking untuk tinjauan HR.
     *
     * @return array<int, array<string, mixed>>
     */
    public function detect(
        string $phase,
        float $accuracyMeters,
        CarbonInterface $serverTime,
        CarbonInterface $clientTime,
        string $rawPhotoHash,
        ?int $ignoreAttendanceId = null,
    ): array {
        $flags = [];

        $maxAccuracy = (float) config('attendance.max_acceptable_accuracy_meters', 100);

        if ($accuracyMeters > $maxAccuracy) {
            $flags[] = [
                'code' => 'low_gps_accuracy',
                'phase' => $phase,
                'message' => __('attendance.flags.low_gps_accuracy', [
                    'meters' => (int) round($maxAccuracy),
                ]),
                'meta' => [
                    'accuracy' => $accuracyMeters,
                ],
            ];
        }

        $maxSkewSeconds = (int) config('attendance.max_client_clock_skew_seconds', 300);
        $diffSeconds = abs($serverTime->getTimestamp() - $clientTime->getTimestamp());

        if ($diffSeconds > $maxSkewSeconds) {
            $flags[] = [
                'code' => 'client_clock_skew',
                'phase' => $phase,
                'message' => __('attendance.flags.client_clock_skew', [
                    'minutes' => max(1, (int) round($maxSkewSeconds / 60)),
                ]),
                'meta' => [
                    'diff_seconds' => $diffSeconds,
                ],
            ];
        }

        if ($rawPhotoHash !== '' && $this->hasDuplicateSelfieHash($rawPhotoHash, $phase, $ignoreAttendanceId)) {
            $flags[] = [
                'code' => 'duplicate_selfie_hash',
                'phase' => $phase,
                'message' => __('attendance.flags.duplicate_selfie_hash'),
            ];
        }

        return $flags;
    }

    private function hasDuplicateSelfieHash(
        string $rawPhotoHash,
        string $phase,
        ?int $ignoreAttendanceId,
    ): bool {
        return Attendance::query()
            ->where(function (Builder $query) use ($rawPhotoHash, $phase, $ignoreAttendanceId): void {
                if ($ignoreAttendanceId === null) {
                    $query->where('selfie_in_hash', $rawPhotoHash)
                        ->orWhere('selfie_out_hash', $rawPhotoHash);

                    return;
                }

                // Pada baris absensi yang sama, tetap deteksi duplikasi lintas fase (mis. foto check-in dipakai ulang saat check-out).
                $query->where(function (Builder $sameRow) use ($rawPhotoHash, $phase, $ignoreAttendanceId): void {
                    $sameRow->where('id', $ignoreAttendanceId);

                    if ($phase === 'out') {
                        $sameRow->where('selfie_in_hash', $rawPhotoHash);
                    } else {
                        $sameRow->where('selfie_out_hash', $rawPhotoHash);
                    }
                })->orWhere(function (Builder $otherRows) use ($rawPhotoHash, $ignoreAttendanceId): void {
                    $otherRows->where('id', '!=', $ignoreAttendanceId)
                        ->where(function (Builder $hashes) use ($rawPhotoHash): void {
                            $hashes->where('selfie_in_hash', $rawPhotoHash)
                                ->orWhere('selfie_out_hash', $rawPhotoHash);
                        });
                });
            })
            ->exists();
    }
}
