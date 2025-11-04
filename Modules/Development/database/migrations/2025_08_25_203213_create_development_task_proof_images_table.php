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
        Schema::create('development_task_proof_images', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('development_task_proof_id');
            $table->string('image_path');
            $table->timestamps();

            // add foreign
            $table->foreign('development_task_proof_id', 'fk_task_proof_id')->references('id')->on('development_task_proofs')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('development_task_proof_images', function (Blueprint $table) {
            $table->dropForeign('fk_task_proof_id');
        });
        Schema::dropIfExists('development_task_proof_images');
    }
};
