<?php

namespace Tests\Unit\Services\Split;

use App\Services\Split\AdvancedSplitService;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

class AdvancedSplitServiceTest extends TestCase
{
    private function invokePrivate(string $methodName, object $instance, array $args = [])
    {
        $ref = new ReflectionMethod($instance, $methodName);
        $ref->setAccessible(true);
        return $ref->invokeArgs($instance, $args);
    }

    // ===== calculateMemberPaymentsメソッドのテスト =====

    /**
     * 比重とオーナーの仮想IDを考慮したメンバー支払い計算
     * オーナーの仮想member_id(-1)が正しく処理されることを確認
     */
    public function test_calculate_member_payments_with_weights_and_owner_virtual_id(): void
    {
        $service = new AdvancedSplitService();

        $allMembers = [
            ['member_id' => -1, 'customer_id' => 10, 'member_name' => 'オーナー', 'split_weight' => 1.0, 'is_owner' => true],
            ['member_id' => 101, 'customer_id' => 101, 'member_name' => 'A', 'split_weight' => 1.0, 'is_owner' => false],
            ['member_id' => 102, 'customer_id' => 102, 'member_name' => 'B', 'split_weight' => 2.0, 'is_owner' => false],
        ];

        $tasksWithTargets = [
            [
                'task_id' => 1,
                'task_name' => '夕食',
                'accounting_amount' => 3000,
                'accounting_type' => 'expense',
                'payer_member_id' => 101,
                'target_members' => [
                    ['member_id' => 101, 'customer_id' => 101, 'member_name' => 'A'],
                    ['member_id' => 102, 'customer_id' => 102, 'member_name' => 'B'],
                ],
            ],
            [
                'task_id' => 2,
                'task_name' => '割引',
                'accounting_amount' => 1000,
                'accounting_type' => 'income',
                'payer_member_id' => null,
                'target_members' => [
                    ['member_id' => 101, 'customer_id' => 101, 'member_name' => 'A'],
                    ['member_id' => 102, 'customer_id' => 102, 'member_name' => 'B'],
                ],
            ],
        ];

        $payments = $this->invokePrivate('calculateMemberPayments', $service, [$tasksWithTargets, $allMembers]);

        $byId = [];
        foreach ($payments as $row) {
            $byId[$row['member_id']] = $row;
        }

        $this->assertSame(3000, $byId[101]['total_paid']);
        $this->assertSame(667, $byId[101]['total_share']);
        $this->assertSame(0, $byId[-1]['total_share']);
        $this->assertSame(-1000, $byId[-1]['total_paid']);
        $this->assertSame(1333, $byId[102]['total_share']);
    }

    // ===== calculateMemberSharesメソッドのテスト =====

    /**
     * メンバーの負担額計算と端数処理、オーナーフラグの確認
     */
    public function test_calculate_member_shares_rounding_and_flags(): void
    {
        $service = new AdvancedSplitService();

        $allMembers = [
            ['member_id' => -1, 'customer_id' => 10, 'member_name' => 'オーナー', 'split_weight' => 1.0, 'is_owner' => true],
            ['member_id' => 101, 'customer_id' => 101, 'member_name' => 'A', 'split_weight' => 1.0, 'is_owner' => false],
            ['member_id' => 102, 'customer_id' => 102, 'member_name' => 'B', 'split_weight' => 2.0, 'is_owner' => false],
        ];

        $memberPayments = [
            ['member_id' => -1, 'customer_id' => 10, 'member_name' => 'オーナー', 'total_paid' => -1000, 'total_share' => 0, 'split_weight' => 1.0],
            ['member_id' => 101, 'customer_id' => 101, 'member_name' => 'A', 'total_paid' => 3000, 'total_share' => 667, 'split_weight' => 1.0],
            ['member_id' => 102, 'customer_id' => 102, 'member_name' => 'B', 'total_paid' => 0, 'total_share' => 1333, 'split_weight' => 2.0],
        ];

        $shares = $this->invokePrivate('calculateMemberShares', $service, [$allMembers, $memberPayments]);

        $byCustomer = [];
        foreach ($shares as $row) {
            $byCustomer[$row['customer_id']] = $row;
        }

        $this->assertSame(0, $byCustomer[10]['share_amount']);
        $this->assertSame(-1000, $byCustomer[10]['total_paid']);
        $this->assertTrue($byCustomer[10]['is_owner']);

        $this->assertSame(667, $byCustomer[101]['share_amount']);
        $this->assertSame(3000, $byCustomer[101]['total_paid']);
        $this->assertFalse($byCustomer[101]['is_owner']);

        $this->assertSame(1333, $byCustomer[102]['share_amount']);
    }

    // ===== calculatePaymentFlowメソッドのテスト =====

