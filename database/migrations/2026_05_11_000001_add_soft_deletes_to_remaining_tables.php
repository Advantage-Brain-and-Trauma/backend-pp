<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Adds soft-delete (deleted_at) column to all application tables that were missing it.
     * System/framework tables (failed_jobs, migrations, password_reset_tokens,
     * personal_access_tokens) are intentionally excluded.
     */
    public function up(): void
    {
        $tables = [
            'form_submissions',
            'funnel_progress',
            'messages',
            'patient_funnel_assignments',
            'user_funnels',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && !Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->softDeletes();
                });
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $tables = [
            'form_submissions',
            'funnel_progress',
            'messages',
            'patient_funnel_assignments',
            'user_funnels',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'deleted_at')) {
                Schema::table($table, function (Blueprint $table) {
                    $table->dropSoftDeletes();
                });
            }
        }
    }
};
