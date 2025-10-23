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
            // Add the published_at column if it doesn't exist
            if (!Schema::hasColumn('project_deals', 'published_at')) {
                $table->timestamp('published_at')->nullable()->after('cancel_at');
            }

            // Add the published_by column if it doesn't exist
            if (!Schema::hasColumn('project_deals', 'published_by')) {
                $table->string('published_by')->nullable()
                    ->comment('Published by')
                    ->after('published_at');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('project_deals', function (Blueprint $table) {
            // Drop the published_at column if it exists
            if (Schema::hasColumn('project_deals', 'published_at')) {
                $table->dropColumn('published_at');
            }

            // Drop the published_by column if it exists
            if (Schema::hasColumn('project_deals', 'published_by')) {
                $table->dropColumn('published_by');
            }
        });
    }
};
