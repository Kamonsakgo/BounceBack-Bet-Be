<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('promotion_settings')) {
            Schema::drop('promotion_settings');
        }
        if (Schema::hasTable('PromotionSetting')) {
            Schema::drop('PromotionSetting');
        }
    }

    public function down(): void
    {
        // ไม่สร้างตารางเก่าคืน เพื่อหลีกเลี่ยงสับสนของสคีมา
    }
};


