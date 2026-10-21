<?php

use App\Enums\Accounting\AccountSubtype;
use App\Enums\Accounting\AccountType;
use App\Enums\Accounting\CashFlowCategory;
use App\Enums\Accounting\NormalBalance;
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
        $accountTypes = collect(AccountType::cases())->map(fn ($val) => $val->value)->toArray();
        $accountSubtypes = collect(AccountSubtype::cases())->map(fn ($val) => $val->value)->toArray();
        $normalBalances = collect(NormalBalance::cases())->map(fn ($val) => $val->value)->toArray();
        $cashFlowCategories = collect(CashFlowCategory::cases())->map(fn ($val) => $val->value)->toArray();

        Schema::create('accounts', function (Blueprint $table) use ($accountTypes, $accountSubtypes, $normalBalances, $cashFlowCategories) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->string('code', 20)->unique();
            $table->string('name', 150);
            $table->enum('account_type', $accountTypes);
            $table->enum('account_subtype', $accountSubtypes)->nullable();
            $table->enum('normal_balance', $normalBalances)->nullable();
            $table->foreignId('parent_id')->nullable()->constrained('accounts');
            $table->unsignedTinyInteger('level')->default(0);
            $table->boolean('is_postable')->default(true);
            $table->boolean('is_cash_equivalent')->default(false);
            $table->enum('cash_flow_category', $cashFlowCategories)->nullable();
            $table->char('currency_code', 3)->default('IDR');
            $table->boolean('is_locked')->default(false);
            $table->boolean('is_active')->default(true);
            $table->string('description')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users');
            $table->timestamps();
            $table->softDeletes();

            $table->index(['account_type', 'account_subtype']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropForeign(['parent_id']);
            $table->dropForeign(['created_by']);
        });
        Schema::dropIfExists('accounts');
    }
};
