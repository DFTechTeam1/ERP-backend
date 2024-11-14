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
        Schema::create('request_inventories', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid');
            $table->string('name');
            $table->text('description')->nullable();
            $table->double('price')->default(0);
            $table->tinyInteger('quantity')->default(0);
            $table->string('purchase_source')->nullable();
            $table->json('purchase_link')->nullable();
            $table->tinyInteger('status')
                ->default(1)
                ->comment('1 for requested, 2 for approved, 3 for rejected');
            $table->integer('requested_by')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('request_inventories');
    }
};
