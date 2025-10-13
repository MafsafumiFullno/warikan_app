<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProjectController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::get('/example', function () {
    return ['message' => 'API通信成功'];
});

// CSRFトークン取得用エンドポイント
Route::get('/csrf-token', function () {
    return response()->json(['csrf_token' => csrf_token()]);
});

// 認証関連のルート
Route::prefix('auth')->group(function () {
    Route::post('/guest-login', [AuthController::class, 'guestLogin']);
    Route::post('/register', [AuthController::class, 'register']);
    Route::post('/login', [AuthController::class, 'login']);
    
    // 認証が必要なルート
    Route::middleware('auth:sanctum')->group(function () {
        Route::post('/logout', [AuthController::class, 'logout']);
        Route::get('/me', [AuthController::class, 'me']);
        Route::put('/profile', [AuthController::class, 'updateProfile']);
        Route::post('/upgrade-to-member', [AuthController::class, 'upgradeToMember']);
    });
});

// プロジェクトCRUD（要認証）
Route::middleware(['auth:sanctum', 'auth.customer'])->prefix('projects')->group(function () {
    Route::get('/', [ProjectController::class, 'index']);
    Route::post('/', [ProjectController::class, 'store']);
    Route::get('/{projectId}', [ProjectController::class, 'show']);
    Route::put('/{projectId}', [ProjectController::class, 'update']);
    Route::delete('/{projectId}', [ProjectController::class, 'destroy']);
    Route::post('/{projectId}/settlement', [ProjectController::class, 'settlement']);
});