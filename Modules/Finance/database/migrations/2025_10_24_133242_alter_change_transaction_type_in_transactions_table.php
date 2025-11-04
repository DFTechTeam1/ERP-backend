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
        $types = \App\Enums\Transaction\TransactionType::cases();
        $types = collect($types)->map(function ($type) {
            return $type->value;
        })->toArray();

        Schema::table('transactions', function (Blueprint $table) use ($types) {
            $table->enum('transaction_type', $types)
                ->nullable()
                ->change();
            $table->morphs('sourceable');
            $table->enum('debit_credit', ['debit', 'credit'])
                ->default('debit')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $types = \App\Enums\Transaction\TransactionType::cases();
        $types = collect($types)->map(function ($type) {
            return $type->value;
        })->toArray();

        Schema::table('transactions', function (Blueprint $table) use ($types) {
            $table->enum('transaction_type', $types)
                ->nullable()
                ->change();
            $table->dropMorphs('sourceable');
            $table->dropColumn('debit_credit');
        });
    }
};
