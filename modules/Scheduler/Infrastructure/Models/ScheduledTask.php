<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Scheduler\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $key
 * @property int $interval_seconds
 * @property string $status
 * @property Carbon $next_run_at
 * @property string|null $lease_token
 * @property Carbon|null $leased_until
 * @property int $run_count
 * @property int $consecutive_failures
 * @property Carbon|null $last_finished_at
 * @property Carbon|null $last_succeeded_at
 * @property Carbon|null $last_failed_at
 * @property string|null $last_error_code
 */
final class ScheduledTask extends Model
{
    protected $primaryKey = 'key';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'interval_seconds' => 'integer',
            'next_run_at' => 'datetime',
            'leased_until' => 'datetime',
            'last_started_at' => 'datetime',
            'last_finished_at' => 'datetime',
            'last_succeeded_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'run_count' => 'integer',
            'consecutive_failures' => 'integer',
        ];
    }
}
