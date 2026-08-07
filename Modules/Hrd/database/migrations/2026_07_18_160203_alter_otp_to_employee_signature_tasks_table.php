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
            $table->string('otp', 10)->nullable();
            $table->timestamp('otp_expired_at')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_signature_tasks', function (Blueprint $table) {
            $table->dropColumn('otp', 10);
            $table->dropColumn('otp_expired_at');
        });
    }
};
