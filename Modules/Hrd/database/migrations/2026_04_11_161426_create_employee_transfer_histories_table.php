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
        Schema::create('employee_transfer_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')
                ->references('id')
                ->on('employees');
            $table->foreignId('from_position_id')
                ->references('id')
                ->on('position_backups');
            $table->foreignId('to_position_id')
                ->references('id')
                ->on('position_backups');

            $table->foreignId('from_work_location_id')
                ->references('id')
                ->on('greatday_work_locations');
            $table->foreignId('to_work_location_id')
                ->references('id')
                ->on('greatday_work_locations');

            $table->string('from_work_location_name');
            $table->string('to_work_location_name');

            $table->foreignId('from_cost_center_id')
                ->references('id')
                ->on('greatday_cost_centers');
            $table->foreignId('to_cost_center_id')
                ->references('id')
                ->on('greatday_cost_centers');

            $table->string('from_cost_center_name');
            $table->string('to_cost_center_name');

            $table->enum('transfer_type', [
                'promotion',
                'demotion',
                'mutation',
                'employment_status_change'
            ]);
            
            $table->date('effective_date');
            $table->foreignId('transferred_by')
                ->references('id')
                ->on('employees');

            $table->string('note')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign
        Schema::table('employee_transfer_histories', function (Blueprint $table) {
            $table->dropForeign(['from_position_id']);
            $table->dropForeign(['to_position_id']);
            $table->dropForeign(['from_cost_center_id']);
            $table->dropForeign(['to_cost_center_id']);
            $table->dropForeign(['from_work_location_id']);
            $table->dropForeign(['to_work_location_id']);
            $table->dropForeign(['employee_id']);
            $table->dropForeign(['transferred_by']);
        });
        
        Schema::dropIfExists('employee_transfer_histories');
    }
};
