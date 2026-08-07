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
        Schema::create('employee_signature_tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->constrained('employees');
            $table->foreignId('employee_document_id')
                ->constrained('employee_documents');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_signature_tasks', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['employee_document_id']);
        });
        Schema::dropIfExists('employee_signature_tasks');
    }
};
