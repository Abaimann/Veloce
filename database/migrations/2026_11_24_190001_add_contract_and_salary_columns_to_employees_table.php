<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->date('contract_start_date')->nullable()->after('join_date');
            $table->date('contract_end_date')->nullable()->after('contract_start_date');
            $table->unsignedBigInteger('base_salary')->default(0)->after('contract_end_date');
            $table->unsignedBigInteger('fixed_allowance')->default(0)->after('base_salary');

            $table->index('contract_end_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table): void {
            $table->dropIndex(['contract_end_date']);
            $table->dropColumn([
                'contract_start_date',
                'contract_end_date',
                'base_salary',
                'fixed_allowance',
            ]);
        });
    }
};
