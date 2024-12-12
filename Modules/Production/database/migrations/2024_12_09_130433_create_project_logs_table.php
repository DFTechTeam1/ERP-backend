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
        Schema::create('project_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->references('id')->on('projects')
                ->cascadeOnDelete();
            $table->string('message');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (checkForeignKey(tableName: 'project_logs', columnName: 'project_id')) {
            Schema::table('project_logs', function (Blueprint $table) {
                $table->dropForeign(['project_id']);
            });
        }
        Schema::dropIfExists('project_logs');
    }
};
