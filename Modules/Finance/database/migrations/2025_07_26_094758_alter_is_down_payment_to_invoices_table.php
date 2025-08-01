<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Modules\Finance\Models\Invoice;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->boolean('is_down_payment')->default(false)->after('status')
                ->comment('Define this invoice is down payment or not');
        });

        $data = Invoice::selectRaw('id,number,parent_number,is_main')
            ->where('is_main', 0)
            ->where('parent_number', '!=', null)
            ->orderBy('created_at', 'asc')
            ->get();

        $data = $data->groupBy('parent_number');

        foreach ($data as $invoice) {
            $first = $invoice->first();
            if ($first) {
                $first->is_down_payment = true;
                $first->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {
            $table->dropColumn('is_down_payment');
        });
    }
};
