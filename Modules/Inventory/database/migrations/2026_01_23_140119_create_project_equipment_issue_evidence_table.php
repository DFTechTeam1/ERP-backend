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
        Schema::create('project_equipment_issue_evidence', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid');
            $table->foreignId('missing_id')
                ->nullable()
                ->constrained('project_equipment_missings')
                ->nullOnDelete();
            $table->foreignId('broken_id')
                ->nullable()
                ->constrained('project_equipment_brokens')
                ->nullOnDelete();
            $table->integer('action')->comment('1 for return, 2 for payment');
            $table->string('image');
            $table->foreignId('created_by')
                ->constrained('users');
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Drop foreign key
        Schema::table('project_equipment_issue_evidence', function (Blueprint $table) {
            $table->dropForeign(['missing_id']);
            $table->dropForeign(['broken_id']);
            $table->dropForeign(['created_by']);
            $table->dropForeign(['updated_by']);
        });

        Schema::dropIfExists('project_equipment_issue_evidence');
    }
};
