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
        Schema::table('employee_resigns', function (Blueprint $table) {
            // Drop severance column
            $table->dropColumn('severance');

            // Add created_by column, nullable and constrained to users table
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->after('id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_resigns', function (Blueprint $table) {
            // Drop created_by column
            $table->dropForeign(['created_by']);
            $table->dropColumn('created_by');

            // Add severance column back, nullable and default to 0
            $table->integer('severance')->nullable()->default(0)->after('reason');
        });
    }
};
