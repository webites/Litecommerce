<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Consents\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $marketing_consent_id
 * @property string $action
 * @property string $source
 * @property string $locale
 * @property string|null $content_version
 * @property string|null $content_text
 * @property Carbon $occurred_at
 */
final class MarketingConsentEvent extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
