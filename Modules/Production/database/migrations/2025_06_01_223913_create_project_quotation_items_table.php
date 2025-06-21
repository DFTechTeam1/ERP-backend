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
        Schema::create('project_quotation_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('quotation_id')
                ->references('id')
                ->on('project_quotations');
            $table->foreignId('item_id')
                ->references('id')
                ->on('quotation_items');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_quotation_items');
    }
};
