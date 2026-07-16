<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Lands here rather than with the Sistem module because ADR-03 puts the
        // assessment weights in the database: a school must be able to change 60/40
        // without a deploy. The management UI is still Fase 10.
        Schema::create('settings', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('key')->unique();
            // jsonb so a value can be a number, a string or a structure without a
            // migration each time a new setting appears.
            $table->jsonb('value')->nullable();
            $table->string('group')->default('general');
            $table->timestamps();

            $table->index('group');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
