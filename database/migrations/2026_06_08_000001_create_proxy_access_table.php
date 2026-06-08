<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxy_access', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('patient_user_id');   // patient who granted access
            $table->unsignedBigInteger('proxy_user_id')->nullable(); // null until invite accepted
            $table->string('proxy_email');
            $table->string('relationship');                  // e.g. Husband, Parent, Caregiver
            $table->enum('access_level', ['full', 'limited', 'read_only'])->default('full');
            $table->enum('status', ['pending', 'active', 'revoked', 'expired'])->default('pending');
            $table->string('invitation_token', 64)->nullable()->unique();
            $table->timestamp('token_expires_at')->nullable();
            $table->timestamp('invited_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->foreign('patient_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('proxy_user_id')->references('id')->on('users')->onDelete('set null');

            $table->index(['patient_user_id', 'status']);
            $table->index(['proxy_user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxy_access');
    }
};
