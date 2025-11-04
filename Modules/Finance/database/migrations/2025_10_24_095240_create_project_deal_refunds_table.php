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
        Schema::create('project_deal_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_deal_id')->constrained('project_deals')->onDelete('cascade');
            $table->decimal('refund_amount', 24, 2);
            $table->integer('refund_percentage')->default(0);
            $table->enum('refund_type', ['fixed', 'percentage']);
            $table->string('refund_reason')->nullable();
            $table->enum('status', ['1', '2'])->comment('1: pending, 2: paid');
            $table->foreignId('created_by')->constrained('users')->onDelete('restrict');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('project_deal_refunds', function (Blueprint $table) {
            $table->dropForeign(['project_deal_id']);
            $table->dropForeign(['created_by']);
        });

        Schema::dropIfExists('project_deal_refunds');
    }
};
