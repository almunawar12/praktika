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
        Schema::create('departments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('code');
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        // Partial unique index rather than a plain UNIQUE: soft-deleted rows keep
        // occupying a normal unique constraint, so deleting "RPL" would permanently
        // block ever creating "RPL" again.
        DB::statement(
            'CREATE UNIQUE INDEX departments_code_unique ON departments (code) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('departments');
    }
};
