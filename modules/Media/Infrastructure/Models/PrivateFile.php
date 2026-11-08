<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $disk
 * @property string $path
 * @property string $original_filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $checksum_sha256
 * @property string $integrity_status
 * @property Carbon|null $verified_at
 * @property string|null $uploaded_by
 */
final class PrivateFile extends Model
{
    protected $table = 'private_files';

    protected $fillable = ['disk', 'path', 'original_filename', 'mime_type', 'size_bytes', 'checksum_sha256', 'integrity_status', 'verified_at', 'uploaded_by'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['size_bytes' => 'integer', 'verified_at' => 'datetime'];
    }
}
