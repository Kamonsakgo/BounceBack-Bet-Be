<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\PromotionController;

// API routes (stateless, no CSRF)
Route::post('/promotion/evaluate', [PromotionController::class, 'evaluate']);
Route::post('/promotion/payout', [PromotionController::class, 'payout']);

// Promotion API routes
Route::get('/promotions', function () {
    $promotions = DB::table('promotions')->orderBy('priority', 'asc')->orderBy('id', 'asc')->get();
    return response()->json($promotions);
});

Route::post('/promotions', function (Request $request) {
    $data = $request->all();
    
    // Validate required fields
    if (empty($data['name'])) {
        return response()->json(['error' => 'Name is required'], 400);
    }
    
    // Handle settings if it's an array
    if (isset($data['settings']) && is_array($data['settings'])) {
        $data['settings'] = json_encode($data['settings']);
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
});

Route::get('/promotions/{id}', function ($id) {
    $promotion = DB::table('promotions')->where('id', $id)->first();
    if (!$promotion) {
        return response()->json(['error' => 'Promotion not found'], 404);
    }
    return response()->json($promotion);
});

Route::put('/promotions/{id}', function (Request $request, $id) {
    $promotion = DB::table('promotions')->where('id', $id)->first();
    if (!$promotion) {
        return response()->json(['error' => 'Promotion not found'], 404);
    }
    
    $data = $request->all();
    
    // Handle settings if it's an array
    if (isset($data['settings']) && is_array($data['settings'])) {
        $data['settings'] = json_encode($data['settings']);
    }
    
    $data['updated_at'] = now();
    
    try {
        DB::table('promotions')->where('id', $id)->update($data);
        $updatedPromotion = DB::table('promotions')->where('id', $id)->first();
        return response()->json($updatedPromotion);
    } catch (\Exception $e) {
        return response()->json(['error' => 'Failed to update promotion: ' . $e->getMessage()], 500);
    }
});

Route::delete('/promotions/{id}', function ($id) {
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
});


