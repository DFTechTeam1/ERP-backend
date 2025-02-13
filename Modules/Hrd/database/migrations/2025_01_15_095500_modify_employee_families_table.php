<?php

use App\Enums\Employee\RelationFamily;
use App\Enums\Employee\Religion;
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
        $relations = RelationFamily::cases();
        $relations = collect($relations)->map(function($item) {
            return $item->value;
        })->toArray();

        $religions = Religion::cases();
        $religions = collect($religions)->map(function($item) {
            return $item->value;
        })->toArray();

        Schema::table('employee_families', function (Blueprint $table) use ($relations, $religions) {
            $table->dropColumn('relation');

            $table->enum('relationship', $relations);
            $table->string('address')->nullable();
            $table->string('religion')->nullable();
            $table->string('martial_status')->nullable();
            $table->string('id_number', 16)->nullable()->change();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('employee_families', function (Blueprint $table) {
            $table->string('relation')->nullable();

            $table->dropColumn('relationship');
            $table->dropColumn('address');
            $table->dropColumn('religion');
            $table->dropColumn('martial_status');

            $table->string('id_number', 16)->change();
        });
    }
};
