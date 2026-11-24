<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table): void {
            $table->id();
            $table->string('code', 40)->unique();
            $table->string('name');
            $table->string('attendance_status', 32);
            $table->boolean('is_paid')->default(true);
            $table->boolean('requires_attachment')->default(false);
            $table->boolean('deducts_balance')->default(false);
            $table->smallInteger('hr_approval_after_days')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_types');
    }
};
