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
        Schema::table('employees', function (Blueprint $table) {
            if (checkForeignKey(tableName: 'employees', columnName: 'position_id')) {
                $table->dropForeign(['position_id']);
            }

            $table->foreign('position_id')
                ->references('id')
                ->on('position_backups');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            if (checkForeignKey(tableName: 'employees', columnName: 'position_id')) {
                $table->dropForeign(['position_id']);
            }

            $table->foreign('position_id')
                ->references('id')
                ->on('positions');
        });
    }
};
