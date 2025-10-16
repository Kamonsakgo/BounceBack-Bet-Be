<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use App\Http\Controllers\PromotionController;

Route::get('/', function () {
    $promotions = DB::table('promotions')->orderBy('priority', 'asc')->orderBy('id', 'asc')->get();
    return view('home', compact('promotions'));
});

// FE page to test promotion evaluate/payout
Route::view('/promotions/test', 'promotions.test');
Route::get('/promotions/create', [PromotionController::class, 'create']);
Route::post('/promotions', [PromotionController::class, 'store'])->name('promotions.store');

// ย้าย API ไปที่ routes/api.php แล้ว
