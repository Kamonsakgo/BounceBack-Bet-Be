<?php

namespace App\Services;

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

        // Configurable parameters (can be externalized to config if needed)
        $minStake = 100.0;
        $minOdds = 1.85;
        $allowedMarkets = ['handicap', 'over_under'];
        $requiredPeriod = 'full_time';
        $minSelections = 5;
        $footballOnly = true; // Set to false to allow other sports like muaythai

        // Multipliers for "wrong all" refunds (credit back multipliers)
        $multipliersByCount = [
            5 => 2.0,
            6 => 5.0,
            7 => 7.0,
            8 => 10.0,
            9 => 15.0,
            10 => 30.0,
        ];

        // Guard: stake
        if ($stake < $minStake) {
            $reasons[] = 'Stake must be at least 100';
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
            $reasons[] = 'Each selection odds must be >= 1.85';
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

        // Cap payout per day (business rule mentions 50,000). Actual per-user/day tracking
        // requires persistence which is out of scope here. We cap the computed amount.
        $maxPayoutPerDay = 50000.0;
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


