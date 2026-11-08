<?php

declare(strict_types=1);

namespace App\Support\Privacy;

/** Personal data of an order is kept to the end of its year plus the retention years (the accounting period). */
final class RetentionCutoff
{
    public static function orderData(\DateTimeInterface $now, ?int $years = null): \DateTimeImmutable
    {
        $years ??= (int) config('privacy.order_data_retention_years', 5);

        return new \DateTimeImmutable(sprintf('%d-01-01 00:00:00', (int) $now->format('Y') - $years));
    }
}
