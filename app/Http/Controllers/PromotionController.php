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
        $result = $this->service->evaluate($request->validated());
        return response()->json($result);
    }

    public function payout(Request $request): JsonResponse
    {
        $result = $this->service->payout($request->all());
        return response()->json($result);
    }

}


