<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('school_classes', function (Blueprint $table): void {
            // Nullable: classes created before this column existed, and any class an
            // admin deliberately leaves unscoped, stay valid. `nullOnDelete` because
            // AcademicYearPolicy only guards deletion against placements, not classes.
            $table->foreignUuid('academic_year_id')
                ->nullable()
                ->after('department_id')
                ->constrained('academic_years')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('school_classes', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('academic_year_id');
        });
    }
};
