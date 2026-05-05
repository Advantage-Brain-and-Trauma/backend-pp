<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('user_funnels', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->onDelete('cascade');
            $table->foreignId('funnel_id')->constrained('funnels')->onDelete('cascade');
            $table->string('assigned_via')->default('share_link'); // share_link, admin, etc.
            $table->timestamp('assigned_at')->useCurrent();
            $table->timestamps();

            $table->unique(['user_id', 'funnel_id']); // prevent duplicates
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('user_funnels');
    }
};
