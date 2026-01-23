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
        Schema::create('project_equipment_summary', function (Blueprint $table) {
            $table->id();
            $table->foreignId('master_id')
                ->constrained('project_equipment_masters')
                ->onDelete('cascade');
            $table->uuid('uid');
            $table->integer('issued_total');
            $table->integer('returned_total')->nullable();
            $table->integer('missing_total')->nullable();
            $table->integer('broken_total')->nullable();
            $table->foreignId('created_by')
                ->constrained('users');
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->integer('status')
                ->default(3)
                ->comment('1 for Completed, 2 for OnGoing, 3 for WaitingToReturn');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key
        Schema::table('project_equipment_summary', function (Blueprint $table) {
            $table->dropForeign(['master_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
        });

        Schema::dropIfExists('project_equipment_summary');
    }
};
