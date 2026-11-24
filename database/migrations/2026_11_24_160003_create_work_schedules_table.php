<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('work_schedules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->time('check_in_time');
            $table->time('check_out_time');
            $table->smallInteger('late_tolerance_minutes')->default(0);
            $table->jsonb('work_days');
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('work_schedules');
    }
};
