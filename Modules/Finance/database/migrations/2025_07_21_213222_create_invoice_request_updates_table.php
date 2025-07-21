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
        Schema::create('invoice_request_updates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('invoice_id')
                ->references('id')
                ->on('invoices')
                ->cascadeOnDelete();
            $table->decimal('amount', 24, 2)->default(0);
            $table->date('payment_date');
            $table->enum('status', [1,2]);
            $table->foreignId('request_by')
                ->nullable()
                ->constrained(table: 'users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_request_updates', function (Blueprint $table) {
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['request_by']);
        });

        Schema::dropIfExists('invoice_request_updates');
    }
};
