<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PromotionController;

Route::get('/', function () {
    return view('welcome');
});

// API-like endpoint (no Sanctum by default):
Route::post('api/promotion/evaluate', [PromotionController::class, 'evaluate']);
