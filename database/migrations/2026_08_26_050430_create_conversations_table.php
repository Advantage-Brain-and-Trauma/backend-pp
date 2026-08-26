<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {

            $table->id();

            /*
             * Public identifier used in broadcast channel names
             * (private-chat.{uuid}), never the numeric id.
             */
            $table->uuid('uuid')
                ->unique();

            /*
             * Dedup key for direct conversations: sorted
             * "{chatUserIdA}-{chatUserIdB}" pair, e.g. "3-9".
             */
            $table->string('conversation_key')
                ->unique();

            /*
             * direct
             */
            $table->string('type')
                ->default('direct');

            $table->timestamp('last_message_at')
                ->nullable();

            $table->timestamps();

            $table->index('last_message_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};