    /**
     * 支払いフロー計算が残高に基づいて正しく生成されることを確認
     */
    public function test_calculate_payment_flow_matches_balances(): void
    {
        $service = new AdvancedSplitService();

        $memberShares = [
            ['customer_id' => 101, 'member_name' => 'A', 'split_weight' => 1.0, 'is_owner' => false, 'total_paid' => 2000, 'share_amount' => 1500, 'balance' => 500],
            ['customer_id' => 102, 'member_name' => 'B', 'split_weight' => 1.0, 'is_owner' => false, 'total_paid' => 100, 'share_amount' => 400, 'balance' => -300],
            ['customer_id' => 103, 'member_name' => 'C', 'split_weight' => 1.0, 'is_owner' => false, 'total_paid' => 0, 'share_amount' => 200, 'balance' => -200],
        ];

        $flow = $this->invokePrivate('calculatePaymentFlow', $service, [$memberShares]);

        $this->assertCount(2, $flow);
        $this->assertSame(102, $flow[0]['from_customer_id']);
        $this->assertSame(101, $flow[0]['to_customer_id']);
        $this->assertSame(300, $flow[0]['amount']);
        $this->assertSame(103, $flow[1]['from_customer_id']);
        $this->assertSame(101, $flow[1]['to_customer_id']);
        $this->assertSame(200, $flow[1]['amount']);
    }

    // ===== 境界値テスト =====

    /**
     * 0円の処理（タスクが存在しない場合）
     */
    public function test_calculate_member_payments_with_zero_tasks(): void
    {
        $service = new AdvancedSplitService();

        $allMembers = [
            ['member_id' => 101, 'customer_id' => 101, 'member_name' => 'A', 'split_weight' => 1.0, 'is_owner' => false],
            ['member_id' => 102, 'customer_id' => 102, 'member_name' => 'B', 'split_weight' => 1.0, 'is_owner' => false],
        ];

        $tasksWithTargets = [];

        $payments = $this->invokePrivate('calculateMemberPayments', $service, [$tasksWithTargets, $allMembers]);

        $byId = [];
        foreach ($payments as $row) {
            $byId[$row['member_id']] = $row;
        }

        $this->assertSame(0, $byId[101]['total_paid']);
        $this->assertSame(0, $byId[101]['total_share']);
        $this->assertSame(0, $byId[102]['total_paid']);
        $this->assertSame(0, $byId[102]['total_share']);
    }

    /**
     * 1円の最小金額の処理
     */
    public function test_calculate_member_payments_with_one_yen(): void
    {
        $service = new AdvancedSplitService();

        $allMembers = [
            ['member_id' => 101, 'customer_id' => 101, 'member_name' => 'A', 'split_weight' => 1.0, 'is_owner' => false],
            ['member_id' => 102, 'customer_id' => 102, 'member_name' => 'B', 'split_weight' => 1.0, 'is_owner' => false],
        ];

        $tasksWithTargets = [
            [
                'task_id' => 1,
                'task_name' => '最小金額',
                'accounting_amount' => 1,
                'accounting_type' => 'expense',
                'payer_member_id' => 101,
                'target_members' => [
                    ['member_id' => 101, 'customer_id' => 101, 'member_name' => 'A'],
                    ['member_id' => 102, 'customer_id' => 102, 'member_name' => 'B'],
                ],
            ],
        ];

        $payments = $this->invokePrivate('calculateMemberPayments', $service, [$tasksWithTargets, $allMembers]);

        $byId = [];
        foreach ($payments as $row) {
            $byId[$row['member_id']] = $row;
        }

        $this->assertSame(1, $byId[101]['total_paid']);
        $this->assertSame(1, $byId[101]['total_share'] + $byId[102]['total_share']);
        $this->assertSame(0, $byId[102]['total_paid']);
    }

    /**
     * 端数が発生するケース（1000円を3人で均等割り）
     */
    public function test_calculate_member_payments_with_remainder(): void
    {
        $service = new AdvancedSplitService();

        $allMembers = [
            ['member_id' => 101, 'customer_id' => 101, 'member_name' => 'A', 'split_weight' => 1.0, 'is_owner' => false],
            ['member_id' => 102, 'customer_id' => 102, 'member_name' => 'B', 'split_weight' => 1.0, 'is_owner' => false],
            ['member_id' => 103, 'customer_id' => 103, 'member_name' => 'C', 'split_weight' => 1.0, 'is_owner' => false],
        ];

        $tasksWithTargets = [
            [
                'task_id' => 1,
                'task_name' => '端数テスト',
                'accounting_amount' => 1000,
                'accounting_type' => 'expense',
                'payer_member_id' => 101,
                'target_members' => [
                    ['member_id' => 101, 'customer_id' => 101, 'member_name' => 'A'],
                    ['member_id' => 102, 'customer_id' => 102, 'member_name' => 'B'],
                    ['member_id' => 103, 'customer_id' => 103, 'member_name' => 'C'],
                ],
            ],
        ];

        $payments = $this->invokePrivate('calculateMemberPayments', $service, [$tasksWithTargets, $allMembers]);

        $byId = [];
        foreach ($payments as $row) {
            $byId[$row['member_id']] = $row;
        }

        $this->assertSame(1000, $byId[101]['total_paid']);
        // 端数は最後のメンバーに集約される（333, 333, 334 または 333, 334, 333 など）
        $totalShare = $byId[101]['total_share'] + $byId[102]['total_share'] + $byId[103]['total_share'];
        $this->assertSame(1000, $totalShare);
        // 各メンバーの負担額は整数で、合計が1000円になることを確認
        $this->assertIsInt($byId[101]['total_share']);
        $this->assertIsInt($byId[102]['total_share']);
        $this->assertIsInt($byId[103]['total_share']);
    }

