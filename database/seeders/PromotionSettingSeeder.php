<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PromotionSettingSeeder extends Seeder
{
    public function run(): void
    {
        if (Schema::hasTable('promotions')) {
            $now = now();
            $promotions = [
                [
                    'name' => 'โปรโมชันรับเครดิตคืนเมื่อแพ้หมด',
                    'type' => 'lose_all_refund',
                    'is_active' => true,
                    'starts_at' => $now->copy()->subDay(),
                    'ends_at' => $now->copy()->addMonths(3),
                    'priority' => 10,
                    'is_stackable' => false,
                    'user_limit_total' => 5,
                    'user_limit_per_day' => 1,
                    'global_quota' => 100000,
                    'global_budget' => 200000.00,
                    'max_payout_per_bill' => 10000.00,
                    'max_payout_per_day' => 50000.00,
                    'max_payout_per_user' => 20000.00,
                    'match_periods' => json_encode(['full_time', 'first_half', 'second_half']),
                    'settings' => json_encode([
                        'min_stake' => 100,
                        'min_odds' => 1.85,
                        'min_selections' => 5,
                        'allowed_markets' => ['handicap', 'over_under', 'all'],
                        'allowed_sports' => ['football', 'mpy', 'all'],
                        'required_period' => 'full_time',
                        'multipliers' => [5 => 2.0, 6 => 5.0, 7 => 7.0, 8 => 10.0, 9 => 15.0, 10 => 30.0],
                        'max_payout_per_day' => 50000
                    ]),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
                [
                    'name' => 'โบนัสฝากครั้งแรก',
                    'type' => 'first_deposit_bonus',
                    'is_active' => true,
                    'starts_at' => $now->copy()->subDay(),
                    'ends_at' => $now->copy()->addMonths(2),
                    'priority' => 20,
                    'is_stackable' => false,
                    'user_limit_total' => 1,
                    'user_limit_per_day' => 1,
                    'global_quota' => 50000,
                    'global_budget' => 100000.00,
                    'max_payout_per_bill' => 1000.00,
                    'max_payout_per_day' => 5000.00,
                    'max_payout_per_user' => 2000.00,
                    'settings' => json_encode([
                        'bonus_percentage' => 100,
                        'max_bonus_amount' => 1000,
                        'min_deposit' => 100,
                        'wagering_requirement' => 3
                    ]),
                    'targeting' => json_encode(['user_level' => ['new']]),
                    'channels' => json_encode(['web']),
                    'created_at' => $now,
                    'updated_at' => $now,
                ],
            ];

            $hasTargeting = Schema::hasColumn('promotions', 'targeting');
            $hasChannels = Schema::hasColumn('promotions', 'channels');

            foreach ($promotions as $promotion) {
                $data = $promotion;
                if (!$hasTargeting) { unset($data['targeting']); }
                if (!$hasChannels) { unset($data['channels']); }

                DB::table('promotions')->updateOrInsert(
                    ['name' => $promotion['name']],
                    $data
                );
            }
        }

        // ไม่ใช้ promotion_settings แล้ว
    }
}


