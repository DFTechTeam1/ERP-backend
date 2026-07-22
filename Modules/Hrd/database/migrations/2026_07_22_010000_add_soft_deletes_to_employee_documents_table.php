<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Add soft deletes to employee_documents and refresh the "one in-progress document per employee per
 * type" unique guard so a soft-deleted document no longer occupies the slot. Deleting an in-progress
 * document therefore frees it up for a fresh one to be generated, exactly as if it never existed.
 *
 * @see 2026_07_22_000000_add_active_document_unique_index_to_employee_documents_table.php
 */
return new class extends Migration
{
    private const INDEX_NAME = 'employee_documents_active_unique';

    /**
     * The in-progress statuses guarded against duplication (awaiting sign, need to sign).
     */
    private const IN_PROGRESS_STATUSES = '2, 3';

    public function up(): void
    {
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->softDeletes();
        });

        $this->rebuildGuard(guardOnlyLiveRows: true);
    }

    public function down(): void
    {
        $this->rebuildGuard(guardOnlyLiveRows: false);

        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }

    /**
     * Recreate the partial unique guard, optionally scoping it to non-deleted rows.
     */
    private function rebuildGuard(bool $guardOnlyLiveRows): void
    {
        $condition = $guardOnlyLiveRows
            ? 'deleted_at IS NULL AND status IN ('.self::IN_PROGRESS_STATUSES.')'
            : 'status IN ('.self::IN_PROGRESS_STATUSES.')';

        if ($this->isPostgres()) {
            DB::statement('DROP INDEX IF EXISTS '.self::INDEX_NAME);
            DB::statement(
                'CREATE UNIQUE INDEX '.self::INDEX_NAME.
                ' ON employee_documents (employee_id, document_type_id)'.
                ' WHERE '.$condition
            );

            return;
        }

        // MySQL / MariaDB: rebuild the generated guard column the partial index is emulated with.
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropUnique(self::INDEX_NAME);
            $table->dropColumn('active_document_guard');
        });

        Schema::table('employee_documents', function (Blueprint $table) use ($condition) {
            $table->string('active_document_guard')
                ->nullable()
                ->virtualAs(
                    'CASE WHEN '.$condition.
                    " THEN CONCAT(employee_id, '-', document_type_id) ELSE NULL END"
                )
                ->after('document_type_id');

            $table->unique('active_document_guard', self::INDEX_NAME);
        });
    }

    private function isPostgres(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql';
    }
};
