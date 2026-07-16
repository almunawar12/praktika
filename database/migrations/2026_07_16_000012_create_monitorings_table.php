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
        Schema::create('monitorings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('placement_id')->constrained('placements')->restrictOnDelete();
            // Denormalised from the placement on purpose: a placement can be reassigned
            // to a different teacher, and a visit report must keep naming whoever
            // actually made the visit.
            $table->foreignUuid('teacher_id')->constrained('teachers')->restrictOnDelete();
            $table->date('visit_date');
            $table->text('note');
            $table->text('recommendation')->nullable();
            // Optional evidence of the visit — never a geofence: a teacher who forgot
            // to file on site must still be able to file from school.
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['placement_id', 'visit_date']);
            $table->index('teacher_id');
            $table->index('visit_date');
        });

        // Latitude and longitude are meaningless apart.
        DB::statement(
            'ALTER TABLE monitorings ADD CONSTRAINT monitorings_coordinates_check CHECK (
                (latitude IS NULL AND longitude IS NULL)
                OR (latitude IS NOT NULL AND longitude IS NOT NULL)
            )'
        );

        DB::statement(
            'ALTER TABLE monitorings ADD CONSTRAINT monitorings_latitude_range_check
             CHECK (latitude IS NULL OR (latitude BETWEEN -90 AND 90))'
        );

        DB::statement(
            'ALTER TABLE monitorings ADD CONSTRAINT monitorings_longitude_range_check
             CHECK (longitude IS NULL OR (longitude BETWEEN -180 AND 180))'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('monitorings');
    }
};
