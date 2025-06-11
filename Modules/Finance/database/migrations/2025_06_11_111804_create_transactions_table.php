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
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid');
            $table->foreignId('project_deal_id')
                ->references('id')
                ->on('project_deals');
            $table->foreignId('customer_id')
                ->references('id')
                ->on('customers');
            $table->decimal('payment_amount', 24, 2);
            $table->string('reference')->nullable();
            $table->string('note')->nullable();
            $table->string('trx_id');
            $table->timestamp('transaction_date');
            $table->foreignId('created_by')
                ->nullable()
                ->references('id')
                ->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropForeign(['project_deal_id']);
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['created_by']);
        });

        Schema::dropIfExists('transactions');
    }
};
