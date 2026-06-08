<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('proxy_access_history', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('proxy_access_id');
            $table->unsignedBigInteger('proxy_user_id');
            $table->string('action');                       // e.g. "viewed Lab Results"
            $table->string('resource_type')->nullable();    // e.g. "clinical_note", "appointment"
            $table->string('resource_id')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->timestamp('accessed_at');
            $table->timestamps();

            $table->foreign('proxy_access_id')->references('id')->on('proxy_access')->onDelete('cascade');
            $table->foreign('proxy_user_id')->references('id')->on('users')->onDelete('cascade');

            $table->index(['proxy_access_id', 'accessed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('proxy_access_history');
    }
};
