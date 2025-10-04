<?php

namespace App\Http\Controllers;

use App\Models\Customer;
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
        try {
            Log::info('ゲストログイン開始', ['request_data' => $request->all()]);

            $validator = Validator::make($request->all(), [
                'nick_name' => 'nullable|string|max:255',
            ]);

            if ($validator->fails()) {
                Log::error('ゲストログインバリデーションエラー', ['errors' => $validator->errors()]);
                return response()->json([
                    'message' => 'バリデーションエラー',
                    'errors' => $validator->errors()
                ], 422);
            }

            // 自動的にユニークなニックネームを生成
            $nickName = $request->nick_name ?: $this->generateGuestNickname();

            Log::info('ゲストユーザー作成開始', ['nick_name' => $nickName]);

            $customer = Customer::create([
                'is_guest' => true,
                'nick_name' => $nickName,
            ]);

            Log::info('ゲストユーザー作成成功', ['customer_id' => $customer->customer_id]);

            $token = $customer->createToken('guest-token')->plainTextToken;

            Log::info('ゲストログイン成功', ['customer_id' => $customer->customer_id]);

            return response()->json([
                'message' => 'ゲストログイン成功',
                'customer' => $customer,
                'token' => $token,
                'token_type' => 'Bearer'
            ], 200);

        } catch (\Exception $e) {
            Log::error('ゲストログインエラー', [
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
        $validator = Validator::make($request->all(), [
            'email' => 'required|email|unique:customers,email',
            'password' => 'required|string|min:8',
            'first_name' => 'nullable|string|max:255',
            'last_name' => 'nullable|string|max:255',
            'nick_name' => 'nullable|string|max:255',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'バリデーションエラー',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customer = Customer::create([
            'is_guest' => false,
            'email' => $request->email,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'nick_name' => $request->nick_name,
            'password' => Hash::make($request->password),
        ]);

        $token = $customer->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => '登録成功',
            'customer' => $customer,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }

    /**
     * ログイン（メール/パスワード）
     */
    public function login(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'message' => 'バリデーションエラー',
                'errors' => $validator->errors(),
            ], 422);
        }

        $customer = Customer::where('email', $request->email)->first();
        if (!$customer || !$customer->password || !Hash::check($request->password, $customer->password)) {
            throw ValidationException::withMessages([
                'email' => ['メールアドレスまたはパスワードが正しくありません。'],
            ]);
        }

        $token = $customer->createToken('auth-token')->plainTextToken;

        return response()->json([
            'message' => 'ログイン成功',
            'customer' => $customer,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 200);
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
            'customer' => $customer
        ], 200);
    }

    /**
     * ゲストユーザーのニックネームを自動生成
     */
    private function generateGuestNickname(): string
    {
        $adjectives = ['楽しい', '元気な', '素敵な', '素晴らしい', '素朴な', '勇敢な', '賢い', '優しい'];
        $animals = ['ねこ', 'いぬ', 'うさぎ', 'パンダ', 'ライオン', 'ぞう', 'きつね', 'たぬき'];
        
        $adjective = $adjectives[array_rand($adjectives)];
        $animal = $animals[array_rand($animals)];
        $number = rand(1, 999);
        
        return "{$adjective}{$animal}{$number}";
    }
}

