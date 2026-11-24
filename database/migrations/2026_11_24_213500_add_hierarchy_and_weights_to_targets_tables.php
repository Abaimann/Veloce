<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('department_targets', function (Blueprint $table): void {
            $table->decimal('progress_percent', 5, 2)->default(0)->after('unit_label');
        });

        Schema::table('division_targets', function (Blueprint $table): void {
            $table->foreignId('parent_division_target_id')
                ->nullable()
                ->after('department_target_id')
                ->constrained('division_targets')
                ->nullOnDelete();
            $table->decimal('weight_percent', 5, 2)->default(100)->after('progress_percent');
        });
    }

    public function down(): void
    {
        Schema::table('division_targets', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('parent_division_target_id');
            $table->dropColumn('weight_percent');
        });

        Schema::table('department_targets', function (Blueprint $table): void {
            $table->dropColumn('progress_percent');
        });
    }
};
