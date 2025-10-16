<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PromotionController;

// API routes (stateless, no CSRF)
Route::post('/promotion/evaluate', [PromotionController::class, 'evaluate']);


