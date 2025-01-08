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
            $table->string('place_of_birth')->nullable()->change();
            $table->string('postal_code', 6)->nullable()->change();
            $table->enum('education', ['smp', 'sma', 'smk', 'diploma', 's1', 's2', 's3'])->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->string('place_of_birth')->change();
            $table->string('postal_code', 6)->change();
            $table->enum('education', ['smp', 'sma', 'smk', 'diploma', 's1', 's2', 's3'])->change();
        });
    }
};
