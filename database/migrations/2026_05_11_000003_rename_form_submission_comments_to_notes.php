<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Rename the table
        Schema::rename('form_submission_comments', 'form_submission_notes');

        // Rename the column 'comment' to 'note'
        Schema::table('form_submission_notes', function (Blueprint $table) {
            $table->renameColumn('comment', 'note');
        });

        // Rename the column 'commented_by' to 'noted_by'
        Schema::table('form_submission_notes', function (Blueprint $table) {
            $table->renameColumn('commented_by', 'noted_by');
        });
    }

    public function down(): void
    {
        Schema::table('form_submission_notes', function (Blueprint $table) {
            $table->renameColumn('noted_by', 'commented_by');
        });

        Schema::table('form_submission_notes', function (Blueprint $table) {
            $table->renameColumn('note', 'comment');
        });

        Schema::rename('form_submission_notes', 'form_submission_comments');
    }
};
