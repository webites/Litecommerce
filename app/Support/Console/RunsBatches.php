<?php

declare(strict_types=1);

namespace App\Support\Console;

trait RunsBatches
{
    /**
     * Repeats a step until it handles fewer items than a full batch or the
     * per-run limit is reached, so one scheduler run stays bounded.
     *
     * @param  callable(int): int  $step  receives the batch size, returns the number of items handled
     */
    private function runBatches(callable $step, int $batchSize, int $maxBatches): int
    {
        $total = 0;

        for ($batch = 0; $batch < $maxBatches; $batch++) {
            $count = $step($batchSize);
            $total += $count;

            if ($count < $batchSize) {
                break;
            }
        }

        return $total;
    }
}
