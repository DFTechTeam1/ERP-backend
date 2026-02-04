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
        Schema::table('development_task_proof_images', function (Blueprint $table) {
            $table->bigInteger('file_size')->after('image_path');
            $table->string('file_type', 50)->after('file_size');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('development_task_proof_images', function (Blueprint $table) {
            $table->dropColumn('file_size');
            $table->dropColumn('file_type');
        });
    }
};
