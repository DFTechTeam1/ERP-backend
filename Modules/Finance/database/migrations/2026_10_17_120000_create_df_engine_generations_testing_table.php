<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TEST-ONLY table for `df_engine_generations`.
 *
 * `df_engine_generations` is owned by the separate DFEngine service and already exists in the
 * dev/prod MySQL (it is NOT created by Laravel migrations there). The ERP relates to it via
 * Project::aiGenerations(), so the finance "project cost" feature reads it. The dedicated test DB
 * (built by `make test-migrate`, which runs with APP_ENV=testing) has no such table, so this
 * migration recreates just the columns the ERP reads — and only under the `testing` environment,
 * so it can never create or drop the engine-owned table in dev/prod.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        if (Schema::hasTable('df_engine_generations')) {
            return;
        }

        Schema::create('df_engine_generations', function (Blueprint $table) {
            $table->id();
            $table->uuid('uid')->nullable();
            $table->string('kind')->nullable();
            $table->unsignedBigInteger('model_id')->nullable();
            $table->unsignedBigInteger('menu_id')->nullable();
            $table->unsignedBigInteger('feature_id')->nullable();
            $table->unsignedBigInteger('project_id')->nullable()->index();
            $table->unsignedBigInteger('task_id')->nullable();
            $table->unsignedBigInteger('song_id')->nullable();
            $table->string('status')->nullable();
            $table->unsignedBigInteger('token_usage')->nullable();
            $table->decimal('cost', 30, 10)->nullable();
            $table->decimal('exchange_rate', 30, 10)->nullable();
            $table->unsignedBigInteger('created_by')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        if (! app()->environment('testing')) {
            return;
        }

        Schema::dropIfExists('df_engine_generations');
    }
};
