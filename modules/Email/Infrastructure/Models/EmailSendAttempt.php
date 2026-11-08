<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $attempt_number
 * @property string $outcome
 * @property string|null $error_code
 * @property int|null $duration_ms
 * @property Carbon $started_at
 */
final class EmailSendAttempt extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
