<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            if (Schema::hasColumn('promotions', 'code')) {
                $table->dropUnique('promotions_code_unique');
                $table->dropColumn('code');
            }
            $table->unique('name');
        });
    }

    public function down(): void
    {
        Schema::table('promotions', function (Blueprint $table) {
            $table->dropUnique('promotions_name_unique');
            $table->string('code')->unique()->nullable();
        });
    }
};


