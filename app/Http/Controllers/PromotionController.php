<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromotionRequest;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

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

    /**
     * Get all promotions
     */
    public function index(): JsonResponse
    {
        $promotions = DB::table('promotions')
            ->orderBy('priority', 'asc')
            ->orderBy('id', 'asc')
            ->get();
        
        return response()->json($promotions);
    }

    /**
     * Create a new promotion
     */
    public function store(Request $request): JsonResponse
    {
        $data = $request->all();
        
        // Validate required fields
        if (empty($data['name'])) {
            return response()->json(['error' => 'Name is required'], 400);
        }
        
        // Handle settings if it's an array
        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings']);
        }
        
        // Handle match_periods if it's an array
        if (isset($data['match_periods']) && is_array($data['match_periods'])) {
            $data['match_periods'] = json_encode($data['match_periods']);
        }
        
        // Set default values
        $data['is_active'] = $data['is_active'] ?? true;
        $data['is_stackable'] = $data['is_stackable'] ?? false;
        $data['priority'] = $data['priority'] ?? 100;
        $data['type'] = $data['type'] ?? 'lose_all_refund';
        $data['created_at'] = now();
        $data['updated_at'] = now();
        
        try {
            $id = DB::table('promotions')->insertGetId($data);
            $promotion = DB::table('promotions')->where('id', $id)->first();
            return response()->json($promotion, 201);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to create promotion: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Get a specific promotion
     */
    public function show($id): JsonResponse
    {
        $promotion = DB::table('promotions')->where('id', $id)->first();
        if (!$promotion) {
            return response()->json(['error' => 'Promotion not found'], 404);
        }
        return response()->json($promotion);
    }

    /**
     * Update a promotion
     */
    public function update(Request $request, $id): JsonResponse
    {
        $promotion = DB::table('promotions')->where('id', $id)->first();
        if (!$promotion) {
            return response()->json(['error' => 'Promotion not found'], 404);
        }
        
        $data = $request->all();
        
        // Handle settings if it's an array
        if (isset($data['settings']) && is_array($data['settings'])) {
            $data['settings'] = json_encode($data['settings']);
        }
        
        // Handle match_periods if it's an array
        if (isset($data['match_periods']) && is_array($data['match_periods'])) {
            $data['match_periods'] = json_encode($data['match_periods']);
        }
        
        $data['updated_at'] = now();
        
        try {
            DB::table('promotions')->where('id', $id)->update($data);
            $updatedPromotion = DB::table('promotions')->where('id', $id)->first();
            return response()->json($updatedPromotion);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to update promotion: ' . $e->getMessage()], 500);
        }
    }

    /**
     * Delete a promotion
     */
    public function destroy($id): JsonResponse
    {
        $promotion = DB::table('promotions')->where('id', $id)->first();
        if (!$promotion) {
            return response()->json(['error' => 'Promotion not found'], 404);
        }
        
        try {
            DB::table('promotions')->where('id', $id)->delete();
            return response()->json(['message' => 'Promotion deleted successfully']);
        } catch (\Exception $e) {
            return response()->json(['error' => 'Failed to delete promotion: ' . $e->getMessage()], 500);
        }
    }

}


