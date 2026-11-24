<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('divisions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('department_id')->constrained('departments')->restrictOnDelete();
            $table->string('code', 20);
            $table->string('name');
            $table->boolean('is_active')->default(true)->index();
            $table->timestampsTz();

            $table->unique(['department_id', 'code']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('divisions');
    }
};
