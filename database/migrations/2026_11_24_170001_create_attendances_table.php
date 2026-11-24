<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->restrictOnDelete();
            $table->date('work_date');

            $table->timestampTz('check_in_at')->nullable();
            $table->timestampTz('check_out_at')->nullable();

            $table->decimal('check_in_lat', 10, 7)->nullable();
            $table->decimal('check_in_lng', 10, 7)->nullable();
            $table->decimal('check_in_accuracy', 8, 2)->nullable();
            $table->decimal('check_in_distance_meters', 10, 2)->nullable();

            $table->decimal('check_out_lat', 10, 7)->nullable();
            $table->decimal('check_out_lng', 10, 7)->nullable();
            $table->decimal('check_out_accuracy', 8, 2)->nullable();
            $table->decimal('check_out_distance_meters', 10, 2)->nullable();

            $table->foreignId('office_location_id')->nullable()->constrained('office_locations')->nullOnDelete();

            $table->string('selfie_in_path')->nullable();
            $table->string('selfie_out_path')->nullable();
            $table->string('selfie_in_hash', 64)->nullable();
            $table->string('selfie_out_hash', 64)->nullable();

            $table->timestampTz('client_check_in_at')->nullable();
            $table->timestampTz('client_check_out_at')->nullable();

            $table->string('status', 32);
            $table->unsignedBigInteger('leave_request_id')->nullable();
            $table->jsonb('flags')->default('[]');
            $table->timestampTz('selfie_pruned_at')->nullable();
            $table->timestampsTz();

            $table->unique(['employee_id', 'work_date']);
            $table->index(['work_date', 'status']);
            $table->index('selfie_in_hash');
            $table->index('selfie_out_hash');
            $table->index('leave_request_id');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('CREATE INDEX attendances_flags_gin_idx ON attendances USING gin (flags)');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
