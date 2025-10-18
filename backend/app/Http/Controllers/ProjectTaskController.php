<?php

namespace App\Http\Controllers;

use App\Models\ProjectTask;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ProjectTaskController extends Controller
{
    /**
     * プロジェクトの会計一覧を取得
     */
    public function index(Request $request, int $projectId): JsonResponse
    {
        try {
            $customer = $request->user();

            $project = Project::where('project_id', $projectId)
                ->where('del_flg', false)
                ->first();
            
            if (!$project) {
                return response()->json([
                    'message' => 'プロジェクトが見つかりません'
                ], 404);
            }

            // プロジェクトのオーナーまたはメンバーかチェック
            $isOwner = $project->customer_id === $customer->customer_id;
            $isMember = \App\Models\ProjectMember::where('project_id', $projectId)
                                                ->where('customer_id', $customer->customer_id)
                                                ->where('del_flg', false)
                                                ->exists();
            
            if (!$isOwner && !$isMember) {
                return response()->json([
                    'message' => 'アクセス権限がありません'
                ], 403);
            }

            $projectTasks = ProjectTask::where('project_id', $projectId)
                ->where('del_flg', false)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'accountings' => $projectTasks
            ], 200);

        } catch (\Exception $e) {
            Log::error('会計一覧取得エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $projectId,
                'customer_id' => $request->user()?->customer_id
            ]);
            
            return response()->json([
                'message' => '会計一覧の取得に失敗しました',
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], 500);
        }
    }

    /**
     * 会計を追加
     */
    public function store(Request $request, int $projectId): JsonResponse
    {
        try {
            $project = Project::findOrFail($projectId);
            
            // プロジェクトの所有者かチェック
            if ($project->customer_id !== $request->user()->customer_id) {
                return response()->json([
                    'message' => 'アクセス権限がありません'
                ], 403);
            }

            $validator = Validator::make($request->all(), [
                'accounting_name' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.01',
                'description' => 'nullable|string|max:1000',
                'accounting_type' => 'nullable|string|max:50',
                'member_name' => 'required|string|max:255',
                'target_member_ids' => 'nullable|array',
                'target_member_ids.*' => 'integer|exists:project_members,id',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'バリデーションエラー',
                    'errors' => $validator->errors(),
                ], 422);
            }

            // プロジェクトタスクコードを生成（既存の最大値+1）
            $maxTaskCode = $project->projectTasks()->max('project_task_code') ?? 0;
            $nextTaskCode = $maxTaskCode + 1;

            // 支払人（task_member_name）のプロジェクトメンバーIDを取得
            $payerMember = \App\Models\ProjectMember::where('project_id', $projectId)
                ->where('member_name', $request->member_name)
                ->where('del_flg', false)
                ->first();

            // オーナーの名前を取得（オーナーが支払った場合の判定用）
            $owner = $project->customer;
            $ownerName = trim($owner->first_name . ' ' . $owner->last_name) ?: $owner->nick_name ?: 'オーナー';

            $projectTaskData = [
                'project_id' => $projectId,
                'project_task_code' => $nextTaskCode,
                'task_name' => $request->accounting_name,
                'task_member_name' => $request->member_name,
                'accounting_amount' => $request->amount,
                'accounting_type' => $request->accounting_type ?? 'expense',
                'breakdown' => $request->description,
                'memo' => null,
                'del_flg' => false,
            ];

            // 支払人がproject_membersテーブルに登録されている場合はmember_idを使用
            if ($payerMember) {
                $projectTaskData['member_id'] = $payerMember->id;
            } elseif ($request->member_name === $ownerName) {
                // 支払人がオーナーの場合はcustomer_idを使用
                $projectTaskData['customer_id'] = $owner->customer_id;
            } else {
                // その他の場合（ゲストメンバーなど）はcustomer_idを使用
                $projectTaskData['customer_id'] = $request->user()->customer_id;
            }

            $projectTask = ProjectTask::create($projectTaskData);

            // 対象メンバーをproject_task_membersテーブルに追加（target_member_idsが提供されている場合のみ）
            if ($request->has('target_member_ids') && is_array($request->target_member_ids)) {
                foreach ($request->target_member_ids as $memberId) {
                    \App\Models\ProjectTaskMember::create([
                        'member_id' => $memberId,
                        'task_id' => $projectTask->task_id,
                        'del_flg' => false,
                    ]);
                }
            }

            return response()->json([
                'message' => '会計を追加しました',
                'accounting' => $projectTask
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '会計の追加に失敗しました',
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], 500);
        }
    }

    /**
     * 会計を更新
     */
    public function update(Request $request, int $projectId, int $taskId): JsonResponse
    {
        try {
            $project = Project::findOrFail($projectId);
            
            // プロジェクトの所有者かチェック
            if ($project->customer_id !== $request->user()->customer_id) {
                return response()->json([
                    'message' => 'アクセス権限がありません'
                ], 403);
            }

            $projectTask = ProjectTask::where('project_id', $projectId)
                ->where('task_id', $taskId)
                ->where('del_flg', false)
                ->firstOrFail();

            $validator = Validator::make($request->all(), [
                'accounting_name' => 'required|string|max:255',
                'amount' => 'required|numeric|min:0.01',
                'description' => 'nullable|string|max:1000',
                'accounting_type' => 'nullable|string|max:50',
                'member_name' => 'required|string|max:255',
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'message' => 'バリデーションエラー',
                    'errors' => $validator->errors(),
                ], 422);
            }

            $projectTask->update([
                'task_name' => $request->accounting_name,
                'task_member_name' => $request->member_name,
                'accounting_amount' => $request->amount,
                'accounting_type' => $request->accounting_type ?? 'expense',
                'breakdown' => $request->description,
            ]);

            return response()->json([
                'message' => '会計を更新しました',
                'accounting' => $projectTask->fresh()
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '会計の更新に失敗しました',
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], 500);
        }
    }

    /**
     * 会計を削除（論理削除）
     */
    public function destroy(Request $request, int $projectId, int $taskId): JsonResponse
    {
        try {
            $project = Project::findOrFail($projectId);
            
            // プロジェクトの所有者かチェック
            if ($project->customer_id !== $request->user()->customer_id) {
                return response()->json([
                    'message' => 'アクセス権限がありません'
                ], 403);
            }

            $projectTask = ProjectTask::where('project_id', $projectId)
                ->where('task_id', $taskId)
                ->where('del_flg', false)
                ->firstOrFail();

            $projectTask->update(['del_flg' => true]);

            return response()->json([
                'message' => '会計を削除しました'
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'message' => '会計の削除に失敗しました',
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], 500);
        }
    }
}
