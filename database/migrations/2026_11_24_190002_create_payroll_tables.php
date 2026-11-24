<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_periods', function (Blueprint $table): void {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('label', 64);
            $table->string('status', 32)->default('draft');
            $table->foreignId('generated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('generated_at')->nullable();
            $table->timestampTz('finalized_at')->nullable();
            $table->timestampsTz();

            $table->unique(['year', 'month']);
        });

        Schema::create('payroll_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('payroll_period_id')->constrained('payroll_periods')->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->unsignedTinyInteger('workdays_in_month');
            $table->unsignedTinyInteger('present_days')->default(0);
            $table->unsignedTinyInteger('late_days')->default(0);
            $table->unsignedInteger('late_minutes_total')->default(0);
            $table->unsignedTinyInteger('wfh_days')->default(0);
            $table->unsignedTinyInteger('paid_leave_days')->default(0);
            $table->unsignedTinyInteger('unpaid_leave_days')->default(0);
            $table->unsignedTinyInteger('absent_days')->default(0);
            $table->unsignedBigInteger('base_salary')->default(0);
            $table->unsignedBigInteger('fixed_allowance')->default(0);
            $table->unsignedBigInteger('late_deduction')->default(0);
            $table->unsignedBigInteger('absence_deduction')->default(0);
            $table->unsignedBigInteger('net_salary')->default(0);
            $table->jsonb('calculation_breakdown')->default('{}');
            $table->timestampsTz();

            $table->unique(['payroll_period_id', 'employee_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_periods');
    }
};
