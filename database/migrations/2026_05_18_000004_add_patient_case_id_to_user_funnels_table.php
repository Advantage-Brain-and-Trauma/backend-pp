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
            $table->unsignedBigInteger('patient_case_id')->nullable()->after('patient_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('user_funnels', function (Blueprint $table) {
            $table->dropColumn('patient_case_id');
        });
    }
};
