<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('placements', function (Blueprint $table): void {
            // Nullable for the same reason as school_classes.academic_year_id: rows
            // predating this column, and any placement an admin leaves unscoped, stay
            // valid. `restrictOnDelete` because AcademicYearPolicy explicitly blocks
            // deleting a year that still has placements — the FK is the hard backstop
            // behind that check.
            $table->foreignUuid('academic_year_id')
                ->nullable()
                ->after('teacher_id')
                ->constrained('academic_years')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('placements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('academic_year_id');
        });
    }
};
