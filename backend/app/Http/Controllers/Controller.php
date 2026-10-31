<?php

namespace App\Http\Controllers;

use App\Http\Responses\ApiErrorResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;
use Throwable;

abstract class Controller
{
    protected function handleException(Throwable $exception, Request $request, LoggerInterface $logger, string $context = ''): JsonResponse
    {
        $response = ApiErrorResponse::fromException($exception);

        if ($response->getStatusCode() >= 500) {
            $logger->error($context . ($context ? 'エラー: ' : '') . $exception->getMessage(), [
                'exception' => $exception,
                'customer_id' => $request->user()?->customer_id,
            ]);
        }

        return $response;
    }
}
