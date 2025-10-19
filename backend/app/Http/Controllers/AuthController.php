<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Services\AuthService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Validation\ValidationException;
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
     * ゲストユーザーとしてログイン
     */
    public function guestLogin(Request $request): JsonResponse
    {
        try {
            $result = $this->authService->guestLogin($request->all());

            return response()->json([
                'message' => 'ゲストログイン成功',
                ...$result
            ], 200);

        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'バリデーションエラー',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            $this->logger->error('ゲストログインエラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => 'ゲストログインに失敗しました。',
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], 500);
        }
    }

    /**
     * 会員登録（メール/パスワード）
     */
    public function register(Request $request): JsonResponse
    {
        try {
            $result = $this->authService->register($request->all());

            return response()->json([
                'message' => '登録成功',
                ...$result
            ], 201);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'バリデーションエラー',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            $this->logger->error('会員登録エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => '会員登録に失敗しました',
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], 500);
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

            return response()->json([
                'message' => '会員登録完了',
                ...$result
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'バリデーションエラー',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            $this->logger->error('会員アップグレードエラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'customer_id' => $request->user()?->customer_id
            ]);

            return response()->json([
                'message' => '会員アップグレードに失敗しました',
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], 500);
        }
    }

    /**
     * ログイン（メール/パスワード）
     */
    public function login(Request $request): JsonResponse
    {
        try {
            $result = $this->authService->login($request->all());

            return response()->json([
                'message' => 'ログイン成功',
                ...$result
            ], 200);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'バリデーションエラー',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            $this->logger->error('ログインエラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return response()->json([
                'message' => 'ログインに失敗しました',
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], 500);
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
        } catch (\Exception $e) {
            $this->logger->error('ログアウトエラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'customer_id' => $request->user()?->customer_id
            ]);

            return response()->json([
                'message' => 'ログアウトに失敗しました',
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], 500);
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

            return response()->json($result, 200);
        } catch (\Exception $e) {
            $this->logger->error('ユーザー情報取得エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'customer_id' => $request->user()?->customer_id
            ]);

            return response()->json([
                'message' => 'ユーザー情報の取得に失敗しました',
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], 500);
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

            return response()->json([
                'message' => 'プロフィール更新成功',
                ...$result
            ], 200);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        } catch (ValidationException $e) {
            return response()->json([
                'message' => 'バリデーションエラー',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            $this->logger->error('プロフィール更新エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all(),
                'customer_id' => $request->user()?->customer_id
            ]);

            return response()->json([
                'message' => 'プロフィール更新に失敗しました',
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], 500);
        }
    }
}

