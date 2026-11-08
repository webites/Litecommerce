<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Application;

use Litecommerce\Modules\DemoData\Contracts\DemoDataContent;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContext;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContributor;
use Litecommerce\Modules\DemoData\Contracts\DemoDataResourceRegistry;
use Litecommerce\Modules\Media\Contracts\MediaLibrary;

final readonly class MediaDemoDataContributor implements DemoDataContributor
{
    private const ASSETS = ['ceramic-mug', 'linen-apron', 'oak-tray', 'desk-lamp', 'notebook', 'soy-candle'];

    public function __construct(private MediaLibrary $media, private DemoDataContent $demoContent) {}

    public function key(): string
    {
        return 'media';
    }

    public function priority(): int
    {
        return 50;
    }

    public function install(DemoDataContext $context, DemoDataResourceRegistry $resources): void
    {
        $altTexts = $this->demoContent->load('media::demo', $context->locale)['alt'];

        foreach (self::ASSETS as $asset) {
            $path = base_path("resources/demo/images/{$asset}.jpg");
            $contents = file_get_contents($path);
            if ($contents === false) {
                throw new \RuntimeException("Nie można odczytać obrazu demonstracyjnego: {$asset}.jpg");
            }

            $stored = $this->media->store($contents, "{$asset}.jpg", 'image/jpeg', $altTexts[$asset]);
            $resources->record($context->batchId, $this->key(), "asset.{$asset}", $stored->id);
        }
    }

    public function remove(string $batchId, DemoDataResourceRegistry $resources): void
    {
        foreach (self::ASSETS as $asset) {
            foreach ($resources->ids($batchId, $this->key(), "asset.{$asset}") as $mediaId) {
                $this->media->delete((int) $mediaId);
            }
        }
    }
}
