<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $access_token
 * @property int $variant_file_set_id
 * @property int $digital_asset_version_id
 * @property string $reference_type
 * @property string $reference_id
 * @property string|null $order_id
 * @property int|null $customer_account_id
 * @property string|null $email
 * @property Carbon $granted_at
 * @property Carbon|null $expires_at
 * @property int|null $max_downloads
 * @property int $downloads_used
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_reason
 */
final class DownloadGrant extends Model
{
    protected $guarded = [];

    /** @return BelongsTo<VariantFileSet, $this> */
    public function fileSet(): BelongsTo
    {
        return $this->belongsTo(VariantFileSet::class, 'variant_file_set_id');
    }

    /** @return BelongsTo<DigitalAssetVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(DigitalAssetVersion::class, 'digital_asset_version_id');
    }

    protected function casts(): array
    {
        return [
            'granted_at' => 'datetime', 'expires_at' => 'datetime', 'revoked_at' => 'datetime',
        ];
    }
}
