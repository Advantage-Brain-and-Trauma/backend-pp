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
            $table->dropForeign(['user_id']);

            $table->foreignId('user_id')
                ->nullable()
                ->change();

            $table->foreign('user_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();

            $table->unsignedBigInteger('patient_id')->nullable()->after('funnel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_funnels', function (Blueprint $table) {
            //
        });
    }
};
