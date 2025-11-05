<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Psr\Log\LoggerInterface;

abstract class Controller
{
    /**
     * 共通のエラーハンドリング
     */
    protected function handleException(\Exception $e, Request $request, LoggerInterface $logger, string $context = ''): JsonResponse
    {
        $logger->error($context . ($context ? 'エラー: ' : '') . $e->getMessage(), [
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'request_data' => $request->all(),
            'customer_id' => $request->user()?->customer_id
        ]);

        // バリデーションエラー
        if ($e instanceof ValidationException) {
            return response()->json([
                'message' => 'バリデーションエラー',
                'errors' => $e->errors()
            ], 422);
        }

        // カスタムエラーメッセージに基づくHTTPステータスコードの決定
        $message = $e->getMessage();
        $statusCode = 500;

        if (str_contains($message, '見つかりません')) {
            $statusCode = 404;
        } elseif (str_contains($message, 'アクセス権限がありません') || str_contains($message, 'オーナー権限がありません')) {
            $statusCode = 403;
        } elseif (str_contains($message, '無効な') || str_contains($message, 'バリデーション')) {
            $statusCode = 422;
        } elseif (str_contains($message, '既に追加されています')) {
            $statusCode = 409;
        }

        return response()->json([
            'message' => $message,
            'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
        ], $statusCode);
    }
}
