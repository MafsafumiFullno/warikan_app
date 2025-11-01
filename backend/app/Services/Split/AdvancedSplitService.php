<?php

namespace App\Services\Split;

use App\Models\ProjectTask;
use App\Models\ProjectMember;
use App\Models\ProjectTaskMember;
use App\Models\Project;

class AdvancedSplitService
{
    /**
     * プロジェクトの割り勘計算を実行（比重と会計タスク対象者を考慮）
     * 
     * @param int $projectId
     * @return array
     */
    public function calculateSplitForProject(int $projectId): array
    {
        // プロジェクトの全メンバー（オーナー含む）を取得
        $allMembers = $this->getAllProjectMembers($projectId);
        
        // 会計タスクとその対象者を取得
        $tasksWithTargets = $this->getTasksWithTargetMembers($projectId);
        
        // 各メンバーの支払い総額を計算
        $memberPayments = $this->calculateMemberPayments($tasksWithTargets, $allMembers);
        
        // 各メンバーの負担額を比重に基づいて計算
        $memberShares = $this->calculateMemberShares($allMembers, $memberPayments);
        
        // 支払いフローを計算
        $paymentFlow = $this->calculatePaymentFlow($memberShares);
        
        return [
            'project_id' => $projectId,
            'total_amount' => array_sum(array_column($memberPayments, 'total_share')),
            'members' => $memberShares,
            'payment_flow' => $paymentFlow,
            'calculation_date' => now()->toISOString(),
        ];
    }

    /**
     * プロジェクトの全メンバー（オーナー含む）を取得
     */
    private function getAllProjectMembers(int $projectId): array
    {
        $project = Project::find($projectId);
        $members = [];
        $ownerCustomerId = null;
        
        // オーナーを追加
        if ($project) {
            $owner = $project->customer;
            $ownerCustomerId = $owner->customer_id;
            
            // オーナーがproject_membersテーブルにも登録されているかチェック
            $ownerAsMember = ProjectMember::where('project_id', $projectId)
                ->where('customer_id', $ownerCustomerId)
                ->where('del_flg', false)
                ->first();
            
            if ($ownerAsMember) {
                // オーナーがproject_membersテーブルにも登録されている場合
                $members[] = [
                    'member_id' => $ownerAsMember->id,
                    'customer_id' => $owner->customer_id,
                    'member_name' => trim($owner->first_name . ' ' . $owner->last_name) ?: $owner->nick_name ?: 'オーナー',
                    'split_weight' => $ownerAsMember->split_weight ?? 1.0,
                    'is_owner' => true,
                ];
            } else {
                // オーナーがproject_membersテーブルに登録されていない場合（仮想的なメンバー）
                // オーナー専用の仮想member_idとして負の値を使用
                $members[] = [
                    'member_id' => -1, // オーナー専用の仮想ID（負の値で区別）
                    'customer_id' => $owner->customer_id,
                    'member_name' => trim($owner->first_name . ' ' . $owner->last_name) ?: $owner->nick_name ?: 'オーナー',
                    'split_weight' => 1.0,
                    'is_owner' => true,
                ];
            }
        }
        
        // プロジェクトメンバーを追加（オーナーは除外）
        $projectMembers = ProjectMember::where('project_id', $projectId)
            ->where('del_flg', false)
            ->with('customer')
            ->get();
            
        foreach ($projectMembers as $member) {
            // オーナーがプロジェクトメンバーとしても登録されている場合はスキップ
            if ($member->customer_id === $ownerCustomerId) {
                continue;
            }
            
            // ゲストメンバー（customer_idがnull）の場合はmember_nameを使用
            // 登録済みメンバーの場合はcustomer情報から名前を取得
            $memberName = $member->customer_id 
                ? ($member->customer->nick_name ?: trim($member->customer->first_name . ' ' . $member->customer->last_name) ?: 'メンバー')
                : $member->member_name;
            
            $members[] = [
                'member_id' => $member->id,
                'customer_id' => $member->customer_id,
                'member_name' => $memberName,
                'split_weight' => $member->split_weight ?? 1.0,
                'is_owner' => false,
            ];
        }
        
        return $members;
    }

