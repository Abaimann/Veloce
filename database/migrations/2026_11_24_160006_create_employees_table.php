<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employees', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained('users')->restrictOnDelete();
            $table->string('employee_number', 50)->unique();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->foreignId('division_id')->nullable()->constrained('divisions')->nullOnDelete();
            $table->foreignId('supervisor_id')->nullable()->constrained('employees')->nullOnDelete();
            $table->string('employment_type', 32);
            $table->string('position_title')->nullable();
            $table->foreignId('work_schedule_id')->constrained('work_schedules')->restrictOnDelete();
            $table->date('join_date');
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(['department_id', 'division_id', 'is_active']);
            $table->index(['supervisor_id']);
            $table->index(['employment_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employees');
    }
};
