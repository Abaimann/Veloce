<?php

declare(strict_types=1);

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Attendance;
use App\Models\LeaveRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PrivateMediaController extends Controller
{
    public function selfie(Request $request, Attendance $attendance, string $phase): StreamedResponse
    {
        if (! in_array($phase, ['in', 'out'], true)) {
            abort(404);
        }

        $attendance->loadMissing('employee');
        Gate::authorize('viewSelfie', $attendance);

        $path = $phase === 'in'
            ? $attendance->selfie_in_path
            : $attendance->selfie_out_path;

        if (! is_string($path) || $path === '') {
            abort(404);
        }

        $disk = (string) config('attendance.private_disk', 'local');
        $filesystem = Storage::disk($disk);

        if (! $filesystem->exists($path)) {
            abort(404);
        }

        return $filesystem->response($path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }

    public function leaveAttachment(Request $request, LeaveRequest $leaveRequest): StreamedResponse
    {
        $leaveRequest->loadMissing(['employee.user', 'approvals']);
        Gate::authorize('viewAttachment', $leaveRequest);

        $path = $leaveRequest->attachment_path;

        if (! is_string($path) || $path === '') {
            abort(404);
        }

        $disk = (string) config('attendance.private_disk', 'local');
        $filesystem = Storage::disk($disk);

        if (! $filesystem->exists($path)) {
            abort(404);
        }

        return $filesystem->response($path, null, [
            'Cache-Control' => 'private, no-store, max-age=0',
        ]);
    }
}
