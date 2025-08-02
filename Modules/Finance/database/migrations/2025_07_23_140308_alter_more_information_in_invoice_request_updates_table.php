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
        Schema::table('invoice_request_updates', function (Blueprint $table) {
            $table->bigInteger('approved_by')->nullable()->after('request_by');
            $table->bigInteger('rejected_by')->nullable()->after('approved_by');
            $table->text('reason')->nullable()->after('rejected_by');
            $table->timestamp('approved_at')->nullable()->after('reason');
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_request_updates', function (Blueprint $table) {
            $table->dropColumn(['approved_by', 'rejected_by', 'reason', 'approved_at', 'rejected_at']);
        });
    }
};
