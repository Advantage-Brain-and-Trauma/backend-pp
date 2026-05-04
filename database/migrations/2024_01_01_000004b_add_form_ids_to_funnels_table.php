<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            if (!Schema::hasColumn('funnels', 'form_ids')) {
                $table->json('form_ids')->nullable()->after('steps');
            }
        });
    }

    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            if (Schema::hasColumn('funnels', 'form_ids')) {
                $table->dropColumn('form_ids');
            }
        });
    }
};
