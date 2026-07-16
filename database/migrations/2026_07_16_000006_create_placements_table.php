<?php

declare(strict_types=1);

use App\Enums\PlacementStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('placements', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('student_id')->constrained('students')->restrictOnDelete();
            $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();
            $table->foreignUuid('teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->string('status')->default(PlacementStatus::Draft->value);
            $table->date('start_date');
            $table->date('end_date');
            $table->text('cancel_reason')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['status', 'start_date']);
            $table->index('company_id');
            $table->index('teacher_id');
        });

        // String + CHECK rather than a native ENUM: adding a value to a PostgreSQL
        // ENUM later is painful and not cleanly reversible (ADD § 2.3).
        $allowed = implode("','", PlacementStatus::values());
        DB::statement("ALTER TABLE placements ADD CONSTRAINT placements_status_check CHECK (status IN ('{$allowed}'))");

        DB::statement('ALTER TABLE placements ADD CONSTRAINT placements_dates_check CHECK (end_date >= start_date)');

        // The real guarantee behind "one active PKL per student". Validation in the
        // service can be raced by two concurrent requests; this cannot.
        DB::statement(
            "CREATE UNIQUE INDEX placements_one_active_per_student
             ON placements (student_id)
             WHERE status = 'active' AND deleted_at IS NULL"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('placements');
    }
};
