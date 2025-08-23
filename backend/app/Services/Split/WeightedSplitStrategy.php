<?php

namespace App\Services\Split;

class WeightedSplitStrategy implements SplitStrategyInterface
{
    /**
     * options expects:
     * - weights: array<int, float> keyed by customer_id
     */
    public function calculate(array $participants, array $options = []): array
    {
        $weights = $options['weights'] ?? [];

        $totalAmount = 0.0;
        foreach ($participants as $participant) {
            $totalAmount += (float) ($participant['total_paid'] ?? 0);
        }

        // Sum weights only for included participants
        $sumWeights = 0.0;
        foreach ($participants as $participant) {
            $cid = (int) $participant['customer_id'];
            $sumWeights += isset($weights[$cid]) ? (float) $weights[$cid] : 0.0;
        }

        if ($sumWeights <= 0) {
            // fallback to equal split
            $equal = new EqualSplitStrategy();
            return $equal->calculate($participants, $options);
        }

        $resultParticipants = [];
        foreach ($participants as $participant) {
            $cid = (int) $participant['customer_id'];
            $paid = (float) ($participant['total_paid'] ?? 0);
            $weight = isset($weights[$cid]) ? (float) $weights[$cid] : 0.0;
            $share = $weight > 0 ? ($totalAmount * $weight / $sumWeights) : 0.0;
            $balance = $paid - $share; // +: 受取, -: 支払
            $resultParticipants[] = [
                'customer_id' => $cid,
                'total_paid' => round($paid, 2),
                'share' => round($share, 2),
                'balance' => round($balance, 2),
            ];
        }

        return [
            'total_amount' => round($totalAmount, 2),
            'per_participant' => $resultParticipants,
        ];
    }
}


