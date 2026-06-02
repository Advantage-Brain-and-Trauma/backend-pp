<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Solves the soft-delete + unique-constraint collision.
 *
 * Problem
 * -------
 * The unique key on (user_id, funnel_id, patient_case_id) prevents re-assigning
 * a funnel to the same patient/case after the previous assignment has been
 * soft-deleted, because the deleted row still occupies the unique key slot.
 *
 * Solution — "deleted_id" sentinel pattern
 * -----------------------------------------
 * • Add a `deleted_id` column (BIGINT, default 0, NOT NULL).
 * • Change the unique key to (user_id, funnel_id, patient_case_id, deleted_id).
 * • Active rows always have deleted_id = 0  → uniqueness enforced for live rows.
 * • On soft-delete the model sets deleted_id = id  → always unique, never 0,
 *   so it never collides with an active row or another deleted row.
 *
 * Historical soft-deleted rows are preserved in full — nothing is removed.
 */
return new class extends Migration
{
    public function up(): void
    {
        // Add sentinel column if it doesn't exist yet (idempotent).
        if (! Schema::hasColumn('user_funnels', 'deleted_id')) {
            Schema::table('user_funnels', function (Blueprint $table) {
                $table->unsignedBigInteger('deleted_id')->default(0)->after('deleted_at');
            });
        }

        // Back-fill existing soft-deleted rows so they already satisfy the new constraint.
        DB::statement('UPDATE user_funnels SET deleted_id = id WHERE deleted_at IS NOT NULL AND deleted_id = 0');

        $indexes   = collect(DB::select('SHOW INDEX FROM user_funnels'))->pluck('Key_name')->unique()->toArray();
        $fkNames   = collect(DB::select(
            "SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'user_funnels'
               AND CONSTRAINT_TYPE = 'FOREIGN KEY'"
        ))->pluck('CONSTRAINT_NAME')->toArray();

        Schema::table('user_funnels', function (Blueprint $table) use ($indexes, $fkNames) {
            // MySQL uses the composite unique index to back the user_id FK.
            // We must drop the FK first, then the index, then re-add both.
            if (in_array('user_funnels_user_id_foreign', $fkNames)) {
                $table->dropForeign('user_funnels_user_id_foreign');
            }

            // Ensure a standalone index on user_id exists to back the FK after the swap.
            if (! in_array('user_funnels_user_id_index', $indexes)) {
                $table->index('user_id', 'user_funnels_user_id_index');
            }

            // Drop the old unique constraint (name varies by environment).
            if (in_array('user_funnels_user_id_funnel_id_patient_case_id_unique', $indexes)) {
                $table->dropUnique('user_funnels_user_id_funnel_id_patient_case_id_unique');
            } elseif (in_array('user_funnels_user_id_funnel_id_unique', $indexes)) {
                $table->dropUnique('user_funnels_user_id_funnel_id_unique');
            }

            // New unique key — includes deleted_id so soft-deleted rows never collide.
            $table->unique(
                ['user_id', 'funnel_id', 'patient_case_id', 'deleted_id'],
                'user_funnels_user_id_funnel_id_case_deleted_unique'
            );

            // Re-add the foreign key backed by the standalone index created above.
            if (in_array('user_funnels_user_id_foreign', $fkNames)) {
                $table->foreign('user_id', 'user_funnels_user_id_foreign')
                      ->references('id')->on('users')
                      ->onDelete('set null');
            }
        });
    }

    public function down(): void
    {
        Schema::table('user_funnels', function (Blueprint $table) {
            $table->dropUnique('user_funnels_user_id_funnel_id_case_deleted_unique');
            $table->unique(
                ['user_id', 'funnel_id', 'patient_case_id'],
                'user_funnels_user_id_funnel_id_patient_case_id_unique'
            );
            $table->dropColumn('deleted_id');
        });
    }
};
