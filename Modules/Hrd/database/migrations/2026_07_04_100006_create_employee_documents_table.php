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
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->nullable()
                ->constrained('employees')
                ->cascadeOnDelete();
            $table->tinyInteger('status')->comment('1 completed, 2 awaiting sign, 3 need to sign');
            $table->json('signers_detail')->nullable();
            $table->tinyInteger('total_signer')->nullable()->comment('Expected total signers in this document');
            $table->json('document_snapshot')->nullable()->comment('to catch all document configuration');
            $table->string('document_path')->comment('Document that already attach to employee');
            $table->foreignId('document_type_id')
                ->nullable()
                ->constrained('document_types')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('employee_documents');
    }
};
