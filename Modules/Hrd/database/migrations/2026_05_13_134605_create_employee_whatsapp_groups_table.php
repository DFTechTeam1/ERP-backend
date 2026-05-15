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
        Schema::create('employee_whatsapp_groups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees')
                ->cascadeOnDelete();
            $table->string('group_id');
            $table->timestamps();
        });

        Schema::table('employee_whatsapp_groups', function (Blueprint $table) {
            $table->foreign('group_id')
                ->references('group_id')
                ->on('whatsapp_groups');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_whatsapp_groups', function (Blueprint $table) {
            $table->dropForeign(['group_id']);
            $table->dropForeign(['employee_id']);
        });
        Schema::dropIfExists('employee_whatsapp_groups');
    }
};
