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
        Schema::create('request_equipment_histories', function (Blueprint $table) {
            $table->id();
            $table->char('uid', 32)->unique();
            $table->foreignId('request_id')->constrained('request_equipments')->onDelete('cascade');
            $table->json('from');
            $table->json('to');
            $table->bigInteger('approved_by')->nullable();
            $table->bigInteger('rejected_by')->nullable();
            $table->enum('status', [
                'need_approval',
                'approved',
                'rejected'
            ]);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign key
        Schema::table('request_equipment_histories', function (Blueprint $table) {
            $table->dropForeign(['request_id']);
        });

        Schema::dropIfExists('request_equipment_histories');
    }
};
