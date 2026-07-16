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
        Schema::create('company_supervisors', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Like teachers and students, a supervisor owns a login: Praktika has no
            // public registration, so this module is the only way they get an account.
            $table->foreignUuid('user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUuid('company_id')->constrained('companies')->restrictOnDelete();
            $table->string('name');
            $table->string('position')->nullable();
            $table->string('phone')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->index('company_id');
            $table->index('name');
        });

        // One account belongs to one supervisor. Partial, so a soft-deleted row does
        // not reserve the account forever.
        DB::statement(
            'CREATE UNIQUE INDEX company_supervisors_user_id_unique
             ON company_supervisors (user_id) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('company_supervisors');
    }
};
