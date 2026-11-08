<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Consents\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $email
 * @property string $status
 * @property string $unsubscribe_token
 * @property string $locale
 * @property Carbon|null $granted_at
 * @property Carbon|null $withdrawn_at
 */
final class MarketingConsent extends Model
{
    public const GRANTED = 'granted';

    public const WITHDRAWN = 'withdrawn';

    protected $guarded = [];

    /** @return HasMany<MarketingConsentEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(MarketingConsentEvent::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['granted_at' => 'datetime', 'withdrawn_at' => 'datetime'];
    }
}
