<?php

declare(strict_types=1);

use App\Http\Controllers\Approval\ApprovalInboxController;
use App\Http\Controllers\Attendance\AttendanceController;
use App\Http\Controllers\Auth\AcceptInvitationController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Leave\LeaveRequestController;
use App\Http\Controllers\Management\ActivityLogController;
use App\Http\Controllers\Management\EmployeeManagementController;
use App\Http\Controllers\Management\MasterDataController;
use App\Http\Controllers\Management\TeamAttendanceController;
use App\Http\Controllers\Media\PrivateMediaController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\Payroll\PayrollController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\TimelineController;
use App\Http\Controllers\Work\DailyWorkSubmissionController;
use App\Http\Controllers\Work\TargetController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return Auth::check()
        ? redirect()->route('dashboard')
        : redirect()->route('login');
});

Route::middleware('guest')->group(function (): void {
    Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])
        ->middleware('throttle:login')
        ->name('login.store');

    Route::get('/undangan/{invitation}', [AcceptInvitationController::class, 'show'])->name('invitations.show');
    Route::post('/undangan/{invitation}', [AcceptInvitationController::class, 'store'])->name('invitations.store');
});

Route::middleware('auth')->group(function (): void {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    Route::prefix('profil')->name('profile.')->group(function (): void {
        Route::get('/', [ProfileController::class, 'show'])->name('show');
        Route::put('/kata-sandi', [ProfileController::class, 'updatePassword'])->name('password');
    });

    Route::prefix('notifikasi')->name('notifications.')->group(function (): void {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
        Route::post('/baca-semua', [NotificationController::class, 'markAllAsRead'])->name('read-all');
        Route::post('/{id}/baca', [NotificationController::class, 'markAsRead'])->name('read');
    });

    Route::prefix('absen')->name('attendance.')->group(function (): void {
        Route::get('/', [AttendanceController::class, 'camera'])->name('camera');
        Route::post('/masuk', [AttendanceController::class, 'checkIn'])
            ->middleware('throttle:attendance')
            ->name('check-in');
        Route::post('/pulang', [AttendanceController::class, 'checkOut'])
            ->middleware('throttle:attendance')
            ->name('check-out');
        Route::get('/riwayat', [AttendanceController::class, 'history'])->name('history');
    });

    Route::prefix('izin')->name('leave.')->group(function (): void {
        Route::get('/', [LeaveRequestController::class, 'index'])->name('index');
        Route::get('/ajukan', [LeaveRequestController::class, 'create'])->name('create');
        Route::post('/', [LeaveRequestController::class, 'store'])->name('store');
        Route::get('/{leaveRequest}', [LeaveRequestController::class, 'show'])->name('show');
        Route::post('/{leaveRequest}/batal', [LeaveRequestController::class, 'cancel'])->name('cancel');
    });

    Route::prefix('persetujuan')->name('approvals.')->group(function (): void {
        Route::get('/', [ApprovalInboxController::class, 'index'])->name('index');
        Route::post('/{leaveRequest}/setujui', [ApprovalInboxController::class, 'approve'])->name('approve');
        Route::post('/{leaveRequest}/tolak', [ApprovalInboxController::class, 'reject'])->name('reject');
    });

    Route::get('/media/absensi/{attendance}/selfie/{phase}', [PrivateMediaController::class, 'selfie'])
        ->whereIn('phase', ['in', 'out'])
        ->name('media.attendance.selfie');
    Route::get('/media/izin/{leaveRequest}/lampiran', [PrivateMediaController::class, 'leaveAttachment'])
        ->name('media.leave.attachment');

    Route::get('/timeline', TimelineController::class)->name('timeline.index');

    Route::prefix('target')->name('targets.')->group(function (): void {
        Route::get('/', [TargetController::class, 'index'])->name('index');
        Route::post('/departemen', [TargetController::class, 'storeDepartmentTarget'])->name('department.store');
        Route::post('/divisi', [TargetController::class, 'storeDivisionTarget'])->name('division.store');
        Route::patch('/divisi/{divisionTarget}/bobot', [TargetController::class, 'updateWeight'])->name('division.weight');
    });

    Route::prefix('kerjaan-harian')->name('work.')->group(function (): void {
        Route::get('/', [DailyWorkSubmissionController::class, 'index'])->name('index');
        Route::post('/', [DailyWorkSubmissionController::class, 'store'])->name('store');
        Route::post('/{submission}/setujui', [DailyWorkSubmissionController::class, 'approve'])->name('approve');
        Route::post('/{submission}/revisi', [DailyWorkSubmissionController::class, 'requestRevision'])->name('revision');
    });

    Route::prefix('gaji')->name('payroll.')->group(function (): void {
        Route::get('/', [PayrollController::class, 'index'])->name('index');
        Route::post('/hitung-ulang', [PayrollController::class, 'recalculate'])->name('recalculate');
        Route::post('/{payrollPeriod}/final', [PayrollController::class, 'finalize'])->name('finalize');
    });

    Route::prefix('manajemen')->name('management.')->group(function (): void {
        Route::get('/absensi', [TeamAttendanceController::class, 'index'])->name('attendances.index');

        Route::get('/karyawan', [EmployeeManagementController::class, 'index'])->name('employees.index');
        Route::get('/karyawan/buat', [EmployeeManagementController::class, 'create'])->name('employees.create');
        Route::post('/karyawan', [EmployeeManagementController::class, 'store'])->name('employees.store');
        Route::get('/karyawan/{employee}/edit', [EmployeeManagementController::class, 'edit'])->name('employees.edit');
        Route::put('/karyawan/{employee}', [EmployeeManagementController::class, 'update'])->name('employees.update');
        Route::post('/karyawan/{employee}/undang-ulang', [EmployeeManagementController::class, 'regenerateInvitation'])
            ->name('employees.reinvite');

        Route::get('/log-aktivitas', [ActivityLogController::class, 'index'])->name('activity-logs.index');

        Route::get('/master-data', [MasterDataController::class, 'index'])->name('master.index');
        Route::post('/master-data/departemen', [MasterDataController::class, 'storeDepartment'])
            ->name('master.departments.store');
        Route::patch('/master-data/departemen/{department}/toggle', [MasterDataController::class, 'toggleDepartment'])
            ->name('master.departments.toggle');
        Route::post('/master-data/divisi', [MasterDataController::class, 'storeDivision'])
            ->name('master.divisions.store');
        Route::patch('/master-data/divisi/{division}/toggle', [MasterDataController::class, 'toggleDivision'])
            ->name('master.divisions.toggle');
        Route::post('/master-data/jadwal', [MasterDataController::class, 'storeWorkSchedule'])
            ->name('master.schedules.store');
        Route::patch('/master-data/jadwal/{workSchedule}/toggle', [MasterDataController::class, 'toggleWorkSchedule'])
            ->name('master.schedules.toggle');
        Route::post('/master-data/lokasi', [MasterDataController::class, 'storeOfficeLocation'])
            ->name('master.offices.store');
        Route::patch('/master-data/lokasi/{officeLocation}/toggle', [MasterDataController::class, 'toggleOfficeLocation'])
            ->name('master.offices.toggle');
        Route::post('/master-data/libur', [MasterDataController::class, 'storeHoliday'])
            ->name('master.holidays.store');
        Route::delete('/master-data/libur/{holiday}', [MasterDataController::class, 'destroyHoliday'])
            ->name('master.holidays.destroy');
    });
});
