<?php

use App\Enums\Finance\FiscalYear\FiscalStatus;
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
        $types = collect(FiscalStatus::cases())->map(fn($val) => $val->value)->toArray();
        Schema::create('fiscal_years', function (Blueprint $table) use ($types) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->string('name', 50)->unique();
            $table->date('start_date');
            $table->date('end_date');
            $table->enum('status', $types);
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
        Schema::table('fiscal_years', function (Blueprint $table) {
            $table->dropForeign(['closed_by']);
        });
        Schema::dropIfExists('fiscal_years');
    }
};
