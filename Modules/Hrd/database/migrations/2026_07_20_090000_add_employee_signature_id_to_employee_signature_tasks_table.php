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
        Schema::table('employee_signature_tasks', function (Blueprint $table) {
            $table->foreignId('employee_signature_id')
                ->nullable()
                ->after('employee_document_id')
                ->comment('The signature applied by the signer; null until signed. Overlaid at render time.')
                ->constrained('employee_signatures')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_signature_tasks', function (Blueprint $table) {
            $table->dropForeign(['employee_signature_id']);
            $table->dropColumn('employee_signature_id');
        });
    }
};
