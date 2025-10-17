<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // สร้างตาราง promotions สำหรับจัดการโปรโมชันหลายๆ แบบ
        Schema::create('promotions', function (Blueprint $table) {
            $table->id()->comment('รหัสโปรโมชัน');
            $table->string('name')->unique()->comment('ชื่อโปรโมชัน ');
            $table->string('type')->comment('ประเภทโปรโมชัน');
            $table->boolean('is_active')->default(true)->comment('สถานะการใช้งาน (true=เปิดใช้งาน, false=ปิดใช้งาน)');

            // ช่วงเวลาและการจัดลำดับ
            $table->timestamp('starts_at')->nullable()->comment('วันที่และเวลาเริ่มต้นโปรโมชัน');
            $table->timestamp('ends_at')->nullable()->comment('วันที่และเวลาสิ้นสุดโปรโมชัน');
            $table->unsignedInteger('priority')->default(100)->comment('ลำดับความสำคัญ (ตัวเลขน้อย=สำคัญกว่า)');
            $table->boolean('is_stackable')->default(false)->comment('สามารถใช้ร่วมกับโปรโมชันอื่นได้หรือไม่');

            // ข้อจำกัด/โควตา
            $table->unsignedInteger('user_limit_total')->nullable()->comment('จำนวนครั้งที่ผู้ใช้ 1 คนสามารถใช้โปรโมชันนี้ได้ทั้งหมด');
            $table->unsignedInteger('user_limit_per_day')->nullable()->comment('จำนวนครั้งที่ผู้ใช้ 1 คนสามารถใช้โปรโมชันนี้ได้ต่อวัน');
            $table->unsignedBigInteger('global_quota')->nullable()->comment('จำนวนครั้งที่โปรโมชันนี้สามารถใช้ได้ทั้งหมดในระบบ');
            $table->decimal('global_budget', 12, 2)->nullable()->comment('งบประมาณรวมสำหรับโปรโมชันนี้');

            // เงื่อนไขแบบ JSON (รายละเอียดเฉพาะโปร)
            $table->json('settings')->comment('การตั้งค่าเฉพาะของโปรโมชันในรูปแบบ JSON');

            // Schedule columns - การตั้งค่าเวลา
            $table->json('schedule_days')->nullable()->comment('วันในสัปดาห์ที่โปรโมชันใช้งาน (1=จันทร์, 2=อังคาร, 3=พุธ, 4=พฤหัสบดี, 5=ศุกร์, 6=เสาร์, 7=อาทิตย์)');
            $table->time('schedule_start_time')->nullable()->comment('เวลาเริ่มต้นของโปรโมชันในแต่ละวัน');
            $table->time('schedule_end_time')->nullable()->comment('เวลาสิ้นสุดของโปรโมชันในแต่ละวัน');

            // Payout caps - ข้อจำกัดการจ่ายเงิน
            $table->decimal('max_payout_per_bill', 12, 2)->nullable()->comment('จำนวนเงินสูงสุดที่จ่ายได้ต่อบิล');
            $table->decimal('max_payout_per_day', 12, 2)->nullable()->comment('จำนวนเงินสูงสุดที่จ่ายได้ต่อวัน');
            $table->decimal('max_payout_per_user', 12, 2)->nullable()->comment('จำนวนเงินสูงสุดที่จ่ายให้ผู้ใช้ 1 คนได้ทั้งหมด');

            $table->timestamps(); // วันที่สร้างและแก้ไข
            $table->softDeletes(); // การลบแบบ soft delete

            // Indexes
            $table->index(['is_active', 'starts_at', 'ends_at'], 'promotions_active_time_idx')->comment('ดัชนีสำหรับค้นหาโปรโมชันที่ใช้งานได้ตามเวลา');
            $table->index(['type', 'priority'], 'promotions_type_priority_idx')->comment('ดัชนีสำหรับค้นหาตามประเภทและลำดับความสำคัญ');
            $table->index(['schedule_start_time', 'schedule_end_time'], 'promotions_schedule_time_idx')->comment('ดัชนีสำหรับค้นหาตามเวลาที่กำหนด');
        });

        // สร้างตาราง promotion_payouts สำหรับติดตามการจ่ายเงิน
        Schema::create('promotion_payouts', function (Blueprint $table) {
            $table->id()->comment('รหัสการจ่ายเงิน');
            $table->unsignedBigInteger('promotion_id')->nullable()->comment('รหัสโปรโมชันที่เกี่ยวข้อง');
            $table->unsignedBigInteger('user_id')->nullable()->comment('รหัสผู้ใช้ที่ได้รับเงิน');
            $table->string('bill_id')->nullable()->comment('รหัสบิลที่เกี่ยวข้อง');
            $table->string('transaction_id')->nullable()->comment('รหัสธุรกรรมการจ่าย');
            $table->string('status')->default('completed')->comment('สถานะการจ่าย เช่น completed, pending, failed');
            $table->decimal('amount', 12, 2)->comment('จำนวนเงินที่จ่าย');
            $table->timestamps(); // วันที่สร้างและแก้ไข

            $table->index(['promotion_id'], 'promotion_payouts_promo_idx')->comment('ดัชนีสำหรับค้นหาตามโปรโมชัน');
            $table->index(['user_id'], 'promotion_payouts_user_idx')->comment('ดัชนีสำหรับค้นหาตามผู้ใช้');
            $table->index(['created_at'], 'promotion_payouts_date_idx')->comment('ดัชนีสำหรับค้นหาตามวันที่');
            $table->index(['promotion_id', 'user_id'], 'promotion_payouts_promo_user_idx')->comment('ดัชนีสำหรับตรวจสอบยอดรวมต่อผู้ใช้/โปรโมชัน');
            $table->index(['promotion_id', 'user_id', 'created_at'], 'promotion_payouts_promo_user_date_idx')->comment('ดัชนีสำหรับตรวจสอบยอดรวมต่อวันต่อผู้ใช้/โปรโมชัน');
            // unique constraints
            $table->unique(['promotion_id', 'user_id', 'bill_id'], 'payout_unique_promo_user_bill');
            $table->unique(['transaction_id'], 'payout_unique_txn');
        });

        // ไม่ได้ใช้ตาราง promotion_schedules อีกต่อไป
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('promotion_payouts');
        Schema::dropIfExists('promotions');
    }
};