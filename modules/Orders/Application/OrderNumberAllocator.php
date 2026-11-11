<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use Illuminate\Support\Facades\DB;
use Litecommerce\Modules\Orders\Contracts\OrderSettingKeys;
use Litecommerce\Modules\Store\Contracts\StoreSettingsStore;

/**
 * Hands out the next human-readable order number. Call it as late as possible in
 * the placement transaction: the counter row stays locked until commit, and a
 * rolled-back order does not consume a number.
 */
final class OrderNumberAllocator
{
    public const FIRST_NUMBER = 1001;

    public function __construct(private readonly StoreSettingsStore $settings) {}

    /** @return array{number: int, label: string} */
    public function next(): array
    {
        $number = (int) DB::table('order_number_counters')->where('id', 1)->lockForUpdate()->value('next_number');
        DB::table('order_number_counters')->where('id', 1)->update(['next_number' => $number + 1]);

        return ['number' => $number, 'label' => $this->prefix().$number];
    }

    public function prefix(): string
    {
        $prefix = $this->settings->get(OrderSettingKeys::NumberPrefix, '');

        return is_string($prefix) && preg_match(OrderSettingKeys::NumberPrefixPattern, $prefix) === 1 ? $prefix : '';
    }

    public function peekNextNumber(): int
    {
        return (int) (DB::table('order_number_counters')->where('id', 1)->value('next_number') ?? self::FIRST_NUMBER);
    }
}
