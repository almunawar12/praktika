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
        Schema::create('final_scores', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('placement_id')->constrained('placements')->restrictOnDelete();
            $table->decimal('industry_score', 5, 2);
            $table->decimal('teacher_score', 5, 2);
            // Snapshot, not a lookup (ADR-03). Reading the weights from `settings` at
            // display time would mean changing 60/40 for next year silently rewrites
            // what last year's scores meant.
            $table->decimal('industry_weight', 4, 3);
            $table->decimal('teacher_weight', 4, 3);
            $table->decimal('final_score', 5, 2);
            $table->string('grade', 1);
            $table->timestampTz('published_at')->nullable();
            $table->foreignUuid('published_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            // One result per placement.
            $table->unique('placement_id');
            $table->index('published_at');
        });

        DB::statement(
            'ALTER TABLE final_scores ADD CONSTRAINT final_scores_range_check CHECK (
                industry_score BETWEEN 0 AND 100
                AND teacher_score BETWEEN 0 AND 100
                AND final_score BETWEEN 0 AND 100
            )'
        );

        // The weights that produced this score must be a complete split, or the
        // stored final_score can not be reproduced from its own inputs.
        DB::statement(
            'ALTER TABLE final_scores ADD CONSTRAINT final_scores_weights_check
             CHECK (industry_weight + teacher_weight = 1)'
        );

        DB::statement(
            "ALTER TABLE final_scores ADD CONSTRAINT final_scores_grade_check
             CHECK (grade IN ('A','B','C','D','E'))"
        );

        // A publication must name who published it.
        DB::statement(
            'ALTER TABLE final_scores ADD CONSTRAINT final_scores_publisher_check
             CHECK (published_at IS NULL OR published_by IS NOT NULL)'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('final_scores');
    }
};
