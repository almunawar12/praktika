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
        Schema::create('teachers', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Every teacher owns a login: Praktika has no public registration, so this
            // module is the only way a Guru Pembimbing can get an account at all.
            $table->foreignUuid('user_id')
                ->constrained('users')
                ->restrictOnDelete();
            $table->string('nip');
            $table->string('name');
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        DB::statement(
            'CREATE UNIQUE INDEX teachers_nip_unique ON teachers (nip) WHERE deleted_at IS NULL'
        );
        DB::statement(
            'CREATE UNIQUE INDEX teachers_user_id_unique ON teachers (user_id) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('teachers');
    }
};
