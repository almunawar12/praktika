<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Polymorphic (ADD § ADR-06): journal and monitoring documentation are the
        // same thing — one table, one policy, one upload service.
        Schema::create('attachments', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            // Also creates the (attachable_type, attachable_id) index — the parent
            // decides who may read a file, so lookups always start there.
            $table->uuidMorphs('attachable');
            $table->foreignUuid('uploaded_by')->constrained('users')->restrictOnDelete();
            $table->string('disk');
            $table->string('path');
            $table->string('original_name');
            $table->string('mime_type');
            $table->unsignedBigInteger('size');
            $table->string('caption')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attachments');
    }
};
