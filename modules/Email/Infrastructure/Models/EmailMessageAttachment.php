<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Email\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $disk
 * @property string $path
 * @property string $filename
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $checksum_sha256
 */
final class EmailMessageAttachment extends Model
{
    protected $guarded = [];
}
