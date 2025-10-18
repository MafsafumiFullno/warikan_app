<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTaskController;
use App\Http\Controllers\ProjectMemberController;
use App\Http\Controllers\SplitCalculationController;

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
    
    // 会計管理（ProjectTaskを使用）
    Route::get('/{projectId}/accountings', [ProjectTaskController::class, 'index']);
    Route::post('/{projectId}/accountings', [ProjectTaskController::class, 'store']);
    Route::put('/{projectId}/accountings/{taskId}', [ProjectTaskController::class, 'update']);
    Route::delete('/{projectId}/accountings/{taskId}', [ProjectTaskController::class, 'destroy']);
    
    // メンバー管理
    Route::get('/{projectId}/members', [ProjectMemberController::class, 'index']);
    Route::post('/{projectId}/members', [ProjectMemberController::class, 'store']);
    Route::put('/{projectId}/members/{memberId}/split-weight', [ProjectMemberController::class, 'updateWeight']);
    Route::put('/{projectId}/members/{memberId}/memo', [ProjectMemberController::class, 'updateMemo']);
    Route::delete('/{projectId}/members/{memberId}', [ProjectMemberController::class, 'destroy']);
    
    // 割り勘計算
    Route::post('/{projectId}/split-calculation', [SplitCalculationController::class, 'calculate']);
});