<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Visits now record the time they happened, not just the date.
        DB::statement(
            'ALTER TABLE monitorings ALTER COLUMN visit_date TYPE timestamp(0) USING visit_date::timestamp'
        );
    }

    public function down(): void
    {
        DB::statement(
            'ALTER TABLE monitorings ALTER COLUMN visit_date TYPE date USING visit_date::date'
        );
    }
};
