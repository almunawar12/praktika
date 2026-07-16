<?php

declare(strict_types=1);

use App\Enums\AttendanceStatus;
use App\Enums\VerificationStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('placement_id')->constrained('placements')->restrictOnDelete();
            $table->date('date');
            $table->string('status');

            $table->timestampTz('check_in_at')->nullable();
            $table->decimal('check_in_latitude', 10, 7)->nullable();
            $table->decimal('check_in_longitude', 10, 7)->nullable();
            $table->string('check_in_photo_path')->nullable();
            $table->unsignedInteger('check_in_distance_m')->nullable();

            $table->timestampTz('check_out_at')->nullable();
            $table->decimal('check_out_latitude', 10, 7)->nullable();
            $table->decimal('check_out_longitude', 10, 7)->nullable();
            $table->string('check_out_photo_path')->nullable();
            $table->unsignedInteger('check_out_distance_m')->nullable();

            $table->text('notes')->nullable();

            $table->string('verification_status')->default(VerificationStatus::Pending->value);
            $table->foreignUuid('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at')->nullable();
            $table->text('verification_note')->nullable();

            $table->timestamps();

            // The only reliable defence against a double check-in: two taps on a flaky
            // mobile connection both pass an application-level check.
            $table->unique(['placement_id', 'date']);

            $table->index(['placement_id', 'date']);
            $table->index('verification_status');
            $table->index(['date', 'status']);
        });

        $statuses = implode("','", AttendanceStatus::values());
        DB::statement("ALTER TABLE attendances ADD CONSTRAINT attendances_status_check CHECK (status IN ('{$statuses}'))");

        $verifications = implode("','", VerificationStatus::values());
        DB::statement("ALTER TABLE attendances ADD CONSTRAINT attendances_verification_status_check CHECK (verification_status IN ('{$verifications}'))");

        // Checking out before checking in is not a thing that happens.
        DB::statement(
            'ALTER TABLE attendances ADD CONSTRAINT attendances_checkout_after_checkin_check
             CHECK (check_out_at IS NULL OR (check_in_at IS NOT NULL AND check_out_at >= check_in_at))'
        );

        // `present` is a claim about being somewhere: it must carry the evidence.
        DB::statement(
            "ALTER TABLE attendances ADD CONSTRAINT attendances_present_requires_check_in_check
             CHECK (
                 status <> 'present'
                 OR (check_in_at IS NOT NULL AND check_in_photo_path IS NOT NULL
                     AND check_in_latitude IS NOT NULL AND check_in_longitude IS NOT NULL)
             )"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
