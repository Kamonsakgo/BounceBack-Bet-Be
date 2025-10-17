<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromotionRequest;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PromotionController extends Controller
{
    public function __construct(private PromotionService $service)
    {
    }

    public function evaluate(PromotionRequest $request): JsonResponse
    {
        $payload = $request->validated();
        $result = $this->service->evaluate($payload);

        // หากผ่านเงื่อนไข ให้จ่ายและบันทึกทันทีโดยอัตโนมัติ
        if ($result['eligible'] ?? false) {
            $promotionId = $result['promotion']['id'] ?? null;
            $userId = $payload['user_id'] ?? null;
            $amount = $result['cappedRefund'] ?? 0;

            $payoutPayload = [
                'promotion_id' => $promotionId,
                'user_id' => $userId,
                'amount' => $amount,
                'bill_id' => $payload['bill_id'] ?? null,
                'transaction_id' => $payload['transaction_id'] ?? null,
            ];

            $payoutResult = $this->service->payout($payoutPayload);

            return response()->json([
                'evaluation' => $result,
                'payout' => $payoutResult,
            ]);
        }

        return response()->json($result);
    }

    public function payout(Request $request): JsonResponse
    {
        $result = $this->service->payout($request->all());
        return response()->json($result);
    }

}