    /**
     * 会計タスクとその対象者を取得
     */
    private function getTasksWithTargetMembers(int $projectId): array
    {
        return ProjectTask::where('project_id', $projectId)
            ->where('del_flg', false)
            ->with(['projectMember.customer', 'taskMembers.projectMember.customer'])
            ->get()
            ->map(function ($task) {
                // 支払人の情報を取得（member_idベース）
                $payerMemberId = $task->member_id;
                $payerCustomerId = null;
                $payerMemberName = null;
                
                if ($task->projectMember) {
                    $payerCustomerId = $task->projectMember->customer_id;
                    $payerMemberName = $task->projectMember->customer_id 
                        ? ($task->projectMember->customer->nick_name ?: trim($task->projectMember->customer->first_name . ' ' . $task->projectMember->customer->last_name) ?: 'メンバー')
                        : $task->projectMember->member_name;
                }
                
                return [
                    'task_id' => $task->task_id,
                    'task_name' => $task->task_name,
                    'accounting_amount' => $task->accounting_amount,
                    'accounting_type' => $task->accounting_type,
                    'payer_member_id' => $payerMemberId,
                    'payer_customer_id' => $payerCustomerId,
                    'payer_member_name' => $payerMemberName,
                    'target_members' => $task->taskMembers->map(function ($taskMember) {
                        $member = $taskMember->projectMember;
                        if (!$member) return null;
                        
                        // ゲストメンバー（customer_idがnull）の場合はmember_nameを使用
                        // 登録済みメンバーの場合はcustomer情報から名前を取得
                        $memberName = $member->customer_id 
                            ? ($member->customer->nick_name ?: trim($member->customer->first_name . ' ' . $member->customer->last_name) ?: 'メンバー')
                            : $member->member_name;
                        
                        return [
                            'member_id' => $member->id,
                            'customer_id' => $member->customer_id,
                            'member_name' => $memberName,
                        ];
                    })->filter()->toArray(),
                ];
            })
            ->toArray();
    }

    /**
     * 各メンバーの支払い総額と負担額を計算
     */
    private function calculateMemberPayments(array $tasksWithTargets, array $allMembers): array
    {
        $payments = [];
        
        // 全メンバーの初期化
        foreach ($allMembers as $member) {
            $memberId = $member['member_id'];
            $key = $memberId; // member_idを直接キーとして使用
            $payments[$key] = [
                'member_id' => $memberId,
                'customer_id' => $member['customer_id'],
                'member_name' => $member['member_name'],
                'total_paid' => 0,
                'total_share' => 0,
                'split_weight' => $member['split_weight'],
            ];
        }
        
        foreach ($tasksWithTargets as $task) {
            $payerMemberId = $task['payer_member_id'];
            $amount = $task['accounting_type'] === 'expense' ? $task['accounting_amount'] : -$task['accounting_amount'];
            $targetMembers = $task['target_members'];
            
            // 支払人の実際の支払額を記録（member_idベース）
            // member_idが設定されている場合はそのメンバーが支払ったものとして処理
            if ($payerMemberId) {
                // メンバーが支払った場合
                $payerKey = $payerMemberId;
            } else {
                // オーナーが支払った場合（member_idがnull）
                // オーナーの仮想member_id（-1）を使用
                $payerKey = -1;
            }
            
            if (isset($payments[$payerKey])) {
                $payments[$payerKey]['total_paid'] += $amount;
            }
            
            // 対象メンバーに比重に基づいて負担額を配分
            if (!empty($targetMembers)) {
                $targetWeights = [];
                $totalWeight = 0;
                
                // 対象メンバーの比重を取得（実際に存在するメンバーのみ）
                foreach ($targetMembers as $targetMember) {
                    $targetMemberId = $targetMember['member_id'];
                    $targetKey = $targetMemberId ?? -1; // オーナーの場合は-1
                    
                    if (isset($payments[$targetKey])) {
                        $weight = $payments[$targetKey]['split_weight'];
                        $targetWeights[$targetKey] = $weight;
                        $totalWeight += $weight;
                    }
                }
                
                // 比重に基づいて配分（小数点処理付き）
                if ($totalWeight > 0) {
                    $remainingAmount = $amount;
                    $processedMembers = 0;
                    
                    foreach ($targetWeights as $targetKey => $weight) {
                        $processedMembers++;
                        $isLastMember = $processedMembers === count($targetWeights);
                        
                        if ($isLastMember) {
                            // 最後のメンバーには残りを全て配分（端数処理）
                            $shareAmount = $remainingAmount;
                        } else {
                            // 比重に基づいて配分（整数に丸める）
                            $shareAmount = (int)round($amount * $weight / $totalWeight);
                            $remainingAmount -= $shareAmount;
                        }
                        
                        // 対象メンバーのみに負担額を配分
                        $payments[$targetKey]['total_share'] += $shareAmount;
                    }
                }
            }
        }
        
        return array_values($payments);
    }

