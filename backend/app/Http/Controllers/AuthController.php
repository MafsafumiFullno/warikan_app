<?php

namespace App\Http\Controllers;

use App\Models\Customer;
use App\Models\OAuthAccount;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    /**
     * ゲストユーザーとしてログイン
     */
    public function guestLogin(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'nick_name' => 'required|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'バリデーションエラー',
                'errors' => $validator->errors()
            ], 422);
        }

        $customer = Customer::create([
            'is_guest' => true,
            'nick_name' => $request->nick_name,
        ]);

        $token = $customer->createToken('guest-token')->plainTextToken;

        return response()->json([
            'message' => 'ゲストログイン成功',
            'customer' => $customer,
            'token' => $token,
            'token_type' => 'Bearer'
        ], 200);
    }

    /**
     * OAuthログイン
     */
    public function oauthLogin(Request $request): JsonResponse
    {
        try {
            Log::info('OAuthログイン開始', $request->all());
            
            $validator = Validator::make($request->all(), [
                'provider_name' => 'required|string',
                'provider_user_id' => 'required|string',
                'access_token' => 'required|string',
                'refresh_token' => 'nullable|string',
                'token_expired_date' => 'nullable|date',
                'email' => 'nullable|email',
                'first_name' => 'nullable|string',
                'last_name' => 'nullable|string',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'バリデーションエラー',
                    'errors' => $validator->errors()
                ], 422);
            }

        // 既存のOAuthアカウントを確認
        $oauthAccount = OAuthAccount::where('provider_name', $request->provider_name)
            ->where('provider_user_id', $request->provider_user_id)
            ->first();

        if ($oauthAccount) {
            // 既存ユーザーとしてログイン
            $customer = $oauthAccount->customer;
            
            // トークンを更新
            $oauthAccount->update([
                'access_token' => $request->access_token,
                'refresh_token' => $request->refresh_token,
                'token_expired_date' => $request->token_expired_date,
            ]);
        } else {
            // 新規ユーザーを作成
            $customer = Customer::create([
                'is_guest' => false,
                'email' => $request->email,
                'first_name' => $request->first_name,
                'last_name' => $request->last_name,
            ]);

            // OAuthアカウントを作成
            OAuthAccount::create([
                'customer_id' => $customer->customer_id,
                'provider_name' => $request->provider_name,
                'provider_user_id' => $request->provider_user_id,
                'access_token' => $request->access_token,
                'refresh_token' => $request->refresh_token,
                'token_expired_date' => $request->token_expired_date,
            ]);
        }

        $token = $customer->createToken('oauth-token')->plainTextToken;

        Log::info('OAuthログイン成功', ['customer_id' => $customer->customer_id]);

        return response()->json([
            'message' => 'OAuthログイン成功',
            'customer' => $customer,
            'token' => $token,
            'token_type' => 'Bearer'
        ], 200);
        } catch (\Exception $e) {
            Log::error('OAuthログインエラー', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return response()->json([
                'message' => 'OAuthログインに失敗しました',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * ログアウト
     */
    public function logout(Request $request): JsonResponse
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'ログアウト成功'
        ], 200);
    }

    /**
     * ユーザー情報取得
     */
    public function me(Request $request): JsonResponse
    {
        $customer = $request->user();
        
        return response()->json([
            'customer' => $customer->load('oauthAccounts')
        ], 200);
    }
}

