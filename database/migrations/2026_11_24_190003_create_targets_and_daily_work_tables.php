<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('department_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedInteger('target_value')->default(100);
            $table->string('unit_label', 32)->default('pekerjaan');
            $table->string('status', 32)->default('active');
            $table->timestampsTz();

            $table->index(['department_id', 'year', 'month']);
        });

        Schema::create('division_targets', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('division_id')->constrained('divisions')->restrictOnDelete();
            $table->foreignId('department_target_id')->nullable()->constrained('department_targets')->nullOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('period_type', 16);
            $table->date('start_date');
            $table->date('end_date');
            $table->unsignedInteger('target_value')->default(1);
            $table->unsignedInteger('approved_value')->default(0);
            $table->decimal('progress_percent', 5, 2)->default(0);
            $table->string('unit_label', 32)->default('pekerjaan');
            $table->string('status', 32)->default('active');
            $table->timestampsTz();

            $table->index(['division_id', 'period_type', 'start_date']);
            $table->index(['department_id', 'start_date', 'end_date']);
        });

        Schema::create('daily_work_submissions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('division_target_id')->nullable()->constrained('division_targets')->nullOnDelete();
            $table->date('work_date');
            $table->string('title');
            $table->text('description')->nullable();
            $table->unsignedInteger('contribution_value')->default(1);
            $table->string('drive_file_id')->nullable();
            $table->string('drive_file_name')->nullable();
            $table->text('drive_web_view_link')->nullable();
            $table->string('drive_folder_path')->nullable();
            $table->unsignedBigInteger('file_size_bytes')->nullable();
            $table->string('mime_type', 128)->nullable();
            $table->string('status', 32)->default('pending_review');
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->text('review_note')->nullable();
            $table->timestampsTz();

            $table->index(['employee_id', 'work_date']);
            $table->index(['division_target_id', 'status']);
            $table->index(['status', 'work_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('daily_work_submissions');
        Schema::dropIfExists('division_targets');
        Schema::dropIfExists('department_targets');
    }
};
