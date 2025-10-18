<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Models\Customer;
use App\Models\ProjectMember;
use App\Models\ProjectRole;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class ProjectMemberController extends Controller
{
    /**
     * プロジェクトのメンバー一覧を取得
     */
    public function index(Request $request, int $projectId): JsonResponse
    {
        try {
            $customer = $request->user();
            
            // プロジェクトの存在確認とアクセス権限チェック
            $project = Project::where('project_id', $projectId)
                             ->where('del_flg', false)
                             ->first();
            
            if (!$project) {
                return response()->json(['error' => 'プロジェクトが見つかりません'], 404);
            }

            // プロジェクトのオーナーまたはメンバーかチェック
            $isOwner = $project->customer_id === $customer->customer_id;
            $isMember = ProjectMember::where('project_id', $projectId)
                                    ->where('customer_id', $customer->customer_id)
                                    ->where('del_flg', false)
                                    ->exists();
            
            if (!$isOwner && !$isMember) {
                return response()->json(['error' => 'アクセス権限がありません'], 403);
            }

            // メンバー一覧を取得（プロジェクトオーナーも含む）
            $members = ProjectMember::where('project_id', $projectId)
                                  ->where('del_flg', false)
                                  ->with(['customer', 'role'])
                                  ->get()
                                  ->map(function ($member) use ($projectId) {
                                      $memberName = $member->customer_id 
                                          ? ($member->customer->nick_name ?: 
                                             ($member->customer->first_name . ' ' . $member->customer->last_name))
                                          : $member->member_name;
                                      
                                      // メンバーの支出合計を計算
                                      $totalExpense = \App\Models\ProjectTask::where('project_id', $projectId)
                                          ->where('task_member_name', $memberName)
                                          ->where('accounting_type', 'expense')
                                          ->where('del_flg', false)
                                          ->sum('accounting_amount');
                                      
                                      return [
                                          'id' => $member->id,
                                          'project_member_id' => $member->project_member_id,
                                          'customer_id' => $member->customer_id,
                                          'role' => $member->role->role_code,
                                          'role_name' => $member->role->role_name,
                                          'split_weight' => $member->split_weight,
                                          'memo' => $member->memo,
                                          'name' => $memberName,
                                          'email' => $member->customer_id 
                                              ? $member->customer->email 
                                              : $member->member_email,
                                          'is_guest' => $member->customer_id 
                                              ? $member->customer->is_guest 
                                              : true,
                                          'joined_at' => $member->created_at,
                                          'total_expense' => (float) $totalExpense,
                                      ];
                                  });

            return response()->json(['members' => $members]);
            
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'メンバー一覧の取得に失敗しました',
                'message' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], 500);
        }
    }

    /**
     * プロジェクトにメンバーを追加
     */
    public function store(Request $request, int $projectId): JsonResponse
    {
        try {
            $customer = $request->user();
            
            // バリデーション
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:255',
                'email' => 'nullable|email|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // プロジェクトの存在確認とアクセス権限チェック
            $project = Project::where('project_id', $projectId)
                             ->where('del_flg', false)
                             ->first();
            
            if (!$project) {
                return response()->json(['error' => 'プロジェクトが見つかりません'], 404);
            }

            // プロジェクトのオーナーかチェック
            $ownerRole = ProjectRole::where('role_code', 'owner')->first();
            $isOwner = ProjectMember::where('project_id', $projectId)
                                   ->where('customer_id', $customer->customer_id)
                                   ->where('role_id', $ownerRole->role_id)
                                   ->where('del_flg', false)
                                   ->exists();
            
            if (!$isOwner) {
                return response()->json(['error' => 'メンバー追加の権限がありません'], 403);
            }

            DB::beginTransaction();

            try {
                $email = $request->input('email');
                $name = $request->input('name');

                // メールアドレスが提供されている場合のみ、既存の顧客を検索
                if ($email) {
                    $existingCustomer = Customer::where('email', $email)
                                              ->where('del_flg', false)
                                              ->first();
                    
                    if ($existingCustomer) {
                        // 既存の顧客が存在する場合、その顧客をメンバーとして追加
                        $customerId = $existingCustomer->customer_id;
                    } else {
                        // 既存の顧客が存在しない場合、ゲストユーザーとして新規作成
                        $newCustomer = Customer::create([
                            'is_guest' => true,
                            'nick_name' => $name,
                            'email' => $email,
                            'del_flg' => false,
                        ]);
                        $customerId = $newCustomer->customer_id;
                    }
                } else {
                    // メールアドレスが提供されていない場合、customerテーブルに新規登録しない
                    // プロジェクトメンバーとしてのみ登録（customer_idはnull）
                    $customerId = null;
                }

                // 既にメンバーとして登録されていないかチェック
                if ($customerId) {
                    // customer_idがある場合（メールアドレスあり）
                    $existingMember = ProjectMember::where('project_id', $projectId)
                                                  ->where('customer_id', $customerId)
                                                  ->where('del_flg', false)
                                                  ->exists();
                } else {
                    // customer_idがnullの場合（メールアドレスなし）
                    $existingMember = ProjectMember::where('project_id', $projectId)
                                                  ->where('customer_id', null)
                                                  ->where('member_name', $name)
                                                  ->where('del_flg', false)
                                                  ->exists();
                }
                
                if ($existingMember) {
                    DB::rollBack();
                    return response()->json(['error' => 'このメンバーは既に追加されています'], 409);
                }

                // メンバーとして追加
                $memberRole = ProjectRole::where('role_code', 'member')->first();
                if (!$memberRole) {
                    DB::rollBack();
                    return response()->json(['error' => 'メンバーロールが見つかりません'], 500);
                }

                // プロジェクト内での次のproject_member_idを取得
                $nextProjectMemberId = ProjectMember::where('project_id', $projectId)
                                                  ->where('del_flg', false)
                                                  ->max('project_member_id') + 1;

                $projectMember = ProjectMember::create([
                    'project_id' => $projectId,
                    'project_member_id' => $nextProjectMemberId,
                    'customer_id' => $customerId,
                    'member_name' => $customerId ? null : $name,
                    'member_email' => $customerId ? null : $email,
                    'role_id' => $memberRole->role_id,
                    'split_weight' => 1.00,
                    'del_flg' => false,
                ]);

                // 追加されたメンバー情報を取得
                $member = ProjectMember::where('id', $projectMember->id)
                                      ->with(['customer', 'role'])
                                      ->first();

                // メンバーの支出合計を計算
                $memberName = $member->customer_id 
                    ? ($member->customer->nick_name ?: 
                       ($member->customer->first_name . ' ' . $member->customer->last_name))
                    : $member->member_name;
                
                $totalExpense = \App\Models\ProjectTask::where('project_id', $projectId)
                    ->where('task_member_name', $memberName)
                    ->where('accounting_type', 'expense')
                    ->where('del_flg', false)
                    ->sum('accounting_amount');

                DB::commit();

                return response()->json([
                    'message' => 'メンバーが正常に追加されました',
                    'member' => [
                        'id' => $member->id,
                        'project_member_id' => $member->project_member_id,
                        'customer_id' => $member->customer_id,
                        'role' => $member->role->role_code,
                        'role_name' => $member->role->role_name,
                        'split_weight' => $member->split_weight,
                        'memo' => $member->memo,
                        'name' => $memberName,
                        'email' => $member->customer_id 
                            ? $member->customer->email 
                            : $member->member_email,
                        'is_guest' => $member->customer_id 
                            ? $member->customer->is_guest 
                            : true,
                        'joined_at' => $member->created_at,
                        'total_expense' => (float) $totalExpense,
                    ]
                ], 201);

            } catch (\Exception $e) {
                DB::rollBack();
                throw $e;
            }
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'メンバーの追加に失敗しました'], 500);
        }
    }

    /**
     * メンバーのメモを更新
     */
    public function updateMemo(Request $request, int $projectId, int $memberId): JsonResponse
    {
        try {
            $customer = $request->user();
            
            // バリデーション
            $validator = Validator::make($request->all(), [
                'memo' => 'nullable|string|max:1000',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // プロジェクトのオーナーかチェック
            $ownerRole = ProjectRole::where('role_code', 'owner')->first();
            $isOwner = ProjectMember::where('project_id', $projectId)
                                   ->where('customer_id', $customer->customer_id)
                                   ->where('role_id', $ownerRole->role_id)
                                   ->where('del_flg', false)
                                   ->exists();
            
            if (!$isOwner) {
                return response()->json(['error' => 'メモ変更の権限がありません'], 403);
            }

            // メンバーを更新
            $projectMember = ProjectMember::where('id', $memberId)
                                         ->where('project_id', $projectId)
                                         ->where('del_flg', false)
                                         ->first();
            
            if (!$projectMember) {
                return response()->json(['error' => 'メンバーが見つかりません'], 404);
            }

            $projectMember->update(['memo' => $request->input('memo')]);

            return response()->json([
                'message' => 'メモが正常に更新されました',
                'memo' => $projectMember->memo
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'メモの更新に失敗しました'], 500);
        }
    }

    /**
     * メンバーの比重を更新
     */
    public function updateWeight(Request $request, int $projectId, int $memberId): JsonResponse
    {
        try {
            $customer = $request->user();
            
            // バリデーション
            $validator = Validator::make($request->all(), [
                'split_weight' => 'required|numeric|min:0.01|max:999.99',
            ]);

            if ($validator->fails()) {
                return response()->json(['errors' => $validator->errors()], 422);
            }

            // プロジェクトのオーナーかチェック
            $ownerRole = ProjectRole::where('role_code', 'owner')->first();
            $isOwner = ProjectMember::where('project_id', $projectId)
                                   ->where('customer_id', $customer->customer_id)
                                   ->where('role_id', $ownerRole->role_id)
                                   ->where('del_flg', false)
                                   ->exists();
            
            if (!$isOwner) {
                return response()->json(['error' => '比重変更の権限がありません'], 403);
            }

            // メンバーを更新
            $projectMember = ProjectMember::where('project_member_id', $memberId)
                                         ->where('project_id', $projectId)
                                         ->where('del_flg', false)
                                         ->first();
            
            if (!$projectMember) {
                return response()->json(['error' => 'メンバーが見つかりません'], 404);
            }

            $projectMember->update(['split_weight' => $request->input('split_weight')]);

            return response()->json([
                'message' => '割り勘比重が正常に更新されました',
                'split_weight' => $projectMember->split_weight
            ]);
            
        } catch (\Exception $e) {
            return response()->json(['error' => '比重の更新に失敗しました'], 500);
        }
    }

    /**
     * プロジェクトからメンバーを削除
     */
    public function destroy(Request $request, int $projectId, int $memberId): JsonResponse
    {
        try {
            $customer = $request->user();
            
            // プロジェクトのオーナーかチェック
            $ownerRole = ProjectRole::where('role_code', 'owner')->first();
            $isOwner = ProjectMember::where('project_id', $projectId)
                                   ->where('customer_id', $customer->customer_id)
                                   ->where('role_id', $ownerRole->role_id)
                                   ->where('del_flg', false)
                                   ->exists();
            
            if (!$isOwner) {
                return response()->json(['error' => 'メンバー削除の権限がありません'], 403);
            }

            // メンバーを論理削除
            $projectMember = ProjectMember::where('project_member_id', $memberId)
                                         ->where('project_id', $projectId)
                                         ->where('del_flg', false)
                                         ->first();
            
            if (!$projectMember) {
                return response()->json(['error' => 'メンバーが見つかりません'], 404);
            }

            $projectMember->update(['del_flg' => true]);

            return response()->json(['message' => 'メンバーが正常に削除されました']);
            
        } catch (\Exception $e) {
            return response()->json(['error' => 'メンバーの削除に失敗しました'], 500);
        }
    }
}
