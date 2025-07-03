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
        $statuses = \App\Enums\Transaction\InvoiceStatus::cases();
        $statuses = collect($statuses)->map(function ($item) {
            return $item->value;
        })->toArray();

        Schema::create('invoices', function (Blueprint $table) use ($statuses) {
            $table->id();
            $table->decimal('amount', 24, 2)->default(0);
            $table->decimal('paid_amount', 24, 2)->default(0);
            $table->timestamp('payment_date');
            $table->timestamp('payment_due');
            $table->foreignId('project_deal_id')
                ->references('id')
                ->on('project_deals');
            $table->foreignId('customer_id')
                ->references('id')
                ->on('customers');
            $table->enum('status', $statuses);
            $table->json('raw_data')->nullable();

            // numbering
            $table->string('parent_number', 100)->nullable();
            $table->string('number', 100);
            $table->boolean('is_main')->default(false);
            $table->tinyInteger('sequence')->comment('Sequence identifier for child invoice');

            $table->foreignId('created_by')
                ->nullable()
                ->constrained(table: 'users', column: 'id')
                ->onDelete('set null');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropForeign(['project_deal_id']);
            $table->dropForeign(['customer_id']);
            $table->dropForeign(['created_by']);
        });

        Schema::dropIfExists('invoices');
    }
};
