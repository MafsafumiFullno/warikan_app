<?php

namespace App\Services\Split;

interface SplitStrategyInterface
{
    /**
     * @param array<int, array{customer_id:int,total_paid:float}> $participants
     * @param array<string,mixed> $options
     * @return array{
     *   total_amount: float,
     *   per_participant: array<int, array{customer_id:int,total_paid:float,share:float,balance:float}>
     * }
     */
    public function calculate(array $participants, array $options = []): array;
}


