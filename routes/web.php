<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PromotionController;

Route::get('/', function () {
    return view('welcome');
});

// ย้าย API ไปที่ routes/api.php แล้ว
