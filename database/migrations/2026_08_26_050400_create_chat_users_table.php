<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_users', function (Blueprint $table) {

            $table->id();

            /*
             * Public identity handed to both sides of the chat
             * (Patient Portal / doctor systems) instead of their
             * own source-system id.
             */
            $table->uuid('uuid')
                ->unique();

            /*
             * doctor / patient / ...
             */
            $table->string('external_type');

            /*
             * ID from the source system (patient_id, physician id, ...).
             */
            $table->unsignedBigInteger('external_id');

            $table->string('name')
                ->nullable();

            $table->string('status')
                ->default('active');

            $table->timestamps();

            $table->unique([
                'external_type',
                'external_id',
            ]);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_users');
    }
};
