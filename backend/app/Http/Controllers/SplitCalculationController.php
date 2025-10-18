<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Split\AdvancedSplitService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SplitCalculationController extends Controller
{
    /**
     * プロジェクトの割り勘計算を実行
     */
    public function calculate(Request $request, int $projectId): JsonResponse
    {
        try {
            $customer = $request->user();

            // プロジェクトの存在確認
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

            // 割り勘計算を実行
            $splitService = new AdvancedSplitService();
            $result = $splitService->calculateSplitForProject($projectId);

            return response()->json([
                'message' => '割り勘計算が完了しました',
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            Log::error('割り勘計算エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $projectId,
                'customer_id' => $request->user()?->customer_id
            ]);
            
            return response()->json([
                'message' => '割り勘計算に失敗しました',
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], 500);
        }
    }
}
