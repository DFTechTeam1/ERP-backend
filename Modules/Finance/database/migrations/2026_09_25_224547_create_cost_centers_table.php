<?php

use App\Enums\Finance\CostCenter\CostCenterType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Finance\Models\CostCenter;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $types = collect(CostCenterType::cases())->map(fn($val) => $val->value)->toArray();
        Schema::create('cost_centers', function (Blueprint $table) use ($types) {
            $table->id();
            $table->uuid('uid')->unique();
            $table->string('code', 20)->unique();
            $table->string('name', 100);
            $table->bigInteger('parent_id')->nullable();
            $table->boolean('is_active')->default(true);
            $table->enum('type', $types);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cost_centers');
    }
};
