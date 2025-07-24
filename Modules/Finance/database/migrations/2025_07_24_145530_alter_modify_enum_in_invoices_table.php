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

        Schema::table('invoices', function (Blueprint $table) use ($statuses) {
            $table->enum('status', $statuses)->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $statuses = \App\Enums\Transaction\InvoiceStatus::cases();
        $statuses = collect($statuses)->map(function ($item) {
            return $item->value;
        })->toArray();

        Schema::table('invoices', function (Blueprint $table) use ($statuses) {
            $table->enum('status', $statuses)->change();
        });
    }
};
