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
        Schema::create('master_document_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_document_id')
                ->nullable()
                ->constrained('master_documents')
                ->cascadeOnDelete();
            $table->string('path');
            $table->string('file_type', 30);
            $table->longText('placeholder_mapping')->nullable();
            $table->string('version', 10)->comment('always increment');
            $table->tinyInteger('status')->default(1)->comment('1 active, 2 pending review, 3 rejected, 4 archived');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_document_files');
    }
};
