<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Change users.patient_id from a single unsignedBigInteger to a JSON array.
 *
 * Existing single-integer values are wrapped into a one-element JSON array so
 * no historical data is lost.  New assignments always append to the array rather
 * than overwriting it.
 */
return new class extends Migration
{
    public function up(): void
    {
        // 1. Rename the old integer column so we can keep its data during the migration.
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('patient_id', 'patient_id_legacy');
        });

        // 2. Add the new JSON column in the same position.
        Schema::table('users', function (Blueprint $table) {
            $table->json('patient_id')->nullable()->after('id');
        });

        // 3. Wrap each existing integer value into a JSON array.
        DB::statement('UPDATE users SET patient_id = JSON_ARRAY(patient_id_legacy) WHERE patient_id_legacy IS NOT NULL');

        // 4. Drop the now-redundant legacy column.
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('patient_id_legacy');
        });
    }

    public function down(): void
    {
        // Restore the original single-integer column (uses the first array element).
        Schema::table('users', function (Blueprint $table) {
            $table->renameColumn('patient_id', 'patient_id_json');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('patient_id')->nullable()->after('id');
        });

        DB::statement("UPDATE users SET patient_id = JSON_UNQUOTE(JSON_EXTRACT(patient_id_json, '$[0]')) WHERE patient_id_json IS NOT NULL");

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('patient_id_json');
        });
    }
};
