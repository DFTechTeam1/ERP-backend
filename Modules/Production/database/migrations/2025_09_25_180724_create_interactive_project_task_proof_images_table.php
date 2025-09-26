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
        Schema::create('intr_task_proof_images', function (Blueprint $table) {
            $table->id();
            $table->foreignId('intr_task_proof_id')->constrained('intr_task_proofs')->onDelete('cascade');
            $table->string('image_path');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('intr_task_proof_images', function (Blueprint $table) {
            $table->dropForeign(['intr_task_proof_id']);
        });
        Schema::dropIfExists('intr_task_proof_images');
    }
};
