<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Orders\Application;

use Litecommerce\Modules\Orders\Infrastructure\Models\GuestOrderAccessToken;

final class PruneGuestOrderTokens
{
    public function pruneBatch(\DateTimeImmutable $now, int $batchSize): int
    {
        $ids = GuestOrderAccessToken::query()->where('expires_at', '<', $now)->orderBy('id')->limit($batchSize)->pluck('id')->all();

        return $ids === [] ? 0 : GuestOrderAccessToken::query()->whereKey($ids)->delete();
    }
}
