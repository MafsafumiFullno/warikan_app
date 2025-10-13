<?php

namespace App\Http\Controllers;

use App\Models\ProjectTask;
use App\Models\Project;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ProjectTaskController extends Controller
{
    /**
     * プロジェクトの会計一覧を取得
     */
    public function index(Request $request, int $projectId): JsonResponse
    {
        try {
            $project = Project::findOrFail($projectId);
            
            // プロジェクトの所有者かチェック
            if ($project->customer_id !== $request->user()->customer_id) {
                return response()->json([
                    'message' => 'アクセス権限がありません'
                ], 403);
            }

            $projectTasks = $project->projectTasks()
                ->where('del_flg', false)
                ->orderBy('created_at', 'desc')
                ->get();

            return response()->json([
                'accountings' => $projectTasks
            ], 200);

        } catch (\Exception $e) {
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

            $projectTask = ProjectTask::create([
                'project_id' => $projectId,
                'project_task_code' => $nextTaskCode,
                'task_name' => $request->accounting_name,
                'task_member_name' => $request->member_name,
                'customer_id' => $request->user()->customer_id,
                'accounting_amount' => $request->amount,
                'accounting_type' => $request->accounting_type ?? 'expense',
                'breakdown' => $request->description,
                'memo' => null,
                'del_flg' => false,
            ]);

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