    /**
     * 各メンバーの負担額を比重に基づいて計算
     */
    private function calculateMemberShares(array $allMembers, array $memberPayments): array
    {
        $memberShares = [];
        
        foreach ($allMembers as $member) {
            $memberId = $member['member_id'];
            $customerId = $member['customer_id'];
            $paidAmount = 0;
            $shareAmount = 0;
            
            // 実際に支払った金額と負担額を取得
            foreach ($memberPayments as $payment) {
                if ($payment['member_id'] === $memberId) {
                    $paidAmount = $payment['total_paid'];
                    $shareAmount = $payment['total_share'];
                    break;
                }
            }
            
            $balance = $paidAmount - $shareAmount; // +: 受取, -: 支払
            
            $memberShares[] = [
                'customer_id' => $customerId,
                'member_name' => $member['member_name'],
                'split_weight' => $member['split_weight'],
                'is_owner' => $member['is_owner'],
                'total_paid' => (int)round($paidAmount),
                'share_amount' => (int)round($shareAmount),
                'balance' => (int)round($balance),
            ];
        }
        
        return $memberShares;
    }

    /**
     * 支払いフローを計算（誰が誰にいくら支払うか）
     */
    private function calculatePaymentFlow(array $memberShares): array
    {
        $receivers = []; // 受取人（balance > 0）
        $payers = [];    // 支払人（balance < 0）
        
        foreach ($memberShares as $member) {
            if ($member['balance'] > 0) {
                $receivers[] = $member;
            } elseif ($member['balance'] < 0) {
                $payers[] = $member;
            }
        }
        
        $paymentFlow = [];
        
        // 支払いフローを計算
        $receiverIndex = 0;
        $payerIndex = 0;
        
        while ($receiverIndex < count($receivers) && $payerIndex < count($payers)) {
            $receiver = $receivers[$receiverIndex];
            $payer = $payers[$payerIndex];
            
            $amount = min($receiver['balance'], abs($payer['balance']));
            
            if ($amount > 0) {
                $paymentFlow[] = [
                    'from_customer_id' => $payer['customer_id'],
                    'from_member_name' => $payer['member_name'],
                    'to_customer_id' => $receiver['customer_id'],
                    'to_member_name' => $receiver['member_name'],
                    'amount' => (int)round($amount),
                ];
                
                // 残高を更新
                $receivers[$receiverIndex]['balance'] -= $amount;
                $payers[$payerIndex]['balance'] += $amount;
                
                // 残高が0になったら次の人へ
                if ($receivers[$receiverIndex]['balance'] <= 0) {
                    $receiverIndex++;
                }
                if ($payers[$payerIndex]['balance'] >= 0) {
                    $payerIndex++;
                }
            }
        }
        
        return $paymentFlow;
    }
}
