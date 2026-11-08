<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Payments\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A provider notification accepted while maintenance was on; body and headers are encrypted at rest.
 *
 * @property string $id
 * @property string $method_id
 * @property string $body
 * @property array<string, string> $headers
 * @property string $status
 * @property int $attempts
 */
final class DeferredPaymentWebhook extends Model
{
    public const PENDING = 'pending';

    public const PROCESSING = 'processing';

    public const PROCESSED = 'processed';

    public const REJECTED = 'rejected';

    public const FAILED = 'failed';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'body' => 'encrypted',
            'headers' => 'encrypted:array',
            'received_at' => 'datetime',
            'claimed_at' => 'datetime',
            'finished_at' => 'datetime',
        ];
    }
}
