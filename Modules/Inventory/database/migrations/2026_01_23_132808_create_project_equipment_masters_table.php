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
        Schema::create('project_equipment_masters', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid');
            $table->foreignId('project_id')
                ->constrained('projects');
            $table->foreignId('stocker_approver')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('production_approver')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('entertainment_approver')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->integer('status')
                ->default(4)
                ->comment('1 For EventCanceled, 2 for NeedRevision, 3 for ReadyToGo, 4 for NeedApproval');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key
        Schema::table('project_equipment_masters', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['stocker_approver']);
            $table->dropForeign(['production_approver']);
            $table->dropForeign(['entertainment_approver']);
        });

        Schema::dropIfExists('project_equipment_masters');
    }
};
