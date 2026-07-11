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
        $tableName = "master_document_signers";
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            $table->foreignId('division_id')
                ->nullable()
                ->constrained('division_backups')
                ->nullOnDelete();
            
            if (Schema::hasColumn($tableName, 'position_id')) {
                $table->dropForeign(['position_id']);
                $table->dropColumn('position_id');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tableName = "master_document_signers";
        Schema::table($tableName, function (Blueprint $table) use ($tableName) {
            if (Schema::hasColumn($tableName, 'division_id')) {
                $table->dropForeign(['division_id']);
                $table->dropColumn('division_id');
            }

            if (! Schema::hasColumn($tableName, 'position_id')) {
                $table->foreignId('position_id')
                    ->nullable()
                    ->constrained('position_backups')
                    ->nullOnDelete();
            }
        });
    }
};
