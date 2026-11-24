<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table): void {
            $table->index(['work_date', 'status'], 'attendances_work_date_status_idx');
        });

        Schema::table('daily_work_submissions', function (Blueprint $table): void {
            $table->index(['work_date', 'status'], 'daily_work_date_status_idx');
            $table->index(['division_target_id', 'status'], 'daily_work_target_status_idx');
        });

        Schema::table('division_targets', function (Blueprint $table): void {
            $table->index(['start_date', 'end_date', 'period_type'], 'division_targets_range_period_idx');
        });
    }

    public function down(): void
    {
        Schema::table('division_targets', function (Blueprint $table): void {
            $table->dropIndex('division_targets_range_period_idx');
        });

        Schema::table('daily_work_submissions', function (Blueprint $table): void {
            $table->dropIndex('daily_work_target_status_idx');
            $table->dropIndex('daily_work_date_status_idx');
        });

        Schema::table('attendances', function (Blueprint $table): void {
            $table->dropIndex('attendances_work_date_status_idx');
        });
    }
};
