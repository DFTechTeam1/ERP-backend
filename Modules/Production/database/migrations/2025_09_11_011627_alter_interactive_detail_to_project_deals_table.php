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
            $table->json('interactive_detail')->nullable()->after('led_detail')->comment('[{"name":"interactive","led":[{"width":"1","height":"1"}],"total":"1 m<sup>2<\/sup>","totalRaw":"1","textDetail":"1 x 1 m"}]');
            $table->text('interactive_note')->nullable()->after('note');
            $table->string('interactive_area', 10)->nullable()->after('interactive_detail')->default(0);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_deals', function (Blueprint $table) {
            $table->dropColumn('interactive_detail');
            $table->dropColumn('interactive_note');
            $table->dropColumn('interactive_area');
        });
    }
};
