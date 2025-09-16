<?php

use App\Enums\Production\ProjectDealChangePriceStatus;
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
        $status = ProjectDealChangePriceStatus::cases();
        $status = array_map(fn ($case) => $case->value, $status);

        Schema::create('project_deal_price_changes', function (Blueprint $table) use ($status) {
            $table->id();
            $table->foreignId('project_deal_id')->constrained()->onDelete('cascade');
            $table->decimal('old_price', 24, 2);
            $table->decimal('new_price', 24, 2);
            $table->string('reason');
            $table->foreignId('requested_by')->constrained('users')->onDelete('cascade');
            $table->timestamp('requested_at')->useCurrent();
            $table->foreignId('approved_by')->nullable()->constrained('users')->onDelete('set null');
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->onDelete('set null');
            $table->string('rejected_reason')->nullable();
            $table->enum('status', $status);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_deal_price_changes', function (Blueprint $table) {
            $table->dropForeign(['project_deal_id']);
            $table->dropForeign(['requested_by']);
            $table->dropForeign(['approved_by']);
            $table->dropForeign(['rejected_by']);
        });

        Schema::dropIfExists('project_deal_price_changes');
    }
};