    /**
     * 対象メンバーが1人のみの場合
     */
    public function test_calculate_member_payments_with_single_target_member(): void
    {
        $service = new AdvancedSplitService();

        $allMembers = [
            ['member_id' => 101, 'customer_id' => 101, 'member_name' => 'A', 'split_weight' => 1.0, 'is_owner' => false],
            ['member_id' => 102, 'customer_id' => 102, 'member_name' => 'B', 'split_weight' => 1.0, 'is_owner' => false],
        ];

        $tasksWithTargets = [
            [
                'task_id' => 1,
                'task_name' => '単一対象',
                'accounting_amount' => 1000,
                'accounting_type' => 'expense',
                'payer_member_id' => 101,
                'target_members' => [
                    ['member_id' => 101, 'customer_id' => 101, 'member_name' => 'A'],
                ],
            ],
        ];

        $payments = $this->invokePrivate('calculateMemberPayments', $service, [$tasksWithTargets, $allMembers]);

        $byId = [];
        foreach ($payments as $row) {
            $byId[$row['member_id']] = $row;
        }

        $this->assertSame(1000, $byId[101]['total_paid']);
        $this->assertSame(1000, $byId[101]['total_share']);
        $this->assertSame(0, $byId[102]['total_paid']);
        $this->assertSame(0, $byId[102]['total_share']);
    }

    /**
     * balanceが0の場合（支払いフローが発生しない）
     */
    public function test_calculate_payment_flow_with_zero_balance(): void
    {
        $service = new AdvancedSplitService();

        $memberShares = [
            ['customer_id' => 101, 'member_name' => 'A', 'split_weight' => 1.0, 'is_owner' => false, 'total_paid' => 1000, 'share_amount' => 1000, 'balance' => 0],
            ['customer_id' => 102, 'member_name' => 'B', 'split_weight' => 1.0, 'is_owner' => false, 'total_paid' => 500, 'share_amount' => 500, 'balance' => 0],
        ];

        $flow = $this->invokePrivate('calculatePaymentFlow', $service, [$memberShares]);

        $this->assertCount(0, $flow);
    }

    /**
     * 受取人が0人の場合（全員が支払い側）
     */
    public function test_calculate_payment_flow_with_no_receivers(): void
    {
        $service = new AdvancedSplitService();

        $memberShares = [
            ['customer_id' => 101, 'member_name' => 'A', 'split_weight' => 1.0, 'is_owner' => false, 'total_paid' => 100, 'share_amount' => 500, 'balance' => -400],
            ['customer_id' => 102, 'member_name' => 'B', 'split_weight' => 1.0, 'is_owner' => false, 'total_paid' => 0, 'share_amount' => 500, 'balance' => -500],
        ];

        $flow = $this->invokePrivate('calculatePaymentFlow', $service, [$memberShares]);

        // 受取人がいない場合は支払いフローが生成されない
        $this->assertCount(0, $flow);
    }

    /**
     * 支払い人が0人の場合（全員が受取側）
     */
    public function test_calculate_payment_flow_with_no_payers(): void
    {
        $service = new AdvancedSplitService();

        $memberShares = [
            ['customer_id' => 101, 'member_name' => 'A', 'split_weight' => 1.0, 'is_owner' => false, 'total_paid' => 1000, 'share_amount' => 500, 'balance' => 500],
            ['customer_id' => 102, 'member_name' => 'B', 'split_weight' => 1.0, 'is_owner' => false, 'total_paid' => 1000, 'share_amount' => 500, 'balance' => 500],
        ];

        $flow = $this->invokePrivate('calculatePaymentFlow', $service, [$memberShares]);

        // 支払い人がいない場合は支払いフローが生成されない
        $this->assertCount(0, $flow);
    }

    /**
     * balanceが1円の場合（最小の支払い金額）
     */
    public function test_calculate_payment_flow_with_one_yen_balance(): void
    {
        $service = new AdvancedSplitService();

        $memberShares = [
            ['customer_id' => 101, 'member_name' => 'A', 'split_weight' => 1.0, 'is_owner' => false, 'total_paid' => 1000, 'share_amount' => 999, 'balance' => 1],
            ['customer_id' => 102, 'member_name' => 'B', 'split_weight' => 1.0, 'is_owner' => false, 'total_paid' => 0, 'share_amount' => 1, 'balance' => -1],
        ];

        $flow = $this->invokePrivate('calculatePaymentFlow', $service, [$memberShares]);

        $this->assertCount(1, $flow);
        $this->assertSame(102, $flow[0]['from_customer_id']);
        $this->assertSame(101, $flow[0]['to_customer_id']);
        $this->assertSame(1, $flow[0]['amount']);
    }
}
