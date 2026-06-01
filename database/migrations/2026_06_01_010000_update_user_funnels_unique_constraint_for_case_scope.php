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

