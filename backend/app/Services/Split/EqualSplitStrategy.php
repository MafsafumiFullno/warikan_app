<?php

namespace App\Services\Split;

class EqualSplitStrategy implements SplitStrategyInterface
{
    /**
     * {@inheritdoc}
     */
    public function calculate(array $participants, array $options = []): array
    {
        $numParticipants = count($participants);
        $totalAmount = 0.0;

        foreach ($participants as $participant) {
            $totalAmount += (float) ($participant['total_paid'] ?? 0);
        }

        $equalShare = $numParticipants > 0 ? $totalAmount / $numParticipants : 0.0;

        $resultParticipants = [];
        foreach ($participants as $participant) {
            $paid = (float) ($participant['total_paid'] ?? 0);
            $balance = $paid - $equalShare; // +: 受取, -: 支払
            $resultParticipants[] = [
                'customer_id' => (int) $participant['customer_id'],
                'total_paid' => round($paid, 2),
                'share' => round($equalShare, 2),
                'balance' => round($balance, 2),
            ];
        }

        return [
            'total_amount' => round($totalAmount, 2),
            'per_participant' => $resultParticipants,
        ];
    }
}


