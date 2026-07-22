<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Enforce at the database level that an employee can hold at most ONE in-progress signature
 * document (status 2 = awaiting sign, 3 = need to sign) per document type. This is the hard
 * backstop behind the application guard/row-lock in SignatureService::createEmployeeDocument():
 * even a concurrent double-submit can never persist two duplicate documents.
 *
 * Completed documents (status 1) are intentionally excluded, so re-issuing a document after the
 * previous one is fully signed stays allowed.
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
        if ($this->isPostgres()) {
            DB::statement(
                'CREATE UNIQUE INDEX '.self::INDEX_NAME.
                ' ON employee_documents (employee_id, document_type_id)'.
                ' WHERE status IN ('.self::IN_PROGRESS_STATUSES.')'
            );

            return;
        }

        // MySQL / MariaDB have no partial indexes, so emulate one with a generated guard column
        // that is only populated while a document is in progress. NULLs are excluded from unique
        // indexes, so completed documents never collide.
        Schema::table('employee_documents', function (Blueprint $table) {
            $table->string('active_document_guard')
                ->nullable()
                ->virtualAs(
                    'CASE WHEN status IN ('.self::IN_PROGRESS_STATUSES.')'.
                    " THEN CONCAT(employee_id, '-', document_type_id) ELSE NULL END"
                )
                ->after('document_type_id');

            $table->unique('active_document_guard', self::INDEX_NAME);
        });
    }

    public function down(): void
    {
        if ($this->isPostgres()) {
            DB::statement('DROP INDEX IF EXISTS '.self::INDEX_NAME);

            return;
        }

        Schema::table('employee_documents', function (Blueprint $table) {
            $table->dropUnique(self::INDEX_NAME);
            $table->dropColumn('active_document_guard');
        });
    }

    private function isPostgres(): bool
    {
        return Schema::getConnection()->getDriverName() === 'pgsql';
    }
};
