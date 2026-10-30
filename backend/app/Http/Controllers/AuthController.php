<?php

namespace App\Http\Controllers;

use App\Services\Auth\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Psr\Log\LoggerInterface;

class AuthController extends Controller
{
    protected AuthService $authService;
    protected LoggerInterface $logger;

    public function __construct(
        AuthService $authService,
        LoggerInterface $logger
    ) {
        $this->authService = $authService;
        $this->logger = $logger;
    }

    /**
     * 認証レスポンス（customer + token）を返す
     */
    private function authResponse(array $data, int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'customer' => $data['customer'],
            'token' => $data['token']
        ], $statusCode);
    }

    /**
     * 顧客レスポンス（customerのみ）を返す
     */
    private function customerResponse(array $data, int $statusCode = 200): JsonResponse
    {
        return response()->json([
            'customer' => $data['customer']
        ], $statusCode);
    }

    /**
     * ゲストユーザーとしてログイン
     */
    public function guestLogin(Request $request): JsonResponse
    {
        try {
            $result = $this->authService->guestLogin($request->all());
            return $this->authResponse($result);

        } catch (\Throwable $exception) {
            return $this->handleException($exception, $request, $this->logger, '認証');
        }
    }

    /**
     * 会員登録（メール/パスワード）
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $result = $this->authService->register($request->all());
            return $this->authResponse($result, 201);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, $request, $this->logger, '認証');
        }
    }

    /**
     * ゲストユーザーを会員にアップグレード
     */
    public function upgradeToMember(Request $request): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->authService->upgradeToMember($customer, $request->all());
            return $this->customerResponse($result);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, $request, $this->logger, '認証');
        }
    }

    /**
     * ログイン（メール/パスワード）
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->all());
            return $this->authResponse($result);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, $request, $this->logger, '認証');
        }
    }

    /**
     * ログアウト
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            $customer = $request->user();
            $this->authService->logout($customer);

            return response()->json([
                'message' => 'ログアウト成功'
            ], 200);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, $request, $this->logger, '認証');
        }
    }

    /**
     * ユーザー情報取得
     */
    public function me(Request $request): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->authService->getUser($customer);
            return $this->customerResponse($result);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, $request, $this->logger, '認証');
        }
    }

    /**
     * ユーザー情報更新
     */
    public function updateProfile(Request $request): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->authService->updateProfile($customer, $request->all());
            return $this->customerResponse($result);
        } catch (\Throwable $exception) {
            return $this->handleException($exception, $request, $this->logger, '認証');
        }
    }
}
