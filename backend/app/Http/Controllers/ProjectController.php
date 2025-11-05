<?php

namespace App\Http\Controllers;

use App\Services\Project\ProjectService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

class ProjectController extends Controller
{
    protected ProjectService $projectService;
    protected LoggerInterface $logger;

    public function __construct(
        ProjectService $projectService,
        LoggerInterface $logger
    ) {
        $this->projectService = $projectService;
        $this->logger = $logger;
    }

    /**
     * 一覧取得（ログイン中の顧客のプロジェクトのみ）
     */
    public function index(Request $request): JsonResponse
    {
        try {
            $customer = $request->user();
            $filters = $request->only(['project_status', 'q', 'per_page']);
            
            $result = $this->projectService->getProjectsForCustomer($customer->customer_id, $filters);

            return response()->json($result);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, $this->logger, 'プロジェクト一覧取得');
        }
    }

    /**
     * プロジェクト作成
     */
    public function store(Request $request): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->projectService->createProject($customer->customer_id, $request->all());

            return response()->json($result, 201);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, $this->logger, 'プロジェクト作成');
        }
    }

    /**
     * プロジェクト詳細取得
     */
    public function show(Request $request, int $projectId): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->projectService->getProjectWithAccessCheck($customer->customer_id, $projectId);

            return response()->json($result);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, $this->logger, 'プロジェクト詳細取得');
        }
    }

    /**
     * プロジェクト更新
     */
    public function update(Request $request, int $projectId): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->projectService->updateProject($customer->customer_id, $projectId, $request->all());

            return response()->json($result);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, $this->logger, 'プロジェクト更新');
        }
    }

    /**
     * プロジェクト論理削除
     */
    public function destroy(Request $request, int $projectId): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->projectService->deleteProject($customer->customer_id, $projectId);

            return response()->json($result);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, $this->logger, 'プロジェクト削除');
        }
    }

}
