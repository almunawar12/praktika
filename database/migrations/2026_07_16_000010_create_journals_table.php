<?php

declare(strict_types=1);

use App\Enums\ReviewStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('journals', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('placement_id')->constrained('placements')->restrictOnDelete();
            $table->date('date');

            $table->text('activity');
            $table->text('work_result');
            $table->text('obstacle')->nullable();

            // Derived from the two stages by JournalService::resolveFinalStatus();
            // stored so lists and recaps can filter without recomputing per row.
            $table->string('status')->default(ReviewStatus::Pending->value);

            $table->string('industry_status')->default(ReviewStatus::Pending->value);
            $table->foreignUuid('industry_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('industry_reviewed_at')->nullable();
            $table->text('industry_note')->nullable();

            $table->string('teacher_status')->default(ReviewStatus::Pending->value);
            $table->foreignUuid('teacher_reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('teacher_reviewed_at')->nullable();
            $table->text('teacher_note')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['placement_id', 'date']);
            $table->index('status');
            $table->index('industry_status');
            $table->index('teacher_status');
        });

        // One journal per day. Partial, so a soft-deleted journal does not block the
        // student from writing that day again.
        DB::statement(
            'CREATE UNIQUE INDEX journals_placement_id_date_unique
             ON journals (placement_id, date) WHERE deleted_at IS NULL'
        );

        foreach (['status', 'industry_status', 'teacher_status'] as $column) {
            $allowed = implode("','", ReviewStatus::values());
            DB::statement("ALTER TABLE journals ADD CONSTRAINT journals_{$column}_check CHECK ({$column} IN ('{$allowed}'))");
        }

        // The PRD order is Industri → Guru: a teacher decision can only exist once
        // industry approved. Without this, a bypass could record a teacher approval
        // on a journal the company never signed off.
        DB::statement(
            "ALTER TABLE journals ADD CONSTRAINT journals_teacher_requires_industry_check
             CHECK (teacher_status = 'pending' OR industry_status = 'approved')"
        );

        // A decision must name its reviewer.
        DB::statement(
            "ALTER TABLE journals ADD CONSTRAINT journals_industry_reviewer_check
             CHECK (industry_status = 'pending' OR industry_reviewed_by IS NOT NULL)"
        );
        DB::statement(
            "ALTER TABLE journals ADD CONSTRAINT journals_teacher_reviewer_check
             CHECK (teacher_status = 'pending' OR teacher_reviewed_by IS NOT NULL)"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('journals');
    }
};
