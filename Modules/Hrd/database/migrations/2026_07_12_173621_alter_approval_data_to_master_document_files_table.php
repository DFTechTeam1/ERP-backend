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
        Schema::table('master_document_files', function (Blueprint $table) {
            $table->foreignId('approved_by')
                ->nullable()
                ->constrained('users', 'id');
            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained('users', 'id');
            $table->string('approval_note')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('master_document_files', function (Blueprint $table) {
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropColumn('approved_by');
            $table->dropColumn('rejected_by');
            $table->dropColumn('approval_note');
        });
    }
};
