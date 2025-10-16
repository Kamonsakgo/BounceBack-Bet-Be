<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            if (Schema::hasColumn('promotions', 'type')) {
                $table->dropIndex(['type', 'priority']);
                $table->dropColumn('type');
            }
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->enum('type', ['lose_all_refund','first_deposit_bonus','odds_boost','bet_insurance'])->nullable();
            $table->index(['type', 'priority']);
        });
    }
};


