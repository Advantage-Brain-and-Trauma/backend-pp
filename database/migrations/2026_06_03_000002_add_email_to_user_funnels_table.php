<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Add an email column to user_funnels so that funnel assignments can be
 * matched back to a portal user even before the user has registered.
 *
 * When addPatientToFunnel is called the system looks up every user_funnels row
 * whose email matches the registering patient's email and consolidates all the
 * associated patient_ids into the users.patient_id JSON array.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_funnels', function (Blueprint $table) {
            $table->string('email')->nullable()->after('patient_case_id');
        });
    }

    public function down(): void
    {
        Schema::table('user_funnels', function (Blueprint $table) {
            $table->dropColumn('email');
        });
    }
};
