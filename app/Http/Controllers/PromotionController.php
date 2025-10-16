<?php

namespace App\Http\Controllers;

use App\Http\Requests\PromotionRequest;
use App\Services\PromotionService;
use Illuminate\Http\JsonResponse;

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
}


