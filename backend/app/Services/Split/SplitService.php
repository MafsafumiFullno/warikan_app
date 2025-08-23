<?php

namespace App\Services\Split;

use App\Models\ProjectTask;

class SplitService
{
    /**
     * @param int $projectId
     * @param SplitStrategyInterface $strategy
     * @param array<string,mixed> $options
     * @return array<string,mixed>
     */
    public function calculateForProject(int $projectId, SplitStrategyInterface $strategy, array $options = []): array
    {
        $participants = $this->collectParticipants($projectId);
        return $strategy->calculate($participants, $options);
    }

    /**
     * @return array<int, array{customer_id:int,total_paid:float}>
     */
    private function collectParticipants(int $projectId): array
    {
        $tasks = ProjectTask::query()
            ->where('project_id', $projectId)
            ->where('del_flg', false)
            ->get(['customer_id', 'accounting_amount']);

        $customerToPaid = [];
        foreach ($tasks as $task) {
            $cid = (int) $task->customer_id;
            $amount = (float) $task->accounting_amount;
            if (!isset($customerToPaid[$cid])) {
                $customerToPaid[$cid] = 0.0;
            }
            $customerToPaid[$cid] += $amount;
        }

        $participants = [];
        foreach ($customerToPaid as $cid => $paid) {
            $participants[] = [
                'customer_id' => (int) $cid,
                'total_paid' => round((float) $paid, 2),
            ];
        }

        return $participants;
    }
}


