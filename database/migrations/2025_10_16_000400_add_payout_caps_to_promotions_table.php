<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->decimal('max_payout_per_bill', 12, 2)->nullable()->after('global_budget');
            $table->decimal('max_payout_per_day', 12, 2)->nullable()->after('max_payout_per_bill');
            $table->decimal('max_payout_per_user', 12, 2)->nullable()->after('max_payout_per_day');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropColumn(['max_payout_per_bill', 'max_payout_per_day', 'max_payout_per_user']);
        });
    }
};


