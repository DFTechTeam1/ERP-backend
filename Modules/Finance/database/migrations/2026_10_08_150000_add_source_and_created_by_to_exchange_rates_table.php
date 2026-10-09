<?php

use App\Enums\Finance\ExchangeRate\SourceRate;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Record how each rate was set (system fetch vs manual entry) and, for manual entries, by whom.
     *
     * Each column is guarded so the migration is a no-op for a column that already exists (e.g. when
     * another branch introduced `source` on the same shared database).
     */
    public function up(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            if (! Schema::hasColumn('exchange_rates', 'source')) {
                $table->string('source', 20)->default(SourceRate::System->value)->after('rate');
            }

            if (! Schema::hasColumn('exchange_rates', 'created_by')) {
                $table->foreignId('created_by')->nullable()->after('source')->constrained('users')->nullOnDelete();
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('exchange_rates', function (Blueprint $table) {
            if (Schema::hasColumn('exchange_rates', 'created_by')) {
                $table->dropForeign(['created_by']);
                $table->dropColumn('created_by');
            }

            if (Schema::hasColumn('exchange_rates', 'source')) {
                $table->dropColumn('source');
            }
        });
    }
};
