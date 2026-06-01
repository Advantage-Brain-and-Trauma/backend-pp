<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Keep standalone indexes so foreign keys do not depend on the old composite unique index.
        $hasUserIdIndex = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'user_funnels')
            ->where('index_name', 'user_funnels_user_id_index')
            ->exists();

        if (!$hasUserIdIndex) {
            Schema::table('user_funnels', function (Blueprint $table) {
                $table->index('user_id', 'user_funnels_user_id_index');
            });
        }

        $hasFunnelIdIndex = DB::table('information_schema.statistics')
            ->where('table_schema', DB::raw('DATABASE()'))
            ->where('table_name', 'user_funnels')
            ->where('index_name', 'user_funnels_funnel_id_index')
            ->exists();

        if (!$hasFunnelIdIndex) {
            Schema::table('user_funnels', function (Blueprint $table) {
                $table->index('funnel_id', 'user_funnels_funnel_id_index');
            });
        }

        Schema::table('user_funnels', function (Blueprint $table) {
            $table->dropUnique('user_funnels_user_id_funnel_id_unique');
            $table->unique(
                ['user_id', 'funnel_id', 'patient_case_id'],
                'user_funnels_user_id_funnel_id_patient_case_id_unique'
            );
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_funnels', function (Blueprint $table) {
            $table->dropUnique('user_funnels_user_id_funnel_id_patient_case_id_unique');
            $table->unique(['user_id', 'funnel_id'], 'user_funnels_user_id_funnel_id_unique');
        });
    }
};
