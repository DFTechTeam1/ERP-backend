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
            $table->enum('status', ['signed', 'waiting', 'locked'])
                ->default('waiting');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_signature_tasks', function (Blueprint $table) {
            $table->dropColumn('status');
        });
    }
};
