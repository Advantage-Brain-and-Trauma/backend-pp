<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_sessions', function (Blueprint $table) {

            $table->id();

            /*
             * SHA256 hash of the actual chat token.
             */
            $table->string('token_hash')
                ->unique();

            $table->foreignId('chat_user_id')
                ->constrained('chat_users')
                ->cascadeOnDelete();

            $table->timestamp('expires_at');

            $table->timestamps();

            $table->index('expires_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_sessions');
    }
};
