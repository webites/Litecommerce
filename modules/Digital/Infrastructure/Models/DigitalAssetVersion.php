<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Digital\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $variant_file_set_id
 * @property int $version_number
 * @property int $private_file_id
 * @property string $original_filename
 * @property int $size_bytes
 * @property string|null $uploaded_by
 */
final class DigitalAssetVersion extends Model
{
    protected $fillable = ['variant_file_set_id', 'version_number', 'private_file_id', 'original_filename', 'size_bytes', 'uploaded_by'];

    protected function casts(): array
    {
        return ['size_bytes' => 'integer'];
    }

    /** @return BelongsTo<VariantFileSet, $this> */
    public function fileSet(): BelongsTo
    {
        return $this->belongsTo(VariantFileSet::class, 'variant_file_set_id');
    }
}
