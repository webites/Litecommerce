<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $disk
 * @property string $path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property int|null $width
 * @property int|null $height
 * @property array<int, string>|null $variants width => storage path, generated raster thumbnails only (never the original)
 * @property string|null $alt_text
 * @property string|null $uploaded_by
 */
final class Media extends Model
{
    protected $table = 'media';

    protected $fillable = ['disk', 'path', 'original_filename', 'mime_type', 'size_bytes', 'width', 'height', 'variants', 'alt_text', 'uploaded_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'width' => 'integer', 'height' => 'integer', 'variants' => 'array'];
    }
}
