<?php

declare(strict_types=1);

namespace App\Http\Controllers\Work;

use App\Enums\RoleName;
use App\Enums\WorkSubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\DailyWorkSubmission;
use App\Models\DivisionTarget;
use App\Support\Drive\GoogleDriveService;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

class DailyWorkSubmissionController extends Controller
{
    public function index(Request $request, GoogleDriveService $driveService): View
    {
        $user = $request->user();
        $user->loadMissing(['roles', 'employee.department', 'employee.division']);

        $employee = $user->employee;
        if ($employee === null) {
            abort(403);
        }

        $isAdminOrHr = $user->hasAnyRole([RoleName::SUPER_ADMIN->value, RoleName::HR_MANAGER->value]);
        $isDeptManager = $user->hasRole(RoleName::DEPT_MANAGER->value);
        $isDivisionHead = $user->hasRole(RoleName::DIVISION_HEAD->value);

        $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');
        $todayWib = CarbonImmutable::now($timezone)->toDateString();

        $rawStatus = $request->query('status');
        $selectedStatus = is_string($rawStatus) ? WorkSubmissionStatus::tryFrom($rawStatus) : null;

        // Daftar target aktif (Mingguan/Bulanan) yang dapat dipilih saat mengupload bukti kerja harian.
        $availableTargets = DivisionTarget::query()
            ->with(['division', 'departmentTarget'])
            ->doesntHave('childTargets')
            ->when($employee->division_id !== null, static fn (Builder $q): Builder => $q->where('division_id', $employee->division_id))
            ->when($employee->division_id === null, static fn (Builder $q): Builder => $q->where('department_id', $employee->department_id))
            ->orderByRaw("CASE period_type WHEN 'weekly' THEN 0 WHEN 'monthly' THEN 1 ELSE 2 END")
            ->orderByDesc('end_date')
            ->get();

        // Daftar pekerjaan harian saya.
        $mySubmissions = DailyWorkSubmission::query()
            ->where('employee_id', $employee->id)
            ->with(['divisionTarget.division', 'reviewer'])
            ->latest('work_date')
            ->latest('id')
            ->paginate(10, ['*'], 'my_page')
            ->withQueryString();

        // Daftar pekerjaan anggota divisi/tim yang perlu dikonfirmasi Ketua Divisi (atau atasan terkait).
        $canReviewTeam = $isDivisionHead || $isDeptManager || $isAdminOrHr;
        $teamSubmissions = collect();

        if ($canReviewTeam) {
            $teamSubmissions = DailyWorkSubmission::query()
                ->with(['employee.user', 'employee.division', 'employee.department', 'divisionTarget', 'reviewer'])
                ->where('employee_id', '!=', $employee->id)
                ->when($isDivisionHead && $employee->division_id !== null, static function (Builder $q) use ($employee): void {
                    $q->whereHas('employee', static fn (Builder $e): Builder => $e->where('division_id', $employee->division_id));
                })
                ->when($isDeptManager, static function (Builder $q) use ($employee): void {
                    $q->whereHas('employee', static fn (Builder $e): Builder => $e->where('department_id', $employee->department_id));
                })
                ->when($selectedStatus !== null, static fn (Builder $q): Builder => $q->where('status', $selectedStatus->value))
                ->orderByRaw("CASE WHEN status = 'pending_review' THEN 0 ELSE 1 END")
                ->latest('work_date')
                ->latest('id')
                ->paginate(15, ['*'], 'team_page')
                ->withQueryString();
        }

        return view('work.index', [
            'employee' => $employee,
            'availableTargets' => $availableTargets,
            'mySubmissions' => $mySubmissions,
            'teamSubmissions' => $teamSubmissions,
            'canReviewTeam' => $canReviewTeam,
            'statuses' => WorkSubmissionStatus::cases(),
            'selectedStatus' => $selectedStatus,
            'todayWib' => $todayWib,
            'driveConfigured' => $driveService->isConfigured(),
            'driveRootFolderId' => $driveService->rootFolderId(),
            'driveServiceEmail' => $driveService->serviceAccountEmail(),
            'timezone' => $timezone,
        ]);
    }

