<?php

namespace App\Http\Controllers;

use App\Services\Project\ProjectTaskService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Psr\Log\LoggerInterface;

class ProjectTaskController extends Controller
{
    protected ProjectTaskService $projectTaskService;
    protected LoggerInterface $logger;

    public function __construct(
        ProjectTaskService $projectTaskService,
        LoggerInterface $logger
    ) {
        $this->projectTaskService = $projectTaskService;
        $this->logger = $logger;
    }
    /**
     * プロジェクトの会計一覧を取得
     */
    public function index(Request $request, int $projectId): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->projectTaskService->getProjectTasks($customer->customer_id, $projectId);

            return response()->json($result);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, $this->logger, '会計一覧取得');
        }
    }

    /**
     * 会計を追加
     */
    public function store(Request $request, int $projectId): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->projectTaskService->createProjectTask($customer->customer_id, $projectId, $request->all());

            return response()->json($result, 201);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, $this->logger, '会計追加');
        }
    }

    /**
     * 会計を更新
     */
    public function update(Request $request, int $projectId, int $taskId): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->projectTaskService->updateProjectTask($customer->customer_id, $projectId, $taskId, $request->all());

            return response()->json($result);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, $this->logger, '会計更新');
        }
    }

    /**
     * 会計を削除（論理削除）
     */
    public function destroy(Request $request, int $projectId, int $taskId): JsonResponse
    {
        try {
            $customer = $request->user();
            $result = $this->projectTaskService->deleteProjectTask($customer->customer_id, $projectId, $taskId);

            return response()->json($result);
        } catch (\Exception $e) {
            return $this->handleException($e, $request, $this->logger, '会計削除');
        }
    }
}
