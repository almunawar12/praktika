<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * report_exports_type_check mirrors ReportType at the DB level (see
 * 2026_07_16_000016_create_report_exports_table.php) — every new case needs its
 * own migration to widen the constraint, or inserts fail even though the enum
 * itself accepts the value.
 */
return new class extends Migration
{
    public function up(): void
    {
        $types = implode("','", ['attendance', 'journal', 'assessment', 'monitoring', 'student_summary']);

        DB::statement('ALTER TABLE report_exports DROP CONSTRAINT report_exports_type_check');
        DB::statement("ALTER TABLE report_exports ADD CONSTRAINT report_exports_type_check
             CHECK (type IN ('{$types}'))");
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE report_exports DROP CONSTRAINT report_exports_type_check');
        DB::statement("ALTER TABLE report_exports ADD CONSTRAINT report_exports_type_check
             CHECK (type IN ('attendance','journal','assessment','monitoring'))");
    }
};
