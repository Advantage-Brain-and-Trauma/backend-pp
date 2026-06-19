<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            if (!Schema::hasColumn('funnels', 'insurance_type')) {
                $table->string('insurance_type')->nullable()->after('description');
            }
        });
    }

    public function down(): void
    {
        Schema::table('funnels', function (Blueprint $table) {
            if (Schema::hasColumn('funnels', 'insurance_type')) {
                $table->dropColumn('insurance_type');
            }
        });
    }
};
