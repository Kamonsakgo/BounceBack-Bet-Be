<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

class PromotionService
{
    /**
     * Evaluate a step bet for the refund promotion.
     *
     * Expected payload shape:
     * - stake: float (>= 100)
     * - sport: string ('football' | 'muaythai')
     * - selections: array<array{
     *     result: 'lose'|'win'|'void',
     *     market: 'handicap'|'over_under',
     *     period: 'full_time',
     *     odds: float
     * }>
     */
    public function evaluate(array $payload): array
    {
        $stake = (float)($payload['stake'] ?? 0);
        $sport = (string)($payload['sport'] ?? 'football');
        $selections = $payload['selections'] ?? [];

        $reasons = [];

        // Read configurable parameters from DB (table: prsetting) with fallbacks
        $minStakeVal = DB::table('PromotionSetting')->where('key', 'min_stake')->value('value');
        $minStake = is_numeric($minStakeVal) ? (float)$minStakeVal : 100.0;

        $minOddsVal = DB::table('PromotionSetting')->where('key', 'min_odds')->value('value');
        $minOdds = is_numeric($minOddsVal) ? (float)$minOddsVal : 1.85;

        $allowedMarketsCsv = DB::table('PromotionSetting')->where('key', 'allowed_markets')->value('value');
        $allowedMarkets = is_string($allowedMarketsCsv) && trim($allowedMarketsCsv) !== ''
            ? array_values(array_filter(array_map(fn($m) => strtolower(trim($m)), explode(',', $allowedMarketsCsv))))
            : ['handicap', 'over_under'];

        $requiredPeriodVal = DB::table('PromotionSetting')->where('key', 'required_period')->value('value');
        $requiredPeriod = is_string($requiredPeriodVal) && $requiredPeriodVal !== '' ? strtolower($requiredPeriodVal) : 'full_time';

        // Read minimum selections from database with fallback
        $value = DB::table('PromotionSetting')->where('key', 'min_selections')->value('value');
        $minSelections = is_numeric($value) ? (int)$value : 5;

        $footballOnlyVal = DB::table('PromotionSetting')->where('key', 'football_only')->value('value');
        $footballOnly = in_array(strtolower((string)$footballOnlyVal), ['1', 'true', 'yes'], true) ? true : (strtolower((string)$footballOnlyVal) === '0' || strtolower((string)$footballOnlyVal) === 'false' ? false : true);

        // Multipliers for "wrong all" refunds (credit back multipliers)
        // Read from DB keys: multiplier_5 ... multiplier_10 in table `prsetting`, fallback to defaults
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
            $dbVal = DB::table('PromotionSetting')->where('key', 'multiplier_' . $countKey)->value('value');
            $multipliersByCount[$countKey] = is_numeric($dbVal) ? (float)$dbVal : $defaultValue;
        }

        // Guard: stake
        if ($stake < $minStake) {
            $reasons[] = 'Stake must be at least ' . rtrim(rtrim(number_format($minStake, 2, '.', ''), '0'), '.');
        }

        // Guard: sport restriction (per promo wording: football bills only)
        if ($footballOnly && strtolower($sport) !== 'football') {
            $reasons[] = 'Only football bills are eligible';
        }

        // Guard: selections count and properties
        if (!is_array($selections) || count($selections) < $minSelections) {
            $reasons[] = 'Minimum selections not met (>= 5)';
        }

        $hasVoidOrCancelled = false;
        $allLose = true;
        $allMarketsEligible = true;
        $allPeriodsEligible = true;
        $allOddsEligible = true;

        foreach ($selections as $sel) {
            $result = strtolower((string)($sel['result'] ?? ''));
            $market = strtolower((string)($sel['market'] ?? ''));
            $period = strtolower((string)($sel['period'] ?? ''));
            $odds = (float)($sel['odds'] ?? 0);

            if ($result === 'void' || $result === 'cancelled' || $result === 'canceled') {
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

        if ($hasVoidOrCancelled) {
            $reasons[] = 'Any void/cancelled selection disqualifies the bill';
        }
        if (!$allLose) {
            $reasons[] = 'All selections must be a full loss';
        }
        if (!$allMarketsEligible) {
            $reasons[] = 'Only handicap or over/under full-time markets are eligible';
        }
        if (!$allPeriodsEligible) {
            $reasons[] = 'Only full-time markets are eligible';
        }
        if (!$allOddsEligible) {
            $reasons[] = 'Each selection odds must be >= ' . rtrim(rtrim(number_format($minOdds, 2, '.', ''), '0'), '.');
        }

        $count = is_array($selections) ? count($selections) : 0;
        $multiplier = $multipliersByCount[$count] ?? 0.0;
        if ($multiplier <= 0.0) {
            $reasons[] = 'Selections count not eligible for promotion';
        }

        $eligible = count($reasons) === 0;

        $refund = 0.0;
        if ($eligible) {
            // Promotion text implies "receive credit back X times".
            // We interpret as stake multiplied by the table value.
            $refund = $stake * $multiplier;
        }

        // Cap payout per day (business rule mentions 50,000) or read from DB 'max_payout_per_day'
        $maxPayoutVal = DB::table('PromotionSetting')->where('key', 'max_payout_per_day')->value('value');
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


