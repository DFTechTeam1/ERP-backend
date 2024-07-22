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
        Schema::create('transfer_team_members', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid');
            $table->foreignId('project_id')
                ->references('id')
                ->on('projects')
                ->cascadeOnDelete();
            $table->integer('employee_id');
            $table->integer('request_to');
            $table->string('reason');
            $table->date('project_date');
            $table->timestamp('request_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('canceled_at')->nullable();
            $table->string('reject_reason')->nullable();
            $table->string('device_action', 10)->nullable()
                ->comment('device resource for any action take. It should line or web');
            $table->tinyInteger('status')
                ->comment('1 for requested, 2 for approved, 3 for reject, 4 completed');
            $table->integer('requested_by');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('transfer_team_members');
    }
};
