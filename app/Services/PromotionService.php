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

        // อ่านค่าตั้งค่าทั้งหมดจากฐานข้อมูล (ตาราง: PromotionSetting) ครั้งเดียว พร้อมค่าเริ่มต้นสำรอง
        // หากยังไม่ได้สร้างตาราง จะใช้ค่าเริ่มต้นและไม่คิวรีฐานข้อมูล
        $settings = [];
        if (Schema::hasTable('PromotionSetting')) {
            $settings = DB::table('PromotionSetting')->pluck('value', 'key')->toArray();
        }

        $minStakeVal = $settings['min_stake'] ?? null;
        $minStake = is_numeric($minStakeVal) ? (float)$minStakeVal : 100.0;

        $minOddsVal = $settings['min_odds'] ?? null;
        $minOdds = is_numeric($minOddsVal) ? (float)$minOddsVal : 1.85;

        $allowedMarketsCsv = $settings['allowed_markets'] ?? null;
        $allowedMarkets = is_string($allowedMarketsCsv) && trim($allowedMarketsCsv) !== ''
            ? array_values(array_filter(array_map(fn($m) => strtolower(trim($m)), explode(',', $allowedMarketsCsv))))
            : ['handicap', 'over_under'];

        $requiredPeriodVal = $settings['required_period'] ?? null;
        $requiredPeriod = is_string($requiredPeriodVal) && $requiredPeriodVal !== '' ? strtolower($requiredPeriodVal) : 'full_time';

        // อ่านจำนวนคู่ขั้นต่ำจากฐานข้อมูล พร้อม fallback
        $value = $settings['min_selections'] ?? null;
        $minSelections = is_numeric($value) ? (int)$value : 5;

        // กีฬาอนุญาต: อ่านจาก allowed_sports (csv: football,mpy) ถ้าไม่มีให้ default เป็น football เท่านั้น
        $allowedSportsCsv = $settings['allowed_sports'] ?? 'football,mpy';
        $allowedSports = is_string($allowedSportsCsv) && trim($allowedSportsCsv) !== ''
            ? array_values(array_filter(array_map(fn($s) => strtolower(trim($s)), explode(',', $allowedSportsCsv))))
            : ['football'];

        // ตัวคูณเครดิตคืนกรณี "ผิดหมดทุกคู่"
        // อ่านจากคีย์ใน DB: multiplier_5 ... multiplier_10 (ตาราง `PromotionSetting`) พร้อมค่าเริ่มต้นสำรอง
        $defaultMultipliers = [
            5 => 2.0,
            6 => 5.0,
            7 => 7.0,
            8 => 10.0,
            9 => 15.0,
            10 => 30.0,
        ];
        $multipliersByCount = [];
        foreach ($defaultMultipliers as $countKey => $defaultValue) {
            $dbVal = $settings['multiplier_' . $countKey] ?? null;
            $multipliersByCount[$countKey] = is_numeric($dbVal) ? (float)$dbVal : $defaultValue;
        }

        // ตรวจเงื่อนไข: ยอดเดิมพันขั้นต่ำ
        if ($stake < $minStake) {
            $minStr = rtrim(rtrim(number_format($minStake, 2, '.', ''), '0'), '.');
            $reasons[] = 'ยอดแทงขั้นต่ำต้องไม่น้อยกว่า ' . $minStr . ' | Stake must be at least ' . $minStr;
        }

        // ตรวจเงื่อนไข: กีฬา (ทั้งระดับบิลและรายคู่ต้องอยู่ใน allowed_sports)
        $billSport = strtolower($sport);
        $sportsEligible = in_array($billSport, $allowedSports, true) || $billSport === '';

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
            if (!in_array($selSport, $allowedSports, true)) {
                $sportsEligible = false;
            }

            if ($result === 'void' || $result === 'cancelled' || $result === 'canceled' || $status === 'cancel') {
                $hasVoidOrCancelled = true;
            }
            if ($result !== 'lose') {
                $allLose = false;
            }
            if (!in_array($market, $allowedMarkets, true)) {
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
            $reasons[] = 'กีฬาไม่เข้าเงื่อนไข (อนุญาต: ' . implode(',', $allowedSports) . ') | Only allowed sports: ' . implode(',', $allowedSports);
        }
        if ($hasVoidOrCancelled) {
            $reasons[] = 'มีคู่ที่โมฆะ/ยกเลิก ทำให้บิลไม่เข้าเงื่อนไข | Any void/cancelled selection disqualifies the bill';
        }
        if (!$allLose) {
            $reasons[] = 'ทุกรายการต้องแพ้ (lose) เท่านั้น | All selections must be a full loss';
        }
        if (!$allMarketsEligible) {
            $reasons[] = 'นับเฉพาะตลาดแฮนดิแคปหรือสูง/ต่ำเท่านั้น | Only handicap or over/under markets are eligible';
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

        // จำกัดวงเงินจ่ายคืนต่อวัน (อ่านจาก 'max_payout_per_day' หรือใช้ค่าเริ่มต้น 50,000)
        $maxPayoutVal = $settings['max_payout_per_day'] ?? null;
        $maxPayoutPerDay = is_numeric($maxPayoutVal) ? (float)$maxPayoutVal : 50000.0;
        $cappedRefund = min($refund, $maxPayoutPerDay);

        return [
            'eligible' => $eligible,
            'reasons' => $reasons,
            'selectionsCount' => $count,
            'multiplier' => $multiplier,
            'stake' => $stake,
            'computedRefund' => $refund,
            'cappedRefund' => $cappedRefund,
        ];
    }
}


