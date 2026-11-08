<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Audit\Infrastructure\Models;

use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Model;

#[Guarded([])]
final class AuditEntry extends Model
{
    public const UPDATED_AT = null;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'changes' => 'array',
            'metadata' => 'array',
        ];
    }
}
