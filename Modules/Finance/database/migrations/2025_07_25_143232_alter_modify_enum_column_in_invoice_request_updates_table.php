<?php

use App\Enums\Finance\InvoiceRequestUpdateStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Finance\Models\InvoiceRequestUpdate;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $status = InvoiceRequestUpdateStatus::cases();

        Schema::table('invoice_request_updates', function (Blueprint $table) use ($status) {
            $table->enum('status', array_map(fn($s) => $s->value, $status))
                ->default(InvoiceRequestUpdateStatus::Pending->value)
                ->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_request_updates', function (Blueprint $table) {
            $table->enum('status', [
                InvoiceRequestUpdateStatus::Pending->value,
                InvoiceRequestUpdateStatus::Approved->value,
                InvoiceRequestUpdateStatus::Rejected->value
            ])->default(InvoiceRequestUpdateStatus::Pending->value)->change();
        });
    }
};
