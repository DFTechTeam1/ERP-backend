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
        Schema::table('project_deal_changes', function (Blueprint $table) {
            $table->foreignId('rejected_by')
                ->nullable()
                ->constrained(table: 'users', column: 'id');
            $table->timestamp('rejected_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_deal_changes', function (Blueprint $table) {
            if (checkForeignKey(tableName: 'project_deal_changes', columnName: 'rejected_by')) {
                $table->dropForeign(['rejected_by']);
            }
            $table->dropColumn('rejected_by');
            $table->dropColumn('rejected_at');
        });
    }
};
