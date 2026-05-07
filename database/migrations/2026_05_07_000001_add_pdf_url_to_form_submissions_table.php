<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Add pdf_url column to form_submissions.
     *
     * Stores the generated PDF filename (e.g. Test_Testmh_20260505_070824.pdf)
     * after a form is submitted via the API.
     */
    public function up(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->string('pdf_url')->nullable()->after('status')
                  ->comment('Generated PDF filename stored in storage/app/public/form-pdfs/');
        });
    }

    public function down(): void
    {
        Schema::table('form_submissions', function (Blueprint $table) {
            $table->dropColumn('pdf_url');
        });
    }
};
