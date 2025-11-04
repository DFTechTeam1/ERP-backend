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
        Schema::create('project_feedback', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')
                ->constrained('projects')
                ->onUpdate('cascade')
                ->onDelete('cascade');
            $table->foreignId('pic_id')
                ->constrained('employees')
                ->onDelete('cascade');
            $table->text('feedback');
            $table->json('points');
            $table->timestamp('submitted_at')->nullable();
            $table->unsignedBigInteger('submitted_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('project_feedback', function (Blueprint $table) {
            $table->dropForeign(['project_id']);
            $table->dropForeign(['pic_id']);
        });
        Schema::dropIfExists('project_feedback');
    }
};
