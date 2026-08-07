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
        Schema::create('master_document_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_document_id')
                ->nullable()
                ->constrained('master_documents')
                ->cascadeOnDelete();
            $table->foreignId('position_id')
                ->nullable()
                ->constrained('position_backups')
                ->nullOnDelete();
            $table->tinyInteger('order')->comment('To define the orders');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('master_document_signers');
    }
};
