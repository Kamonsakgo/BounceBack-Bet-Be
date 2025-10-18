<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\PromotionController;

// API routes (stateless, no CSRF)
Route::post('/promotion/evaluate', [PromotionController::class, 'evaluate']);
Route::post('/promotion/payout', [PromotionController::class, 'payout']);

// Promotion API routes
Route::get('/promotions', [PromotionController::class, 'index']);
Route::post('/promotions', [PromotionController::class, 'store']);
Route::get('/promotions/{id}', [PromotionController::class, 'show']);
Route::put('/promotions/{id}', [PromotionController::class, 'update']);
Route::delete('/promotions/{id}', [PromotionController::class, 'destroy']);


