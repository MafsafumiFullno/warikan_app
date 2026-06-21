<?php

namespace App\Http\Controllers;

use App\Services\Project\ProjectService;
use App\Services\Split\AdvancedSplitService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Psr\Log\LoggerInterface;

class SplitCalculationController extends Controller
{
    protected AdvancedSplitService $splitService;
    protected ProjectService $projectService;
    protected LoggerInterface $logger;

    public function __construct(
        AdvancedSplitService $splitService,
        ProjectService $projectService,
        LoggerInterface $logger
    ) {
        $this->splitService = $splitService;
        $this->projectService = $projectService;
        $this->logger = $logger;
    }

    /**
     * プロジェクトの割り勘計算を実行
     * 
     * @param Request $request
     * @param int $projectId
     * @return JsonResponse
     */
    public function calculate(Request $request, int $projectId): JsonResponse
    {
        try {
            $customer = $request->user();
            $this->projectService->getProjectWithAccessCheck($customer->customer_id, $projectId);

            // 割り勘計算を実行
            $result = $this->splitService->calculateSplitForProject($projectId);

            return response()->json([
                'message' => '割り勘計算が完了しました',
                'data' => $result
            ], 200);

        } catch (\Exception $e) {
            return $this->handleException($e, $request, $this->logger, '割り勘計算');
        }
    }
}
