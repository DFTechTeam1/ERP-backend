<?php

use App\Enums\Interactive\InteractiveRequestStatus;
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
        $status = InteractiveRequestStatus::cases();
        $statusValues = array_map(fn ($case) => $case->value, $status);
        Schema::create('interactive_requests', function (Blueprint $table) use ($statusValues) {
            $table->id();
            $table->foreignId('project_deal_id')
                ->references('id')
                ->on('project_deals')
                ->onDelete('cascade');
            $table->foreignId('requester_id')
                ->references('id')
                ->on('users')
                ->onDelete('cascade');
            $table->enum('status', $statusValues)->default(InteractiveRequestStatus::Pending->value);
            $table->json('interactive_detail')->nullable()->comment('[{"name":"interactive","led":[{"width":"1","height":"1"}],"total":"1 m<sup>2<\/sup>","totalRaw":"1","textDetail":"1 x 1 m"}]');
            $table->double('interactive_area')->default(0);
            $table->text('interactive_note')->nullable();
            $table->decimal('interactive_fee', 24, 2)->default(0.00);
            $table->decimal('fix_price', 24, 2)->default(0.00);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // drop foreign
        Schema::table('interactive_requests', function (Blueprint $table) {
            $table->dropForeign(['project_deal_id']);
            $table->dropForeign(['requester_id']);
        });

        Schema::dropIfExists('interactive_requests');
    }
};
