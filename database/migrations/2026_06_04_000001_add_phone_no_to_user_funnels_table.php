<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add a phone_no column to user_funnels so that SMS-based funnel assignments
 * can be matched back to a portal user when the patient registers via the
 * addPatientToFunnel (source=sms) flow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_funnels', function (Blueprint $table) {
            $table->string('phone_no', 30)->nullable()->after('email');
        });
    }

    public function down(): void
    {
        Schema::table('user_funnels', function (Blueprint $table) {
            $table->dropColumn('phone_no');
        });
    }
};
