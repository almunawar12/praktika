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
        Schema::table('companies', function (Blueprint $table): void {
            // Nullable because companies already exist without coordinates. A company
            // with no coordinates simply has no geofence — see AttendanceService.
            $table->decimal('latitude', 10, 7)->nullable()->after('address');
            $table->decimal('longitude', 10, 7)->nullable()->after('latitude');
            $table->unsignedSmallInteger('geofence_radius_m')->nullable()->after('longitude');
        });

        // Latitude and longitude are meaningless alone; either both are set or neither.
        DB::statement(
            'ALTER TABLE companies ADD CONSTRAINT companies_coordinates_check CHECK (
                (latitude IS NULL AND longitude IS NULL)
                OR (latitude IS NOT NULL AND longitude IS NOT NULL)
            )'
        );

        DB::statement(
            'ALTER TABLE companies ADD CONSTRAINT companies_latitude_range_check
             CHECK (latitude IS NULL OR (latitude BETWEEN -90 AND 90))'
        );

        DB::statement(
            'ALTER TABLE companies ADD CONSTRAINT companies_longitude_range_check
             CHECK (longitude IS NULL OR (longitude BETWEEN -180 AND 180))'
        );
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE companies DROP CONSTRAINT IF EXISTS companies_coordinates_check');
        DB::statement('ALTER TABLE companies DROP CONSTRAINT IF EXISTS companies_latitude_range_check');
        DB::statement('ALTER TABLE companies DROP CONSTRAINT IF EXISTS companies_longitude_range_check');

        Schema::table('companies', function (Blueprint $table): void {
            $table->dropColumn(['latitude', 'longitude', 'geofence_radius_m']);
        });
    }
};
