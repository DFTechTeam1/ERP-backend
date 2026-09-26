<?php

use App\Enums\Finance\FiscalYear\AccountingPeriodStatus;
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
        $statuses = collect(AccountingPeriodStatus::cases())->map(fn($val) => $val->value)->toArray();
        Schema::create('accounting_periods', function (Blueprint $table) use ($statuses) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->foreignId('fiscal_year_id')
                ->constrained('fiscal_years')
                ->cascadeOnDelete();
            $table->string('name', 50)
                ->comment('e.g. January 2025');
            $table->tinyInteger('period_number')
                ->comment('1 - 12');
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', $statuses);
            $table->timestamp('closed_at');
            $table->foreignId('closed_by')
                ->constrained('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounting_periods', function (Blueprint $table) {
            $table->dropForeign(['closed_by']);
            $table->dropForeign(['fiscal_year_id']);
        });
        Schema::dropIfExists('accounting_periods');
    }
};
