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
        Schema::create('employee_families', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid');
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();
            $table->string('name');
            $table->string('relation')->nullable();
            $table->date('date_of_birth')->nullable();
            $table->string('id_number', 16);
            $table->string('gender', 10)->nullable();
            $table->string('job')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_families', function (Blueprint $table) {
            $table->dropForeign(['employee_id']);
        });

        Schema::dropIfExists('employee_families');
    }
};
