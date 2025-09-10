<?php

use App\Enums\Finance\InvoiceRequestUpdateStatus;
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
        $statuses = InvoiceRequestUpdateStatus::cases();
        $statuses = array_map(fn ($status) => $status->value, $statuses);

        Schema::create('invoice_request_updates', function (Blueprint $table) use ($statuses) {
            $table->id();
            $table->foreignId('invoice_id')
                ->references('id')
                ->on('invoices')
                ->cascadeOnDelete();
            $table->decimal('amount', 24, 2)->nullable();
            $table->date('payment_date')->nullable();
            $table->enum('status', $statuses);
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
