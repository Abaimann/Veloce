<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_requests', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->foreignId('leave_type_id')->constrained('leave_types')->restrictOnDelete();
            $table->date('start_date');
            $table->date('end_date');
            $table->smallInteger('total_work_days');
            $table->text('reason');
            $table->string('attachment_path')->nullable();
            $table->string('status', 32);
            $table->smallInteger('current_step')->default(1);
            $table->timestampTz('finalized_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();

            $table->index(['employee_id', 'status', 'start_date', 'end_date']);
            $table->index(['status', 'current_step']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_requests');
    }
};
