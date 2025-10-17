<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->string('type')->nullable()->after('name');
            
            // Add index for type and priority
            $table->index(['type', 'priority']);
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            if (Schema::hasColumn('promotions', 'type')) {
                $table->dropIndex(['type', 'priority']);
                $table->dropColumn('type');
            }
        });
    }
};
