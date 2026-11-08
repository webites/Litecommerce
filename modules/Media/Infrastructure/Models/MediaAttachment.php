<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $media_id
 * @property string $owner_type
 * @property int $owner_id
 * @property string $collection
 * @property int $position
 */
final class MediaAttachment extends Model
{
    protected $fillable = ['media_id', 'owner_type', 'owner_id', 'collection', 'position'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['owner_id' => 'integer', 'position' => 'integer'];
    }
}
