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
        Schema::table('development_subtask_upload_files', function (Blueprint $table) {
            $table->string('file_size')->nullable();
            $table->string('file_type')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('development_subtask_upload_files', function (Blueprint $table) {
            $table->dropColumn('file_size');
            $table->dropColumn('file_type');
        });
    }
};
