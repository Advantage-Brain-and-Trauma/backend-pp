<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_submission_comments', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('form_submission_id');
            $table->text('comment');
            $table->unsignedBigInteger('commented_by');
            $table->timestamps();
            $table->softDeletes();

            $table->foreign('form_submission_id')
                  ->references('id')
                  ->on('form_submissions')
                  ->onDelete('cascade');

            $table->foreign('commented_by')
                  ->references('id')
                  ->on('users')
                  ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_submission_comments');
    }
};
