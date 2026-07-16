<?php

declare(strict_types=1);

use App\Enums\AssessmentCriteria;
use App\Enums\AssessmentType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('assessments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('placement_id')->constrained('placements')->restrictOnDelete();
            $table->string('type');
            $table->foreignUuid('assessor_id')->constrained('users')->restrictOnDelete();
            // Cached from assessment_scores so recaps and the final-score calculation
            // do not re-average nine rows per placement.
            $table->decimal('average_score', 5, 2)->nullable();
            $table->text('note')->nullable();
            // Null means draft: the assessor is still filling it in. Once set, the
            // assessment is locked and counts toward the final score.
            $table->timestampTz('submitted_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['placement_id', 'type']);
            $table->index('submitted_at');
        });

        $types = implode("','", AssessmentType::values());
        DB::statement("ALTER TABLE assessments ADD CONSTRAINT assessments_type_check CHECK (type IN ('{$types}'))");

        DB::statement(
            'ALTER TABLE assessments ADD CONSTRAINT assessments_average_range_check
             CHECK (average_score IS NULL OR (average_score BETWEEN 0 AND 100))'
        );

        // One assessment per side per placement. Partial, so a soft-deleted one does
        // not block re-assessing.
        DB::statement(
            'CREATE UNIQUE INDEX assessments_placement_id_type_unique
             ON assessments (placement_id, type) WHERE deleted_at IS NULL'
        );

        Schema::create('assessment_scores', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Cascade: a score has no meaning without its assessment, and the two are
            // always written together.
            $table->foreignUuid('assessment_id')->constrained('assessments')->cascadeOnDelete();
            $table->string('criteria_code');
            $table->decimal('score', 5, 2);
            $table->timestamps();

            $table->unique(['assessment_id', 'criteria_code']);
        });

        $criteria = implode("','", AssessmentCriteria::values());
        DB::statement("ALTER TABLE assessment_scores ADD CONSTRAINT assessment_scores_criteria_check CHECK (criteria_code IN ('{$criteria}'))");

        // PRD § 4: skala 0–100.
        DB::statement(
            'ALTER TABLE assessment_scores ADD CONSTRAINT assessment_scores_range_check
             CHECK (score BETWEEN 0 AND 100)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('assessment_scores');
        Schema::dropIfExists('assessments');
    }
};
