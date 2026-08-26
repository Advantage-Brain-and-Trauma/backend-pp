<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {

            $table->id();

            $table->uuid('uuid')
                ->unique();

            $table->foreignId(
                'conversation_id'
            )
            ->constrained(
                'conversations'
            )
            ->cascadeOnDelete();

            $table->foreignId('sender_chat_user_id')
                ->constrained('chat_users')
                ->cascadeOnDelete();

            $table->text('message')
                ->nullable();

            /*
             * text / image / file
             */
            $table->string(
                'message_type'
            )->default('text');

            $table->string(
                'attachment'
            )->nullable();

            $table->timestamp(
                'read_at'
            )->nullable();

            $table->timestamps();

            $table->index(
                'conversation_id'
            );

            $table->index('read_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_messages');
    }
};
