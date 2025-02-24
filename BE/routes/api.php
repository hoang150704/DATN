<?php

use App\Http\Controllers\Api\Admin\OrderController;
use App\Http\Middleware\CheckOrderStatus;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

Route::prefix('admin')->group(function () {
    Route::prefix('orders')->group(function () {
        Route::get('/', [OrderController::class, 'index']);
        Route::get('/search', [OrderController::class, 'search']);
        Route::post('/create', [OrderController::class, 'store']);
        Route::patch('/changestatus', [OrderController::class, 'changeStatus'])->middleware('check.order.status');
        Route::get('/{order}', [OrderController::class, 'show']);
    });
});