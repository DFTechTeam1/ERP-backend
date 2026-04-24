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
            $table->string('greatday_resign_reason')->nullable()
                ->after('reason');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_resigns', function (Blueprint $table) {
            $table->dropColumn('greatday_resign_reason');
        });
    }
};
