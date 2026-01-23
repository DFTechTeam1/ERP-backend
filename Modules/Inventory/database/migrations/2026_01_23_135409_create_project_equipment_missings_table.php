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
        Schema::create('project_equipment_missings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid');
            $table->foreignId('return_id')
                ->constrained('project_equipment_returns')
                ->onDelete('cascade');
            $table->date('target_return_date');
            $table->foreignId('inventory_id')
                ->constrained('inventories');
            $table->integer('quantity');
            $table->bigInteger('price');
            $table->bigInteger('total_price');
            $table->text('reason')->nullable();
            $table->foreignId('created_by')
                ->constrained('users');
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('pic_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->boolean('is_returned')->default(false)
                ->comment('True If user found the missing items, safely return and approved from stocker, otherwise False');
            $table->boolean('is_paid')->default(false)
                ->comment('True If user decide to refund the missing items, and approved from stocker, otherwise False');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key
        Schema::table('project_equipment_missings', function (Blueprint $table) {
            $table->dropForeign(['return_id']);
            $table->dropForeign(['inventory_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
            $table->dropForeign(['pic_id']);
        });

        Schema::dropIfExists('project_equipment_missings');
    }
};
