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
        Schema::table('exchange_rates', function (Blueprint $table) {
            if (! Schema::hasColumn('exchange_rates', 'created_by')) {
                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $fkKeys = Schema::getForeignKeys('exchange_rates');
        Schema::table('exchange_rates', function (Blueprint $table) use ($fkKeys) {
            $fkExists = collect($fkKeys)->contains(fn($fk) => in_array('created_by', $fk['columns']));
            if ($fkExists) {
                $table->dropForeign(['created_by']);
            }

            if (Schema::hasColumn('exchange_rates', 'created_by')) {
                $table->dropColumn('created_by');
            }
        });
    }
};
