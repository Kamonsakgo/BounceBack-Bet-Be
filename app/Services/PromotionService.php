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
        $sport = (string)($payload['sport'] ?? 'football');
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

        // ตรวจสอบตารางเวลาการเปิดโปรโมชัน (promotion_schedules)
        if ($activePromotion && Schema::hasTable('promotion_schedules')) {
            $now = now();
            $dow = (int)$now->dayOfWeek; // 0..6
            $currentTime = $now->format('H:i:s');
            $hasSchedules = DB::table('promotion_schedules')
                ->where('promotion_id', $activePromotion->id)
                ->exists();
            if ($hasSchedules) {
                $isOpenNow = DB::table('promotion_schedules')
                    ->where('promotion_id', $activePromotion->id)
                    ->where('day_of_week', $dow)
                    ->where('start_time', '<=', $currentTime)
                    ->where('end_time', '>=', $currentTime)
                    ->exists();
                if (!$isOpenNow) {
                    $reasons[] = 'เวลาไม่อยู่ในช่วงเปิดโปรโมชันวันนี้ | Promotion is closed at this time';
                }
            }
        }

        // 2) ไม่ fallback ไปที่ promotion_settings อีกต่อไป (เลิกใช้ตารางนั้น)

        $minStakeVal = $settings['min_stake'] ?? null;
        $minStake = is_numeric($minStakeVal) ? (float)$minStakeVal : 100.0;

        $minOddsVal = $settings['min_odds'] ?? null;
        $minOdds = is_numeric($minOddsVal) ? (float)$minOddsVal : 1.85;

        // allowed_markets อาจมาเป็น array (จาก promotions.settings) หรือ string csv (จาก key-value)
        $allowedMarketsRaw = $settings['allowed_markets'] ?? null;
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
        $minSelections = is_numeric($value) ? (int)$value : 5;

        // กีฬาอนุญาต: อาจมาเป็น array หรือ csv string
        $allowedSportsRaw = $settings['allowed_sports'] ?? 'football,mpy';
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
        $defaultMultipliers = [
            5 => 2.0,
            6 => 5.0,
            7 => 7.0,
            8 => 10.0,
            9 => 15.0,
            10 => 30.0,
        ];
        $multipliersByCount = [];
        if (isset($settings['multipliers']) && is_array($settings['multipliers'])) {
            foreach ($defaultMultipliers as $countKey => $defaultValue) {
                $val = $settings['multipliers'][$countKey] ?? null;
                $multipliersByCount[$countKey] = is_numeric($val) ? (float)$val : $defaultValue;
            }
        } else {
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

        // ตรวจเงื่อนไข: กีฬา (ทั้งระดับบิลและรายคู่ต้องอยู่ใน allowed_sports)
        $billSport = strtolower($sport);
        $sportsEligible = $allowAllSports || in_array($billSport, $allowedSports, true) || $billSport === '';

        // ตรวจเงื่อนไข: จำนวนคู่ขั้นต่ำ และคุณสมบัติของคู่ที่แทง
        if (!is_array($selections) || count($selections) < $minSelections) {
            $reasons[] = 'จำนวนคู่ไม่ถึงขั้นต่ำ (ต้อง >= ' . $minSelections . ')';
        }

        $hasVoidOrCancelled = false;
        $allLose = true;
        $allMarketsEligible = true;
        $allPeriodsEligible = true;
        $allOddsEligible = true;

        foreach ($selections as $sel) {
            $result = strtolower((string)($sel['result'] ?? ''));
            // รองรับทั้ง market และ market_type
            $marketRaw = (string)($sel['market'] ?? ($sel['market_type'] ?? ''));
            $market = strtolower($marketRaw);
            // period ถ้าไม่ส่งมา ให้ถือเป็น full_time
            $period = strtolower((string)($sel['period'] ?? 'full_time'));
            $odds = (float)($sel['odds'] ?? 0);
            // ถ้าส่ง status=cancel ให้ถือว่าโมฆะ/ยกเลิก
            $status = strtolower((string)($sel['status'] ?? 'accept'));
            // กีฬาในระดับคู่ ถ้าไม่ได้ส่งมาก็ใช้ของบิล
            $selSport = strtolower((string)($sel['sport'] ?? $billSport));
            if (!$allowAllSports && !in_array($selSport, $allowedSports, true)) {
                $sportsEligible = false;
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
        }

        if (!$sportsEligible) {
            $list = array_values(array_filter($allowedSports, fn($s) => $s !== 'all'));
            $listStr = empty($list) ? 'ทุกกีฬา' : implode(',', $list);
            $reasons[] = 'กีฬาไม่เข้าเงื่อนไข (อนุญาต: ' . $listStr . ') | Only allowed sports: ' . $listStr;
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

        $count = is_array($selections) ? count($selections) : 0;
        $multiplier = $multipliersByCount[$count] ?? 0.0;
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
}


