<?php

use App\Enums\Production\ProjectDealChangeStatus;
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
        $status = ProjectDealChangeStatus::cases();
        $status = collect($status)->map(fn ($value) => $value->value)->toArray();

        Schema::create('project_deal_changes', function (Blueprint $table) use ($status) {
            $table->id();
            $table->foreignId('project_deal_id')
                ->references('id')
                ->on('project_deals')
                ->cascadeOnDelete();
            $table->json('detail_changes')
                ->comment('Will be JSON format. Json will be like this [{"field": "project_date", "old_value": "", "new_value": "", "label": ""}]');
            $table->foreignId('requested_by')
                ->nullable()
                ->constrained(table: 'users', column: 'id');
            $table->timestamp('requested_at')->nullable();
            $table->foreignId('approval_by')
                ->nullable()
                ->constrained(table: 'users', column: 'id');
            $table->timestamp('approval_at')->nullable();
            $table->enum('status', $status);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_deal_changes', function (Blueprint $table) {
            $table->dropForeign(['project_deal_id']);
            $table->dropForeign(['requested_by']);
            $table->dropForeign(['approval_by']);
        });

        Schema::dropIfExists('project_deal_changes');
    }
};
