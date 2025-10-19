<?php

namespace App\Http\Controllers;

use App\Models\Project;
use App\Services\Split\AdvancedSplitService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Log;

class SplitCalculationController extends Controller
{
    protected AdvancedSplitService $splitService;

    public function __construct(AdvancedSplitService $splitService)
    {
        $this->splitService = $splitService;
    }

    /**
     * プロジェクトのアクセス権限をチェック
     */
    private function checkProjectAccess(Request $request, int $projectId): array
    {
        $customer = $request->user();

        // プロジェクトの存在確認
        $project = Project::where('project_id', $projectId)
            ->where('del_flg', false)
            ->first();
        
        if (!$project) {
            return [
                'success' => false,
                'response' => response()->json([
                    'message' => 'プロジェクトが見つかりません'
                ], 404)
            ];
        }

        // プロジェクトのオーナーまたはメンバーかチェック
        $isOwner = $project->customer_id === $customer->customer_id;
        $isMember = \App\Models\ProjectMember::where('project_id', $projectId)
                                            ->where('customer_id', $customer->customer_id)
                                            ->where('del_flg', false)
                                            ->exists();
        
        if (!$isOwner && !$isMember) {
            return [
                'success' => false,
                'response' => response()->json([
                    'message' => 'アクセス権限がありません'
                ], 403)
            ];
        }

        return [
            'success' => true,
            'project' => $project,
            'customer' => $customer
        ];
    }
    /**
     * プロジェクトの割り勘計算を実行
     */
    public function calculate(Request $request, int $projectId): JsonResponse
    {
        try {
            // プロジェクトアクセス権限チェック
            $accessCheck = $this->checkProjectAccess($request, $projectId);
            if (!$accessCheck['success']) {
                return $accessCheck['response'];
            }

            // 割り勘計算を実行
            $result = $this->splitService->calculateSplitForProject($projectId);

            return response()->json([
                'message' => '割り勘計算が完了しました',
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            Log::error('割り勘計算エラー', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'project_id' => $projectId,
                'customer_id' => $request->user()?->customer_id,
                'request_data' => $request->all()
            ]);
            
            return response()->json([
                'message' => '割り勘計算に失敗しました',
                'error' => config('app.debug') ? $e->getMessage() : 'サーバーエラーが発生しました'
            ], 500);
        }
    }
}
