<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/**
 * Reports are no longer artefacts: they are read live from filters, so there is
 * nothing left to record or to keep on disk.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('report_exports');

        // The files were only ever reachable through the table that just went away.
        Storage::disk('local')->deleteDirectory('reports');
    }

    public function down(): void
    {
        Schema::create('report_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('requested_by')->constrained('users')->cascadeOnDelete();
            $table->string('type');
            $table->string('format');
            $table->string('status')->default('pending');
            $table->jsonb('filters')->default('{}');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();

            $table->index(['requested_by', 'created_at']);
        });

        // Literals, not enums: this table's shape is history now, and the enums it
        // was built from have already started to disappear (ReportStatus is gone).
        $types = implode("','", ['attendance', 'journal', 'assessment', 'monitoring', 'student_summary']);
        $formats = implode("','", ['pdf', 'xlsx']);
        $statuses = implode("','", ['pending', 'processing', 'completed', 'failed']);

        DB::statement("ALTER TABLE report_exports ADD CONSTRAINT report_exports_type_check
             CHECK (type IN ('{$types}'))");
        DB::statement("ALTER TABLE report_exports ADD CONSTRAINT report_exports_format_check
             CHECK (format IN ('{$formats}'))");
        DB::statement("ALTER TABLE report_exports ADD CONSTRAINT report_exports_status_check
             CHECK (status IN ('{$statuses}'))");
        DB::statement("ALTER TABLE report_exports ADD CONSTRAINT report_exports_file_check
             CHECK (
                (status = 'completed' AND file_path IS NOT NULL)
                OR (status <> 'completed' AND file_path IS NULL)
             )");
    }
};
