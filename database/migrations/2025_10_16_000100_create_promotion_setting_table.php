<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // สร้างตาราง promotions สำหรับจัดการโปรโมชันหลายๆ แบบ
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // ex: LOSE_REFUND_V1
            $table->string('name');
            $table->enum('type', [
                'lose_all_refund', 'first_deposit_bonus', 'odds_boost', 'bet_insurance'
            ]);
            $table->boolean('is_active')->default(true);

            // ช่วงเวลาและการจัดลำดับ
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedInteger('priority')->default(100); // ตัวเลขน้อย=สำคัญกว่า
            $table->boolean('is_stackable')->default(false);   // ซ้อนกับโปรอื่นได้ไหม

            // ข้อจำกัด/โควตา
            $table->unsignedInteger('user_limit_total')->nullable(); // ผู้ใช้ 1 คนใช้ได้กี่ครั้งรวม
            $table->unsignedInteger('user_limit_per_day')->nullable();
            $table->unsignedBigInteger('global_quota')->nullable();  // ใช้ได้รวมทั้งระบบกี่ครั้ง
            $table->decimal('global_budget', 12, 2)->nullable();     // งบรวม

            // เงื่อนไขแบบ JSON (รายละเอียดเฉพาะโปร)
            $table->json('settings'); // ดูตัวอย่างสคีมาด้านล่าง

            $table->timestamps();
            $table->softDeletes();

            $table->index(['is_active', 'starts_at', 'ends_at']);
            $table->index(['type', 'priority']);
        });

        // สร้างตาราง promotion_settings สำหรับการตั้งค่าแบบ key-value (backward compatibility)
        Schema::create('promotion_settings', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('promotions');
        Schema::dropIfExists('promotion_settings');
    }
};


