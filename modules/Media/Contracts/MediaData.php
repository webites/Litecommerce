<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Contracts;

final readonly class MediaData
{
    /** @param array<int, string> $variants width => resolved URL, generated raster thumbnails only (smaller than $width, never includes the original) */
    public function __construct(
        public int $id,
        public string $path,
        public ?string $url,
        public string $originalFilename,
        public string $mimeType,
        public int $sizeBytes,
        public ?int $width,
        public ?int $height,
        public array $variants,
        public ?string $altText,
    ) {}

    /**
     * A `srcset` attribute value listing every generated thumbnail plus the
     * original as its own largest candidate (only when both its URL and
     * pixel width are known) — null when there's nothing to offer (no
     * dimensions, e.g. a non-raster file) beyond the plain `src` itself.
     */
    public function srcset(): ?string
    {
        $candidates = $this->variants;

        if ($this->url !== null && $this->width !== null) {
            $candidates[$this->width] = $this->url;
        }

        if ($candidates === []) {
            return null;
        }

        ksort($candidates);

        $parts = [];
        foreach ($candidates as $width => $url) {
            $parts[] = "{$url} {$width}w";
        }

        return implode(', ', $parts);
    }
}
