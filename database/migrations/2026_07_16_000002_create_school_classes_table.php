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
        // Named `school_classes`, not `classes`: `class` is a reserved word in PHP and
        // makes for an awkward model name. See ADD § 2.1.
        Schema::create('school_classes', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->foreignUuid('department_id')
                ->constrained('departments')
                ->restrictOnDelete();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();

            $table->index('name');
        });

        // Two classes in one department can not share a name; the same name across
        // different departments is fine (RPL "XII A" vs TKJ "XII A"). Partial, so a
        // soft-deleted class does not reserve its name forever.
        DB::statement(
            'CREATE UNIQUE INDEX school_classes_department_id_name_unique
             ON school_classes (department_id, name) WHERE deleted_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('school_classes');
    }
};
