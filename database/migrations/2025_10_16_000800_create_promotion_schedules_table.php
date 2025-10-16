<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_schedules', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promotion_id');
            // 0=Sunday .. 6=Saturday
            $table->unsignedTinyInteger('day_of_week');
            $table->time('start_time');
            $table->time('end_time');
            $table->timestamps();

            $table->index(['promotion_id', 'day_of_week']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_schedules');
    }
};


