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
        Schema::create('students', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->foreignUuid('class_id')
                ->constrained('school_classes')
                ->restrictOnDelete();
            $table->string('nis');
            $table->string('nisn');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        DB::statement(
            'CREATE UNIQUE INDEX students_nis_unique ON students (nis) WHERE deleted_at IS NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX students_nisn_unique ON students (nisn) WHERE deleted_at IS NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX students_user_id_unique ON students (user_id) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('students');
    }
};