    public function store(Request $request, GoogleDriveService $driveService): RedirectResponse
    {
        $user = $request->user();
        $user->loadMissing(['employee.department', 'employee.division']);
        $employee = $user->employee;

        if ($employee === null) {
            abort(403);
        }

        $validated = $request->validate([
            'division_target_id' => ['required', 'integer', Rule::exists(DivisionTarget::class, 'id')],
            'work_date' => ['required', 'date'],
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'contribution_value' => ['required', 'integer', 'min:1', 'max:100'],
            'work_file' => ['required', 'file', 'max:15360'],
        ]);

        $target = DivisionTarget::query()->findOrFail((int) $validated['division_target_id']);
        if ($target->department_id !== $employee->department_id) {
            abort(403);
        }

        $workDate = CarbonImmutable::parse((string) $validated['work_date']);
        $folderSegments = [
            $employee->department?->code.' - '.$employee->department?->name,
            $employee->division !== null ? $employee->division->code.' - '.$employee->division->name : 'Non-Divisi',
            $workDate->format('Y-m'),
        ];

        $customFileName = "{$employee->employee_number}_{$workDate->toDateString()}_{$validated['title']}";

        try {
            $driveResult = $driveService->uploadWorkFile(
                $request->file('work_file'),
                $folderSegments,
                $customFileName,
            );
        } catch (RuntimeException $e) {
            return redirect()
                ->back()
                ->withInput()
                ->withErrors(['work_file' => $e->getMessage()]);
        }

        DailyWorkSubmission::query()->create([
            'employee_id' => $employee->id,
            'division_target_id' => $target->id,
            'work_date' => $workDate->toDateString(),
            'title' => trim((string) $validated['title']),
            'description' => ! empty($validated['description']) ? trim((string) $validated['description']) : null,
            'contribution_value' => (int) $validated['contribution_value'],
            'drive_file_id' => $driveResult['id'],
            'drive_file_name' => $driveResult['name'],
            'drive_web_view_link' => $driveResult['webViewLink'],
            'drive_folder_path' => $driveResult['folderPath'],
            'file_size_bytes' => $driveResult['size'],
            'mime_type' => $driveResult['mimeType'],
            'status' => WorkSubmissionStatus::PENDING_REVIEW,
        ]);

        return redirect()
            ->route('work.index')
            ->with('status', 'Kerjaan harian berhasil diunggah ke Google Drive PT dan menunggu konfirmasi Ketua Divisi.');
    }

    public function approve(Request $request, DailyWorkSubmission $submission): RedirectResponse
    {
        $this->authorizeReview($request, $submission);

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');

        DB::transaction(function () use ($request, $submission, $validated, $timezone): void {
            $locked = DailyWorkSubmission::query()
                ->whereKey($submission->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->update([
                'status' => WorkSubmissionStatus::APPROVED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => CarbonImmutable::now($timezone),
                'review_note' => ! empty($validated['review_note']) ? trim((string) $validated['review_note']) : 'Hasil kerja cocok dan disetujui.',
            ]);

            if ($locked->division_target_id !== null) {
                $target = DivisionTarget::query()
                    ->whereKey($locked->division_target_id)
                    ->lockForUpdate()
                    ->first();

                $target?->recalculateProgress();
            }
        });

        return redirect()
            ->back()
            ->with('status', 'Kerjaan harian dicentang cocok dan persentase progress target divisi telah bertambah.');
    }

    public function requestRevision(Request $request, DailyWorkSubmission $submission): RedirectResponse
    {
        $this->authorizeReview($request, $submission);

        $validated = $request->validate([
            'review_note' => ['required', 'string', 'max:500'],
        ]);

        $timezone = (string) config('attendance.timezone', 'Asia/Jakarta');

        DB::transaction(function () use ($request, $submission, $validated, $timezone): void {
            $locked = DailyWorkSubmission::query()
                ->whereKey($submission->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->update([
                'status' => WorkSubmissionStatus::REVISION_REQUESTED,
                'reviewed_by' => $request->user()->id,
                'reviewed_at' => CarbonImmutable::now($timezone),
                'review_note' => trim((string) $validated['review_note']),
            ]);

            if ($locked->division_target_id !== null) {
                $target = DivisionTarget::query()
                    ->whereKey($locked->division_target_id)
                    ->lockForUpdate()
                    ->first();

                $target?->recalculateProgress();
            }
        });

        return redirect()
            ->back()
            ->with('status', 'Kerjaan dikembalikan kepada karyawan dengan catatan revisi.');
    }

    private function authorizeReview(Request $request, DailyWorkSubmission $submission): void
    {
        $actor = $request->user();
        $actor->loadMissing('employee');
        $submission->loadMissing('employee');

        if ($submission->employee->user_id === $actor->id) {
            abort(403, 'Anda tidak dapat mengonfirmasi pekerjaan Anda sendiri.');
        }

        if ($actor->hasAnyRole([RoleName::SUPER_ADMIN->value, RoleName::HR_MANAGER->value])) {
            return;
        }

        if ($actor->hasRole(RoleName::DIVISION_HEAD->value)
            && $actor->employee !== null
            && $actor->employee->division_id !== null
            && $actor->employee->division_id === $submission->employee->division_id) {
            return;
        }

        if ($actor->hasRole(RoleName::DEPT_MANAGER->value)
            && $actor->employee !== null
            && $actor->employee->department_id === $submission->employee->department_id) {
            return;
        }

        abort(403);
    }
}
