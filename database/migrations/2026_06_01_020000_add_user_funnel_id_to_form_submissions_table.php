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
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->unsignedBigInteger('user_funnel_id')->nullable()->after('funnel_id');
            $table->index('user_funnel_id', 'form_submissions_user_funnel_id_index');
            $table->foreign('user_funnel_id')
                ->references('id')
                ->on('user_funnels')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropForeign(['user_funnel_id']);
            $table->dropIndex('form_submissions_user_funnel_id_index');
            $table->dropColumn('user_funnel_id');
        });
    }
};

