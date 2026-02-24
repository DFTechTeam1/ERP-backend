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
        Schema::create('development_subtask_upload_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('subtask_upload_id')
                ->constrained(table: 'development_subtask_uploads', indexName: 'dev_subtask_upload_files_subtask_upload_id_foreign')
                ->onDelete('cascade');
            $table->string('file_path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign
        Schema::table('development_subtask_upload_files', function (Blueprint $table) {
            $table->dropForeign('dev_subtask_upload_files_subtask_upload_id_foreign');
        });

        Schema::dropIfExists('development_subtask_upload_files');
    }
};
