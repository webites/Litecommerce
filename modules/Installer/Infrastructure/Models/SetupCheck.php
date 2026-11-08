<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Installer\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Litecommerce\Modules\Installer\Domain\SetupCheckStatus;

/**
 * @property int $id
 * @property string $step_key
 * @property SetupCheckStatus $status
 * @property Carbon|null $completed_at
 * @property array|null $meta
 */
final class SetupCheck extends Model
{
    protected $table = 'setup_checks';

    protected $fillable = ['step_key', 'status', 'completed_at', 'meta'];

    protected $casts = [
        'status' => SetupCheckStatus::class,
        'completed_at' => 'datetime',
        'meta' => 'array',
    ];
}
