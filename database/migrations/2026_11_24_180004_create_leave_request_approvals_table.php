<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_request_approvals', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('leave_request_id')->constrained('leave_requests')->cascadeOnDelete();
            $table->smallInteger('step');
            $table->string('role', 32);
            $table->foreignId('approver_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status', 32);
            $table->text('note')->nullable();
            $table->timestampTz('acted_at')->nullable();
            $table->timestampsTz();

            $table->unique(['leave_request_id', 'step']);
            $table->index(['role', 'status', 'approver_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_request_approvals');
    }
};
