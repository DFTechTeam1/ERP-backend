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
        Schema::create('document_type_signers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_id')
                ->constrained('division_backups');
            $table->tinyInteger('order');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('document_type_signers', function (Blueprint $table) {
            $table->dropForeign(['division_id']);
        });
        
        Schema::dropIfExists('document_type_signers');
    }
};
