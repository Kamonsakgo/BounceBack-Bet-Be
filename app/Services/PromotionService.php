<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PromotionService
{
    /**
     * ประเมินสิทธิ์โปรโมชันรับเครดิตคืนสำหรับบิลสเต็ป
     *
     * โครงสร้างข้อมูลที่คาดหวัง (payload):
     * - stake: จำนวนเงินเดิมพัน float (>= 100)
     * - sport: ชนิดกีฬา string ('football' | 'muaythai')
     * - selections: รายการคู่ที่แทง array ของอ็อบเจ็กต์ที่มีคีย์:
     *     result: 'lose'|'win'|'void'
     *     market: 'handicap'|'over_under'
     *     period: 'full_time'
     *     odds: ค่าน้ำ float
     */
    public function evaluate(array $payload): array
    {
        $stake = (float)($payload['stake'] ?? 0);
        $selections = $payload['selections'] ?? [];

        $reasons = [];

        // 1) พยายามอ่านค่าจากตาราง promotions (โปรโมชันหลายแบบ) ก่อน
        // เงื่อนไข: type = lose_all_refund, is_active = true, อยู่ในช่วงเวลา, เรียงลำดับตาม priority
        $settings = [];
        $activePromotion = null;
        if (Schema::hasTable('promotions')) {
            $now = now();
            // allow selecting by promotion_code or promotion_type from payload
            $requestedId = isset($payload['promotion_id']) ? (int)$payload['promotion_id'] : null;
            $requestedName = isset($payload['promotion_name']) ? (string)$payload['promotion_name'] : null;

            $query = DB::table('promotions')
                ->where('is_active', true)
                ->where(function ($q) use ($now) {
                    $q->whereNull('starts_at')->orWhere('starts_at', '<=', $now);
                })
                ->where(function ($q) use ($now) {
                    $q->whereNull('ends_at')->orWhere('ends_at', '>=', $now);
                })
                ->orderBy('priority', 'asc');

            if (!empty($requestedId)) {
                $query->where('id', $requestedId);
            } elseif (!empty($requestedName)) {
                $query->where('name', $requestedName);
            }

            $activePromotion = $query->first();

            if ($activePromotion && isset($activePromotion->settings)) {
                $decoded = json_decode($activePromotion->settings, true);
                if (is_array($decoded)) {
                    $settings = $decoded;
                }
            }
            
        }

        // ไม่ใช้ตาราง promotion_schedules อีกต่อไป

        // ตรวจสอบเงื่อนไขเวลาในตาราง promotions (schedule_days, schedule_start_time, schedule_end_time)
        if ($activePromotion) {
            $now = now();
            $currentDayOfWeek = $now->dayOfWeek; // 0=อาทิตย์, 1=จันทร์, ..., 6=เสาร์
            $currentTime = $now->format('H:i:s');
            
            // เช็ค schedule_days (JSON array)
            if ($activePromotion->schedule_days) {
                $scheduleDays = json_decode($activePromotion->schedule_days, true);
                if (is_array($scheduleDays) && !empty($scheduleDays)) {
                    // แปลง day of week ให้ตรงกับ format ที่เก็บ (1=จันทร์, 2=อังคาร, ..., 7=อาทิตย์)
                    $dayMapping = [0 => 7, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6]; // 0=อาทิตย์ -> 7
                    $currentDay = $dayMapping[$currentDayOfWeek];
                    
                    if (!in_array($currentDay, $scheduleDays)) {
                        $dayNames = ['', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์', 'อาทิตย์'];
                        $allowedDays = array_map(fn($d) => $dayNames[$d] ?? $d, $scheduleDays);
                        $reasons[] = 'โปรโมชันไม่เปิดในวันนี้ (เปิดเฉพาะ: ' . implode(', ', $allowedDays) . ') | Promotion not available today';
                    }
                }
            }
            
            // เช็ค schedule_start_time และ schedule_end_time
            if ($activePromotion->schedule_start_time && $activePromotion->schedule_end_time) {
                $startTime = $activePromotion->schedule_start_time;
                $endTime = $activePromotion->schedule_end_time;
                
                if ($currentTime < $startTime || $currentTime > $endTime) {
                    $reasons[] = 'เวลาไม่อยู่ในช่วงเปิดโปรโมชัน (เปิด: ' . $startTime . ' - ' . $endTime . ') | Promotion time not available';
                }
            }
        }

        // 2) ไม่ fallback ไปที่ promotion_settings อีกต่อไป (เลิกใช้ตารางนั้น)

        $minStakeVal = $settings['min_stake'] ?? null;
        $minStake = is_numeric($minStakeVal) ? (float)$minStakeVal : 100.0;

        $minOddsVal = $settings['min_odds'] ?? null;
        $minOdds = is_numeric($minOddsVal) ? (float)$minOddsVal : 1.85;

        // เช็ค max_refund_amount
        $maxRefundAmountVal = $settings['max_refund_amount'] ?? null;
        $maxRefundAmount = is_numeric($maxRefundAmountVal) ? (float)$maxRefundAmountVal : null;

        // เช็ค min_loss_per_pair
        $minLossPerPairVal = $settings['min_loss_per_pair'] ?? null;
        $minLossPerPair = is_numeric($minLossPerPairVal) ? (float)$minLossPerPairVal : null;

        // market_type หรือ market_types อาจมาเป็น array (จาก promotions.settings) หรือ string csv (จาก key-value)
        $allowedMarketsRaw = $settings['market_type'] ?? $settings['market_types'] ?? null;
        if (is_array($allowedMarketsRaw)) {
            $allowedMarkets = array_values(array_filter(array_map(fn($m) => strtolower(trim((string)$m)), $allowedMarketsRaw)));
        } elseif (is_string($allowedMarketsRaw) && trim($allowedMarketsRaw) !== '') {
            $allowedMarkets = array_values(array_filter(array_map(fn($m) => strtolower(trim($m)), explode(',', $allowedMarketsRaw))));
        } else {
            $allowedMarkets = ['handicap', 'over_under'];
        }
        // รองรับค่า 'all' เพื่ออนุญาตทุกตลาด
        $allowAllMarkets = in_array('all', $allowedMarkets, true);

        $requiredPeriodVal = $settings['required_period'] ?? null;
        $requiredPeriod = is_string($requiredPeriodVal) && $requiredPeriodVal !== '' ? strtolower($requiredPeriodVal) : 'full_time';

        // อ่านจำนวนคู่ขั้นต่ำจากฐานข้อมูล พร้อม fallback
        $value = $settings['min_selections'] ?? null;
        if (is_numeric($value)) {
            $minSelections = (int)$value;
        } else {
            // ถ้าไม่มี min_selections ให้ใช้ tiers ที่ต่ำที่สุด
            if (isset($settings['tiers']) && is_array($settings['tiers'])) {
                $minSelections = min(array_column($settings['tiers'], 'pairs'));
            } else {
                $minSelections = 3;
            }
        }
        
        // Debug: แสดงค่า min_selections ที่ได้
        if (isset($settings['min_selections'])) {
            $reasons[] = 'Debug: min_selections = ' . $settings['min_selections'];
        }

        // กีฬาอนุญาต: อาจมาเป็น array หรือ csv string
        $allowedSportsRaw = $settings['allowed_sports'] ?? $settings['betting_types'] ?? 'football,boxing';
        if (is_array($allowedSportsRaw)) {
            $allowedSports = array_values(array_filter(array_map(fn($s) => strtolower(trim((string)$s)), $allowedSportsRaw)));
        } elseif (is_string($allowedSportsRaw) && trim($allowedSportsRaw) !== '') {
            $allowedSports = array_values(array_filter(array_map(fn($s) => strtolower(trim($s)), explode(',', $allowedSportsRaw))));
        } else {
            $allowedSports = ['football'];
        }

        // รองรับค่า 'all' เพื่ออนุญาตทุกกีฬา
        $allowAllSports = in_array('all', $allowedSports, true);

        // ตัวคูณเครดิตคืนกรณี "ผิดหมดทุกคู่"
        // รองรับรูปแบบจาก promotions.settings.multipliers (array) หรือจาก key-value: multiplier_5 ... multiplier_10

        $multipliersByCount = [];
        
        // ตรวจสอบ tiers ก่อน
        if (isset($settings['tiers']) && is_array($settings['tiers'])) {
            foreach ($settings['tiers'] as $tier) {
                if (isset($tier['pairs']) && isset($tier['multiplier'])) {
                    $multipliersByCount[$tier['pairs']] = (float)$tier['multiplier'];
                }
            }
        }
        
        // ถ้ายังไม่มี tiers ให้ใช้ multipliers
        if (empty($multipliersByCount) && isset($settings['multipliers']) && is_array($settings['multipliers'])) {
            foreach ($defaultMultipliers as $countKey => $defaultValue) {
                $val = $settings['multipliers'][$countKey] ?? null;
                $multipliersByCount[$countKey] = is_numeric($val) ? (float)$val : $defaultValue;
            }
        }
        
        // ถ้ายังไม่มีอะไรเลย ให้ใช้ default
        if (empty($multipliersByCount)) {
            foreach ($defaultMultipliers as $countKey => $defaultValue) {
                $dbVal = $settings['multiplier_' . $countKey] ?? null;
                $multipliersByCount[$countKey] = is_numeric($dbVal) ? (float)$dbVal : $defaultValue;
            }
        }

        // ตรวจเงื่อนไข: ยอดเดิมพันขั้นต่ำ
        if ($stake < $minStake) {
            $minStr = rtrim(rtrim(number_format($minStake, 2, '.', ''), '0'), '.');
            $reasons[] = 'ยอดแทงขั้นต่ำต้องไม่น้อยกว่า ' . $minStr . ' | Stake must be at least ' . $minStr;
        }


        // ตรวจเงื่อนไข: จำนวนคู่ขั้นต่ำ และคุณสมบัติของคู่ที่แทง
        if (!is_array($selections) || count($selections) < $minSelections) {
            $reasons[] = 'จำนวนคู่ไม่ถึงขั้นต่ำ (ต้อง >= ' . $minSelections . ')';
        }

        $hasVoidOrCancelled = false;
        $allLose = true;
        $allMarketsEligible = true;
        $allPeriodsEligible = true;
        $allOddsEligible = true;
        $allLossPerPairEligible = true;

        foreach ($selections as $i => $sel) {
            $result = strtolower((string)($sel['result'] ?? ''));
            // รองรับทั้ง market และ market_type
            $marketRaw = (string)($sel['market'] ?? ($sel['market_type'] ?? ''));
            $market = strtolower($marketRaw);
            // period ถ้าไม่ส่งมา ให้ถือเป็น full_time
            $period = strtolower((string)($sel['period'] ?? 'full_time'));
            $odds = (float)($sel['odds'] ?? 0);
            // ถ้าส่ง status=cancel ให้ถือว่าโมฆะ/ยกเลิก
            $status = strtolower((string)($sel['status'] ?? 'accept'));
            // ตรวจสอบ sport ของแต่ละคู่ (บังคับ)
            $selSport = strtolower((string)($sel['sport'] ?? ''));
            if (!$allowAllSports && !in_array($selSport, $allowedSports, true)) {
                $list = array_values(array_filter($allowedSports, fn($s) => $s !== 'all'));
                $listStr = empty($list) ? 'ทุกกีฬา' : implode(',', $list);
                $reasons[] = 'กีฬาในคู่ที่ ' . ($i + 1) . ' ไม่เข้าเงื่อนไข (อนุญาต: ' . $listStr . ') | Selection sport not eligible (allowed: ' . $listStr . ')';
            }

            if ($result === 'void' || $result === 'cancelled' || $result === 'canceled' || $status === 'cancel') {
                $hasVoidOrCancelled = true;
            }
            if ($result !== 'lose') {
                $allLose = false;
            }
            if (!$allowAllMarkets && !in_array($market, $allowedMarkets, true)) {
                $allMarketsEligible = false;
            }
            if ($period !== $requiredPeriod) {
                $allPeriodsEligible = false;
            }
            if ($odds < $minOdds) {
                $allOddsEligible = false;
            }
            
            // เช็ค min_loss_per_pair: การขาดทุนต่อคู่ต้อง >= minLossPerPair
            if ($minLossPerPair !== null && $result === 'lose') {
                $lossPerPair = $stake / count($selections); // แบ่ง stake เท่าๆ กันทุกคู่
                if ($lossPerPair < $minLossPerPair) {
                    $allLossPerPairEligible = false;
                }
            }
        }

        if ($hasVoidOrCancelled) {
            $reasons[] = 'มีคู่ที่โมฆะ/ยกเลิก ทำให้บิลไม่เข้าเงื่อนไข | Any void/cancelled selection disqualifies the bill';
        }
        if (!$allLose) {
            $reasons[] = 'ทุกรายการต้องแพ้ (lose) เท่านั้น | All selections must be a full loss';
        }
        if (!$allMarketsEligible) {
            $mk = array_values(array_filter($allowedMarkets, fn($m) => $m !== 'all'));
            $mkStr = empty($mk) ? 'ทุกตลาด' : implode(',', $mk);
            $reasons[] = 'ตลาดไม่เข้าเงื่อนไข (อนุญาต: ' . $mkStr . ') | Only allowed markets: ' . $mkStr;
        }
        if (!$allPeriodsEligible) {
            $reasons[] = 'นับเฉพาะเต็มเวลา (full_time) เท่านั้น | Only full-time markets are eligible';
        }
        if (!$allOddsEligible) {
            $minOddsStr = rtrim(rtrim(number_format($minOdds, 2, '.', ''), '0'), '.');
            $reasons[] = 'ค่าน้ำของแต่ละคู่ต้อง >= ' . $minOddsStr . ' | Each selection odds must be >= ' . $minOddsStr;
        }
        if (!$allLossPerPairEligible) {
            $minLossStr = rtrim(rtrim(number_format($minLossPerPair, 2, '.', ''), '0'), '.');
            $reasons[] = 'การขาดทุนต่อคู่ต้อง >= ' . $minLossStr . ' | Loss per pair must be >= ' . $minLossStr;
        }

        $count = is_array($selections) ? count($selections) : 0;
        // เลือกตัวคูณตามจำนวนคู่: ถ้าเกิน tier สูงสุด ให้ใช้ tier สูงสุด
        if (!empty($multipliersByCount)) {
            $eligibleCounts = array_keys($multipliersByCount);
            sort($eligibleCounts, SORT_NUMERIC);
            $chosenCount = null;
            foreach ($eligibleCounts as $eligibleCount) {
                if ($eligibleCount <= $count) {
                    $chosenCount = $eligibleCount;
                } else {
                    break;
                }
            }
            if ($chosenCount === null) {
                // จำนวนน้อยกว่าขั้นต่ำใดๆ -> ไม่เข้าเงื่อนไข
                $multiplier = 0.0;
            } else {
                // ถ้ามากกว่าขั้นสูงสุด chosenCount จะเป็นขั้นสูงสุดโดยอัตโนมัติ
                $multiplier = (float)$multipliersByCount[$chosenCount];
            }
        } else {
            $multiplier = 0.0;
        }
        
        
        if ($multiplier <= 0.0) {
            $reasons[] = 'จำนวนคู่ไม่เข้าเงื่อนไขของโปรโมชัน | Selections count not eligible for promotion';
        }

        $eligible = count($reasons) === 0;

        $refund = 0.0;
        if ($eligible) {
            // ตีความตามโปรโมชัน: รับเครดิตคืนตามตัวคูณ X เท่า
            // คำนวณโดยใช้ stake * multiplier
            $refund = $stake * $multiplier;
        }

        // Cap ตามคอลัมน์ใน promotions: ต่อบิล/ต่อวัน/ต่อผู้ใช้ (ถ้ามีค่า)
        $maxPayoutPerBill = null;
        $maxPayoutPerDay = null;
        $maxPayoutPerUser = null;
        if ($activePromotion) {
            // บาง DB driver จะคืนค่าเป็น string -> แปลงเป็น float ถ้าเป็นตัวเลข
            $maxPayoutPerBill = is_numeric($activePromotion->max_payout_per_bill ?? null)
                ? (float)$activePromotion->max_payout_per_bill : null;
            $maxPayoutPerDay = is_numeric($activePromotion->max_payout_per_day ?? null)
                ? (float)$activePromotion->max_payout_per_day : null;
            $maxPayoutPerUser = is_numeric($activePromotion->max_payout_per_user ?? null)
                ? (float)$activePromotion->max_payout_per_user : null;
        }

        // ตอนนี้บังคับใช้ cap ต่อบิลทันที (per-day และ per-user ให้ชั้นเรียกใช้ไปจัดการ aggregation)
        $cappedRefund = $refund;
        if ($maxPayoutPerBill !== null) {
            $cappedRefund = min($cappedRefund, $maxPayoutPerBill);
        }
        
        // เช็ค max_refund_amount จาก settings
        if ($maxRefundAmount !== null) {
            $cappedRefund = min($cappedRefund, $maxRefundAmount);
        }

        // เช็ค user_limit_total และ user_limit_per_day
        if ($activePromotion && $eligible) {
            $userId = $payload['user_id'] ?? null;
            if ($userId) {
                // เช็ค user_limit_total (จำนวนครั้งที่ใช้ได้ทั้งหมด)
                if ($activePromotion->user_limit_total) {
                    $totalUsage = DB::table('promotion_payouts')
                        ->where('promotion_id', $activePromotion->id)
                        ->where('user_id', $userId)
                        ->count();
                    
                    if ($totalUsage >= $activePromotion->user_limit_total) {
                        $eligible = false;
                        $reasons[] = 'คุณใช้โปรโมชันนี้ครบจำนวนครั้งที่อนุญาตแล้ว (' . $activePromotion->user_limit_total . ' ครั้ง)';
                    }
                }

                // เช็ค user_limit_per_day (จำนวนครั้งที่ใช้ได้ต่อวัน)
                if ($activePromotion->user_limit_per_day) {
                    $todayUsage = DB::table('promotion_payouts')
                        ->where('promotion_id', $activePromotion->id)
                        ->where('user_id', $userId)
                        ->whereDate('created_at', today())
                        ->count();
                    
                    if ($todayUsage >= $activePromotion->user_limit_per_day) {
                        $eligible = false;
                        $reasons[] = 'คุณใช้โปรโมชันนี้ครบจำนวนครั้งที่อนุญาตต่อวันแล้ว (' . $activePromotion->user_limit_per_day . ' ครั้ง)';
                    }
                }
            }
        }

        return [
            'eligible' => $eligible,
            'reasons' => $reasons,
            'selectionsCount' => $count,
            'multiplier' => $multiplier,
            'stake' => $stake,
            'computedRefund' => $refund,
            'cappedRefund' => $cappedRefund,
            'caps' => [
                'maxPayoutPerBill' => $maxPayoutPerBill,
                'maxPayoutPerDay' => $maxPayoutPerDay,
                'maxPayoutPerUser' => $maxPayoutPerUser,
            ],
            // metadata เพื่อช่วยระบบภายนอกทำ aggregation cap ต่อวัน/ต่อผู้ใช้
            'promotion' => $activePromotion ? [
                'id' => $activePromotion->id,
                'name' => $activePromotion->name,
            ] : null,
        ];
    }

    /**
     * จ่ายโปรโมชันตาม type
     */
    public function payout(array $payload): array
    {
        $promotionId = $payload['promotion_id'] ?? null;
        $userId = $payload['user_id'] ?? null;
        $amount = $payload['amount'] ?? 0;
        $transactionId = $payload['transaction_id'] ?? null;

        $reasons = [];

        // ตรวจสอบข้อมูลที่จำเป็น
        if (!$promotionId) {
            $reasons[] = 'ต้องระบุ promotion_id';
        }
        if (!$userId) {
            $reasons[] = 'ต้องระบุ user_id';
        }
        if ($amount <= 0) {
            $reasons[] = 'จำนวนเงินต้องมากกว่า 0';
        }

        if (count($reasons) > 0) {
            return [
                'success' => false,
                'reasons' => $reasons,
                'payout' => 0,
                'transaction_id' => null
            ];
        }

        // ดึงข้อมูลโปรโมชัน
        $promotion = DB::table('promotions')->where('id', $promotionId)->first();
        if (!$promotion) {
            return [
                'success' => false,
                'reasons' => ['ไม่พบโปรโมชัน'],
                'payout' => 0,
                'transaction_id' => null
            ];
        }

        // ตรวจสอบสถานะโปรโมชัน
        if (!$promotion->is_active) {
            return [
                'success' => false,
                'reasons' => ['โปรโมชันปิดใช้งาน'],
                'payout' => 0,
                'transaction_id' => null
            ];
        }

        // ตรวจสอบช่วงเวลา
        $now = now();
        if ($promotion->starts_at && $now < $promotion->starts_at) {
            return [
                'success' => false,
                'reasons' => ['โปรโมชันยังไม่เริ่ม'],
                'payout' => 0,
                'transaction_id' => null
            ];
        }
        if ($promotion->ends_at && $now > $promotion->ends_at) {
            return [
                'success' => false,
                'reasons' => ['โปรโมชันหมดอายุ'],
                'payout' => 0,
                'transaction_id' => null
            ];
        }

        // หมายเหตุ: การตรวจ global_quota จะทำแบบ remaining counter ภายใน transaction ด้านล่าง

        // ตรวจสอบเงื่อนไขเวลาในตาราง promotions (schedule_days, schedule_start_time, schedule_end_time)
        $currentDayOfWeek = $now->dayOfWeek; // 0=อาทิตย์, 1=จันทร์, ..., 6=เสาร์
        $currentTime = $now->format('H:i:s');
        
        // เช็ค schedule_days (JSON array)
        if ($promotion->schedule_days) {
            $scheduleDays = json_decode($promotion->schedule_days, true);
            if (is_array($scheduleDays) && !empty($scheduleDays)) {
                // แปลง day of week ให้ตรงกับ format ที่เก็บ (1=จันทร์, 2=อังคาร, ..., 7=อาทิตย์)
                $dayMapping = [0 => 7, 1 => 1, 2 => 2, 3 => 3, 4 => 4, 5 => 5, 6 => 6]; // 0=อาทิตย์ -> 7
                $currentDay = $dayMapping[$currentDayOfWeek];
                
                if (!in_array($currentDay, $scheduleDays)) {
                    $dayNames = ['', 'จันทร์', 'อังคาร', 'พุธ', 'พฤหัสบดี', 'ศุกร์', 'เสาร์', 'อาทิตย์'];
                    $allowedDays = array_map(fn($d) => $dayNames[$d] ?? $d, $scheduleDays);
                    return [
                        'success' => false,
                        'reasons' => ['โปรโมชันไม่เปิดในวันนี้ (เปิดเฉพาะ: ' . implode(', ', $allowedDays) . ')'],
                        'payout' => 0,
                        'transaction_id' => null
                    ];
                }
            }
        }
        
        // เช็ค schedule_start_time และ schedule_end_time
        if ($promotion->schedule_start_time && $promotion->schedule_end_time) {
            $startTime = $promotion->schedule_start_time;
            $endTime = $promotion->schedule_end_time;
            
            if ($currentTime < $startTime || $currentTime > $endTime) {
                return [
                    'success' => false,
                    'reasons' => ['เวลาไม่อยู่ในช่วงเปิดโปรโมชัน (เปิด: ' . $startTime . ' - ' . $endTime . ')'],
                    'payout' => 0,
                    'transaction_id' => null
                ];
            }
        }

        // คำนวณการจ่ายตาม type
        $payoutAmount = $this->calculatePayoutByType($promotion, $amount, $payload);

        // ตรวจสอบ caps (ต่อบิล/ต่อวัน/ต่อผู้ใช้/งบรวม)
        $payoutAmount = $this->applyCaps($promotion, $payoutAmount, $userId);

        // หากถูกจำกัดจนเป็น 0 ไม่ต้องบันทึก และแจ้งเหตุผลให้ชัดเจน
        if ($payoutAmount <= 0) {
            $capReasons = $this->getCapFailureReasons($promotion, $userId, (float)$amount);
            if (empty($capReasons)) {
                $capReasons = ['จำนวนเงินคำนวณหลังจำกัดเป็น 0 (งบ/เพดานครบแล้ว)'];
            }
            return [
                'success' => false,
                'reasons' => $capReasons,
                'payout' => 0,
                'transaction_id' => null
            ];
        }

        // ระยะสั้น: ใช้ทรานแซกชัน + lock แถว promotions เพื่อลด race-condition และอัปเดตยอดแบบหักออกจริง
        return DB::transaction(function () use ($promotion, $userId, $payoutAmount, $transactionId) {
            // ล็อกแถวโปรโมชัน
            $locked = DB::table('promotions')->where('id', $promotion->id)->lockForUpdate()->first();

            // ตรวจซ้ำ global_quota แบบ remaining counter (ใช้ค่าที่เหลือจริงในคอลัมน์)
            if (!is_null($locked->global_quota)) {
                if ((int)$locked->global_quota <= 0) {
                    return [
                        'success' => false,
                        'reasons' => ['โปรโมชันถึงจำนวนครั้งสูงสุดของระบบแล้ว'],
                        'payout' => 0,
                        'transaction_id' => null
                    ];
                }
            }

            // ตรวจซ้ำงบรวม และปรับยอดให้ไม่เกินงบคงเหลือ
            $effectivePayout = $payoutAmount;
            if (!is_null($locked->global_budget)) {
                $remainingBudget = max(0.0, (float)$locked->global_budget);
                if ($remainingBudget <= 0 || $effectivePayout > $remainingBudget) {
                    $capReasons = $this->getCapFailureReasons($locked, $userId, (float)$effectivePayout);
                    return [
                        'success' => false,
                        'reasons' => !empty($capReasons) ? $capReasons : ['งบประมาณรวมไม่เพียงพอ'],
                        'payout' => 0,
                        'transaction_id' => null
                    ];
                }
            }

            // อัปเดตยอดคงเหลือใน promotions (หักออกจริง)
            $updates = [];
            if (!is_null($locked->global_quota)) {
                $updates['global_quota'] = max(0, (int)$locked->global_quota - 1);
            }
            if (!is_null($locked->global_budget)) {
                $updates['global_budget'] = max(0, (float)$locked->global_budget - (float)$effectivePayout);
            }
            if (!empty($updates)) {
                DB::table('promotions')->where('id', $locked->id)->update($updates);
            }

            // บันทึกการจ่ายด้วยยอดที่ปรับแล้ว
            $finalTxnId = $this->recordPayout($locked, $userId, $effectivePayout, $transactionId);

            return [
                'success' => true,
                'reasons' => [],
                'payout' => $effectivePayout,
                'transaction_id' => $finalTxnId,
                'promotion' => [
                    'id' => $locked->id,
                    'name' => $locked->name,
                    'type' => $locked->type
                ]
            ];
        });
    }

    /**
     * คำนวณการจ่ายตาม type
     */
    private function calculatePayoutByType($promotion, $amount, $payload)
    {
        $type = $promotion->type;
        $settings = json_decode($promotion->settings, true) ?? [];

        switch ($type) {
            case 'lose_all_refund':
                // รับเครดิตคืนเมื่อแพ้หมด - ใช้ตัวคูณ
                $multiplier = $settings['multiplier'] ?? 1;
                return $amount * $multiplier;

            case 'lose_and_get_back':
                // แพ้แล้วได้คืน - ใช้ tier system
                if (isset($settings['tiers']) && is_array($settings['tiers'])) {
                    $selectionsCount = $payload['selections_count'] ?? 0;
                    return $this->calculateTierPayout($amount, $selectionsCount, $settings['tiers']);
                }
                return $amount;

            case 'first_deposit_bonus':
                // โบนัสฝากครั้งแรก - เปอร์เซ็นต์
                $bonusPercent = $settings['bonus_percent'] ?? 100;
                return $amount * ($bonusPercent / 100);

            case 'odds_boost':
                // เพิ่มค่าน้ำ - เปอร์เซ็นต์เพิ่ม
                $boostPercent = $settings['boost_percent'] ?? 10;
                return $amount * ($boostPercent / 100);

            case 'bet_insurance':
                // ประกันการแทง - คืนเต็มจำนวน
                return $amount;

            default:
                return $amount;
        }
    }

    /**
     * คำนวณ tier payout
     */
    private function calculateTierPayout($amount, $count, $tiers)
    {
        $payout = 0;
        
        foreach ($tiers as $tier) {
            if (isset($tier['pairs']) && isset($tier['multiplier'])) {
                if ($count >= $tier['pairs']) {
                    $payout = $amount * $tier['multiplier'];
                }
            }
        }
        
        return $payout;
    }

    /**
     * ใช้ caps ต่างๆ
     */
    private function applyCaps($promotion, $amount, $userId)
    {
        // Cap ต่อบิล
        if ($promotion->max_payout_per_bill && $amount > $promotion->max_payout_per_bill) {
            $amount = $promotion->max_payout_per_bill;
        }

        // Cap ต่อวัน (ต้องเช็คจากฐานข้อมูล)
        if ($promotion->max_payout_per_day) {
            $todayPayout = DB::table('promotion_payouts')
                ->where('promotion_id', $promotion->id)
                ->where('user_id', $userId)
                ->whereDate('created_at', today())
                ->sum('amount');
            
            $remaining = $promotion->max_payout_per_day - $todayPayout;
            if ($amount > $remaining) {
                $amount = max(0, $remaining);
            }
        }

        // Cap ต่อผู้ใช้ (ต้องเช็คจากฐานข้อมูล)
        if ($promotion->max_payout_per_user) {
            $totalPayout = DB::table('promotion_payouts')
                ->where('promotion_id', $promotion->id)
                ->where('user_id', $userId)
                ->sum('amount');
            
            $remaining = $promotion->max_payout_per_user - $totalPayout;
            if ($amount > $remaining) {
                $amount = max(0, $remaining);
            }
        }

        // Cap งบรวมทั้งระบบ (global_budget) - ใช้ค่าในคอลัมน์เป็นงบคงเหลือ (เรา decrement แล้วใน txn)
        if (!is_null($promotion->global_budget)) {
            $remainingBudget = (float)$promotion->global_budget;
            if ($remainingBudget <= 0) {
                $amount = 0;
            } elseif ($amount > $remainingBudget) {
                // ถ้าเหลืองบไม่พอ ให้ถือว่าไม่ผ่าน (ไม่จ่าย)
                $amount = 0;
            }
        }

        return $amount;
    }

    /**
     * สร้างข้อความอธิบายเหตุผลกรณีติดเพดาน/งบ ไม่สามารถจ่ายได้เต็มจำนวน
     */
    private function getCapFailureReasons($promotion, $userId, $desiredAmount)
    {
        $reasons = [];

        // ต่อบิล
        if ($promotion->max_payout_per_bill && $desiredAmount > $promotion->max_payout_per_bill) {
            $reasons[] = 'เกินเพดานต่อบิล (สูงสุด ' . rtrim(rtrim(number_format((float)$promotion->max_payout_per_bill, 2, '.', ''), '0'), '.') . ')';
        }

        // ต่อวัน
        if ($promotion->max_payout_per_day) {
            $todayPayout = DB::table('promotion_payouts')
                ->where('promotion_id', $promotion->id)
                ->where('user_id', $userId)
                ->whereDate('created_at', today())
                ->sum('amount');
            $remaining = (float)$promotion->max_payout_per_day - (float)$todayPayout;
            if ($remaining <= 0) {
                $reasons[] = 'ถึงเพดานต่อวันแล้ว (สูงสุด ' . rtrim(rtrim(number_format((float)$promotion->max_payout_per_day, 2, '.', ''), '0'), '.') . ')';
            } elseif ($desiredAmount > $remaining) {
                $reasons[] = 'โควตาต่อวันคงเหลือ ' . rtrim(rtrim(number_format($remaining, 2, '.', ''), '0'), '.') . ' ไม่พอสำหรับการจ่าย ' . rtrim(rtrim(number_format($desiredAmount, 2, '.', ''), '0'), '.');
            }
        }

        // ต่อผู้ใช้รวม
        if ($promotion->max_payout_per_user) {
            $totalPayout = DB::table('promotion_payouts')
                ->where('promotion_id', $promotion->id)
                ->where('user_id', $userId)
                ->sum('amount');
            $remaining = (float)$promotion->max_payout_per_user - (float)$totalPayout;
            if ($remaining <= 0) {
                $reasons[] = 'ถึงเพดานต่อผู้ใช้แล้ว (สูงสุด ' . rtrim(rtrim(number_format((float)$promotion->max_payout_per_user, 2, '.', ''), '0'), '.') . ')';
            } elseif ($desiredAmount > $remaining) {
                $reasons[] = 'โควตาต่อผู้ใช้คงเหลือ ' . rtrim(rtrim(number_format($remaining, 2, '.', ''), '0'), '.') . ' ไม่พอสำหรับการจ่าย ' . rtrim(rtrim(number_format($desiredAmount, 2, '.', ''), '0'), '.');
            }
        }

        // งบรวมทั้งระบบ (ใช้ค่าที่คงเหลือในคอลัมน์)
        if (!is_null($promotion->global_budget)) {
            $remainingBudget = (float)$promotion->global_budget;
            if ($remainingBudget <= 0) {
                $reasons[] = 'งบประมาณรวมหมดแล้ว';
            } elseif ($desiredAmount > $remainingBudget) {
                $reasons[] = 'งบประมาณรวมคงเหลือ ' . rtrim(rtrim(number_format($remainingBudget, 2, '.', ''), '0'), '.') . ' ไม่พอสำหรับการจ่าย ' . rtrim(rtrim(number_format($desiredAmount, 2, '.', ''), '0'), '.');
            }
        }

        return $reasons;
    }

    /**
     * บันทึกการจ่าย
     */
    private function recordPayout($promotion, $userId, $amount, $transactionId = null)
    {
        if (!$transactionId) {
            $transactionId = 'PAY_' . time() . '_' . rand(1000, 9999);
        }

        DB::table('promotion_payouts')->insert([
            'promotion_id' => $promotion->id,
            'user_id' => $userId,
            'amount' => $amount,
            'transaction_id' => $transactionId,
            'status' => 'completed',
            'created_at' => now(),
            'updated_at' => now()
        ]);

        return $transactionId;
    }
}


