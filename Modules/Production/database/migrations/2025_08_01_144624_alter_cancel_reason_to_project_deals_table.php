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
        Schema::table('project_deals', function (Blueprint $table) {
            $table->string('cancel_reason')->nullable()->after('is_fully_paid');
            $table->timestamp('cancel_at')->nullable();
            $table->foreignId('cancel_by')
                ->nullable()
                ->constrained(table: 'users', column: 'id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_deals', function (Blueprint $table) {
            $table->dropForeign(['cancel_by']);

            $table->dropColumn('cancel_reason');
            $table->dropColumn('cancel_at');
            $table->dropColumn('cancel_by');
        });
    }
};
