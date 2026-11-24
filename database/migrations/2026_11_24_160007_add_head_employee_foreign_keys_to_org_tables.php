<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('departments', function (Blueprint $table): void {
            $table->foreignId('head_employee_id')->nullable()->after('name')->constrained('employees')->nullOnDelete();
        });

        Schema::table('divisions', function (Blueprint $table): void {
            $table->foreignId('head_employee_id')->nullable()->after('name')->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('divisions', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('head_employee_id');
        });

        Schema::table('departments', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('head_employee_id');
        });
    }
};
