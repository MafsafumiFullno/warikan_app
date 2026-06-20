<?php

namespace App\Http\Controllers;

use App\Services\Project\ProjectShareLinkService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

class ProjectShareLinkController extends Controller
{
    protected ProjectShareLinkService $projectShareLinkService;
    protected LoggerInterface $logger;

    public function __construct(
        ProjectShareLinkService $projectShareLinkService,
        LoggerInterface $logger
    ) {
        $this->projectShareLinkService = $projectShareLinkService;
        $this->logger = $logger;
    }

    /**
     * 共有リンクを作成（既存があれば返却）
     */
    public function store(Request $request, int $projectId): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->projectShareLinkService->createOrGetShareLink($customer->customer_id, $projectId);

            return response()->json($result);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, $this->logger, '共有リンク作成');
        }
    }

    /**
     * 共有トークンで公開プロジェクト詳細を取得
     */
    public function showByToken(Request $request, string $token): JsonResponse
    {
        try {
            $result = $this->projectShareLinkService->getPublicProjectByToken($token);

            return response()->json($result);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, $this->logger, '共有リンク公開詳細取得');
        }
    }
}
