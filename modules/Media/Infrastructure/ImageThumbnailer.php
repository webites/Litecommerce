<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Infrastructure;

use GdImage;

/**
 * Pure GD (no Composer dependency — GD is a required PHP extension already,
 * docs/knowledge-base/10-operations.md#wymagania-środowiskowe, precisely for
 * this). Never upscales: a variant is only generated for a target width
 * strictly smaller than the source's own width. Unsupported/corrupt input
 * (including vector formats GD can't rasterize, e.g. SVG), and an original
 * too large for the current memory_limit to decode safely, all yield no
 * variants rather than throwing/fataling — a thumbnail failure must never
 * block an otherwise-valid upload.
 */
final class ImageThumbnailer
{
    /** @var list<int> */
    private const WIDTHS = [320, 640, 960, 1280];

    /**
     * @return array{0: int, 1: int, 2: array<int, string>}|null [width, height, width => encoded binary contents], or null when the contents aren't a rasterizable image
     */
    public function process(string $contents, string $mimeType): ?array
    {
        $info = @getimagesizefromstring($contents);

        // Cheap (header-only, unlike imagecreatefromstring()) — refuse to even attempt a
        // decode we already know would blow the request's memory budget. A shared host's
        // default memory_limit is a hard constraint here (docs/knowledge-base/10-operations.md),
        // and GD holds the full decoded pixel buffer in memory for the whole operation.
        if ($info === false || ! $this->fitsMemoryBudget((int) $info[0], (int) $info[1])) {
            return null;
        }

        $source = @imagecreatefromstring($contents);

        if ($source === false) {
            return null;
        }

        try {
            $originalWidth = imagesx($source);
            $originalHeight = imagesy($source);
            $variants = [];

            imagealphablending($source, false);
            imagesavealpha($source, true);

            foreach (self::WIDTHS as $width) {
                if ($width >= $originalWidth) {
                    continue;
                }

                $height = max(1, (int) round($originalHeight * ($width / $originalWidth)));
                $resized = imagecreatetruecolor($width, $height);
                imagealphablending($resized, false);
                imagesavealpha($resized, true);
                $transparent = imagecolorallocatealpha($resized, 0, 0, 0, 127);
                imagefilledrectangle($resized, 0, 0, $width, $height, $transparent);

                if (! imagecopyresampled($resized, $source, 0, 0, 0, 0, $width, $height, $originalWidth, $originalHeight)) {
                    imagedestroy($resized);

                    continue;
                }

                try {
                    $encoded = $this->encode($resized, $mimeType);

                    if ($encoded !== null) {
                        $variants[$width] = $encoded;
                    }
                } finally {
                    imagedestroy($resized);
                }
            }

            return [$originalWidth, $originalHeight, $variants];
        } finally {
            imagedestroy($source);
        }
    }

    /**
     * GD needs the full decoded truecolor buffer (~4 bytes/pixel) resident for the whole
     * operation, plus working space while resizing/re-encoding — an out-of-memory fatal
     * error is NOT catchable in PHP, so this must be ruled out ahead of time, not caught.
     * The x2 multiplier is a deliberately generous margin for that working space, the
     * same order of magnitude WordPress's own memory-raising logic budgets for image work.
     */
    private function fitsMemoryBudget(int $width, int $height): bool
    {
        $limit = $this->memoryLimitBytes();

        if ($limit === null) {
            return true;
        }

        $estimatedBytes = $width * $height * 4 * 2;

        return memory_get_usage(true) + $estimatedBytes < $limit;
    }

    private function memoryLimitBytes(): ?int
    {
        $configured = trim((string) ini_get('memory_limit'));

        if ($configured === '' || $configured === '-1') {
            return null;
        }

        $unit = strtolower(substr($configured, -1));
        $value = (int) $configured;

        return match ($unit) {
            'g' => $value * 1024 * 1024 * 1024,
            'm' => $value * 1024 * 1024,
            'k' => $value * 1024,
            default => $value,
        };
    }

    private function encode(GdImage $image, string $mimeType): ?string
    {
        ob_start();

        $ok = match ($mimeType) {
            'image/jpeg' => imagejpeg($image, quality: 85),
            'image/png' => imagepng($image, quality: 6),
            'image/gif' => imagegif($image),
            'image/webp' => function_exists('imagewebp') && imagewebp($image, quality: 85),
            default => false,
        };

        $contents = ob_get_clean();

        return $ok && is_string($contents) ? $contents : null;
    }
}
