<?php

namespace App\Providers;

use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Ensure pdf_url column exists in form_submissions (safe idempotent check)
        if (Schema::hasTable('form_submissions') && !Schema::hasColumn('form_submissions', 'pdf_url')) {
            Schema::table('form_submissions', function ($table) {
                $table->string('pdf_url')->nullable()->after('status')
                      ->comment('Generated PDF filename stored in storage/app/public/form-pdfs/');
            });
        }
    }
}
