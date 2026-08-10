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
        Schema::create('academic_years', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('name');
            $table->date('start_date');
            $table->date('end_date');
            $table->boolean('is_active')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        // A soft-deleted year must not block reusing its name — mirrors the
        // department/school-class partial unique pattern.
        DB::statement(
            'CREATE UNIQUE INDEX academic_years_name_unique
             ON academic_years (name) WHERE deleted_at IS NULL'
        );

        DB::statement('ALTER TABLE academic_years ADD CONSTRAINT academic_years_dates_check CHECK (end_date >= start_date)');

        // The real guarantee behind "only one academic year is current". Validation
        // in the service can be raced by two concurrent requests; this cannot.
        DB::statement(
            'CREATE UNIQUE INDEX academic_years_one_active
             ON academic_years (is_active)
             WHERE is_active = true AND deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('academic_years');
    }
};
