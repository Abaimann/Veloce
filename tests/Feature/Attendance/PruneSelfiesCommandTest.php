<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Models\Attendance;
use App\Models\OfficeLocation;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake((string) config('attendance.private_disk', 'local'));
    $this->seed();
});

test('perintah attendance:prune-selfies menghapus selfie lebih lama dari 90 hari dan mempertahankan selfie terbaru', function (): void {
    $privateDisk = (string) config('attendance.private_disk', 'local');
    $user = User::query()->where('email', 'operator.prd@veloce.test')->firstOrFail();
    $office = OfficeLocation::query()->active()->firstOrFail();

    $oldDate = CarbonImmutable::now('Asia/Jakarta')->subDays(100);
    $recentDate = CarbonImmutable::now('Asia/Jakarta')->subDays(5);

    $sampleFile = UploadedFile::fake()->image('sample.jpg', 320, 240);
    $sampleBytes = (string) $sampleFile->get();

    $oldInPath = 'selfies/'.$oldDate->format('Y/m').'/old-in.jpg';
    $oldOutPath = 'selfies/'.$oldDate->format('Y/m').'/old-out.jpg';
    $recentInPath = 'selfies/'.$recentDate->format('Y/m').'/recent-in.jpg';
    $recentOutPath = 'selfies/'.$recentDate->format('Y/m').'/recent-out.jpg';

    Storage::disk($privateDisk)->put($oldInPath, $sampleBytes);
    Storage::disk($privateDisk)->put($oldOutPath, $sampleBytes);
    Storage::disk($privateDisk)->put($recentInPath, $sampleBytes);
    Storage::disk($privateDisk)->put($recentOutPath, $sampleBytes);

    $oldAttendance = Attendance::query()->create([
        'employee_id' => $user->employee->id,
        'work_date' => $oldDate->toDateString(),
        'check_in_at' => $oldDate->setTime(8, 0),
        'check_out_at' => $oldDate->setTime(17, 0),
        'office_location_id' => $office->id,
        'selfie_in_path' => $oldInPath,
        'selfie_out_path' => $oldOutPath,
        'selfie_in_hash' => hash('sha256', 'old-in'),
        'selfie_out_hash' => hash('sha256', 'old-out'),
        'status' => AttendanceStatus::PRESENT,
        'flags' => [],
    ]);

    DB::table('attendances')
        ->where('id', $oldAttendance->id)
        ->update([
            'created_at' => $oldDate->toIso8601String(),
            'updated_at' => $oldDate->toIso8601String(),
        ]);

    $recentAttendance = Attendance::query()->create([
        'employee_id' => $user->employee->id,
        'work_date' => $recentDate->toDateString(),
        'check_in_at' => $recentDate->setTime(8, 5),
        'check_out_at' => $recentDate->setTime(17, 10),
        'office_location_id' => $office->id,
        'selfie_in_path' => $recentInPath,
        'selfie_out_path' => $recentOutPath,
        'selfie_in_hash' => hash('sha256', 'recent-in'),
        'selfie_out_hash' => hash('sha256', 'recent-out'),
        'status' => AttendanceStatus::PRESENT,
        'flags' => [],
    ]);

    DB::table('attendances')
        ->where('id', $recentAttendance->id)
        ->update([
            'created_at' => $recentDate->toIso8601String(),
            'updated_at' => $recentDate->toIso8601String(),
        ]);

    $this->artisan('attendance:prune-selfies', ['--days' => 90])
        ->assertSuccessful();

    $oldFresh = $oldAttendance->fresh();
    $recentFresh = $recentAttendance->fresh();

    expect(Storage::disk($privateDisk)->exists($oldInPath))->toBeFalse()
        ->and(Storage::disk($privateDisk)->exists($oldOutPath))->toBeFalse()
        ->and($oldFresh->selfie_in_path)->toBeNull()
        ->and($oldFresh->selfie_out_path)->toBeNull()
        ->and($oldFresh->selfie_pruned_at)->not->toBeNull();

    expect(Storage::disk($privateDisk)->exists($recentInPath))->toBeTrue()
        ->and(Storage::disk($privateDisk)->exists($recentOutPath))->toBeTrue()
        ->and($recentFresh->selfie_in_path)->toBe($recentInPath)
        ->and($recentFresh->selfie_out_path)->toBe($recentOutPath)
        ->and($recentFresh->selfie_pruned_at)->toBeNull();
});
