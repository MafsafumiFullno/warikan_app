<?php

namespace App\Http\Responses;

use App\Exceptions\BadRequestException;
use App\Exceptions\ConflictException;
use App\Exceptions\ForbiddenException;
use App\Exceptions\NotFoundException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

final class ApiErrorResponse
{
    public static function fromException(Throwable $exception): JsonResponse
    {
        $status = match (true) {
            $exception instanceof ValidationException => 422,
            $exception instanceof AuthenticationException => 401,
            $exception instanceof ForbiddenException => 403,
            $exception instanceof AuthorizationException => $exception->status() ?? 403,
            $exception instanceof NotFoundException, $exception instanceof ModelNotFoundException => 404,
            $exception instanceof ConflictException => 409,
            $exception instanceof BadRequestException => 400,
            $exception instanceof HttpExceptionInterface => $exception->getStatusCode(),
            default => 500,
        };

        $code = match ($status) {
            400 => 'BAD_REQUEST',
            401 => 'UNAUTHENTICATED',
            403 => 'FORBIDDEN',
            404 => 'NOT_FOUND',
            405 => 'METHOD_NOT_ALLOWED',
            409 => 'CONFLICT',
            419 => 'CSRF_TOKEN_MISMATCH',
            422 => 'VALIDATION_ERROR',
            429 => 'TOO_MANY_REQUESTS',
            default => $status >= 500 ? 'INTERNAL_SERVER_ERROR' : 'HTTP_ERROR',
        };

        $message = match (true) {
            $exception instanceof ValidationException => 'バリデーションエラー',
            $exception instanceof NotFoundException,
            $exception instanceof ForbiddenException,
            $exception instanceof ConflictException,
            $exception instanceof BadRequestException => $exception->getMessage(),
            $status >= 500 => 'サーバーエラーが発生しました',
            $status === 401 => '認証が必要です',
            $status === 403 => 'アクセス権限がありません',
            $status === 404 => 'リソースが見つかりません',
            default => Response::$statusTexts[$status] ?? 'リクエストを処理できません',
        };

        return response()->json([
            'message' => $message,
            'error' => $code,
            'errors' => (object) ($exception instanceof ValidationException ? $exception->errors() : []),
        ], $status, $exception instanceof HttpExceptionInterface ? $exception->getHeaders() : []);
    }
}
