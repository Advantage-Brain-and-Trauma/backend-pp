<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Expand the status enum to include 'draft' and 'completed'.
     *
     * Full set:
     *   draft      – user started but did not finish / auto-saved partial data
     *   completed  – user submitted all fields successfully
     *   pending    – legacy default / awaiting admin review
     *   reviewed   – admin has reviewed the submission
     *   archived   – submission archived by admin
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE form_submissions MODIFY COLUMN status ENUM('draft','completed','pending','reviewed','archived') NOT NULL DEFAULT 'pending'");
    }

    public function down(): void
    {
        // Revert unknown values to 'pending' before shrinking the enum
        DB::statement("UPDATE form_submissions SET status = 'pending' WHERE status IN ('draft','completed')");
        DB::statement("ALTER TABLE form_submissions MODIFY COLUMN status ENUM('pending','reviewed','archived') NOT NULL DEFAULT 'pending'");
    }
};
