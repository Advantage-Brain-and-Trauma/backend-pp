<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // Check if the FK currently points to sync_forms and fix it
        $fkInfo = DB::select("
            SELECT CONSTRAINT_NAME, REFERENCED_TABLE_NAME
            FROM information_schema.KEY_COLUMN_USAGE
            WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = 'form_submissions'
            AND COLUMN_NAME = 'form_id'
            AND REFERENCED_TABLE_NAME IS NOT NULL
            LIMIT 1
        ");

        if (!empty($fkInfo)) {
            $constraintName = $fkInfo[0]->CONSTRAINT_NAME;
            $referencedTable = $fkInfo[0]->REFERENCED_TABLE_NAME;

            // Only fix if it's pointing to the wrong table
            if ($referencedTable !== 'forms') {
                Schema::table('form_submissions', function (Blueprint $table) use ($constraintName) {
                    $table->dropForeign($constraintName);
                });
                Schema::table('form_submissions', function (Blueprint $table) {
                    $table->foreign('form_id')
                          ->references('id')
                          ->on('forms')
                          ->onDelete('cascade');
                });
            }
        }
    }

    public function down(): void
    {
        // No-op: we don't want to revert to a broken FK
    }
};
