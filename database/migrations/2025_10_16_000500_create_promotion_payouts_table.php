<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('promotion_payouts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('promotion_id')->nullable();
            $table->string('promotion_code')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('bill_id')->nullable();
            $table->decimal('amount', 12, 2);
            $table->timestamps();

            $table->index(['promotion_code']);
            $table->index(['user_id']);
            $table->index(['created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotion_payouts');
    }
};


