<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table("project_tasks", function (Blueprint $table) {
            $table->string('task_identifier_id', 4)
                ->unique()
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table("project_tasks", function (Blueprint $table) {
            $table->dropColumn('task_identifier_id');
        });
    }
};
