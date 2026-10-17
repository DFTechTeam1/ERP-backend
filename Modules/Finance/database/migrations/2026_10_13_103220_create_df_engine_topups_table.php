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
        Schema::create('df_engine_topups', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->string('created_by')->nullable();
            $table->decimal('amount_usd', 24, 2)->default(0);
            $table->decimal('exchange_rate', 12, 8)->default(0);
            $table->string('note')->nullable();
            $table->string('reference')->nullable();
            $table->date('topup_date');
            $table->string('proof_of_payment')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('df_engine_topups');
    }
};
