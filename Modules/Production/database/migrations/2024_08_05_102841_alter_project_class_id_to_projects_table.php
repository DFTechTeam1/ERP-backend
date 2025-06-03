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
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('classification');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->integer('project_class_id')->nullable();

            $table->string('classification')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('classification');
        });

        $classifications = ['s', 'a', 'b', 'c', 'd'];

        Schema::table('projects', function (Blueprint $table) use ($classifications) {
            $table->dropColumn('project_class_id');

            $table->enum('classification', $classifications)->nullable();
        });
    }
};
