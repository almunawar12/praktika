<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('report_exports', function (Blueprint $table): void {
            $table->uuid('id')->primary();

            // The owner. A report is only ever downloaded by the person who asked
            // for it (ADD § 9), so this is the whole access-control story.
            $table->foreignUuid('requested_by')->constrained('users')->cascadeOnDelete();

            $table->string('type');
            $table->string('format');
            $table->string('status')->default('pending');

            // The scope the report was run with (period, department, company).
            // Kept so the history row is self-describing and a report can be re-run.
            $table->jsonb('filters')->default('{}');

            // Populated once the queue job finishes. Null until then.
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->timestampTz('completed_at')->nullable();

            // Why it failed, surfaced to the owner so they can fix the filters or retry.
            $table->text('error')->nullable();

            $table->timestamps();

            $table->index(['requested_by', 'created_at']);
        });

        // Frozen as literals: a migration is a snapshot of the schema at its own
        // moment, and the enums it was written against may change or go away.
        $types = implode("','", ['attendance', 'journal', 'assessment', 'monitoring']);
        $formats = implode("','", ['pdf', 'xlsx']);
        $statuses = implode("','", ['pending', 'processing', 'completed', 'failed']);

        DB::statement("ALTER TABLE report_exports ADD CONSTRAINT report_exports_type_check
             CHECK (type IN ('{$types}'))");
        DB::statement("ALTER TABLE report_exports ADD CONSTRAINT report_exports_format_check
             CHECK (format IN ('{$formats}'))");
        DB::statement("ALTER TABLE report_exports ADD CONSTRAINT report_exports_status_check
             CHECK (status IN ('{$statuses}'))");

        // A completed report must have a file to hand back; anything else must not
        // pretend it has one.
        DB::statement("ALTER TABLE report_exports ADD CONSTRAINT report_exports_file_check
             CHECK (
                (status = 'completed' AND file_path IS NOT NULL)
                OR (status <> 'completed' AND file_path IS NULL)
             )");
    }

    public function down(): void
    {
        Schema::dropIfExists('report_exports');
    }
};
