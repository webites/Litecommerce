<?php

declare(strict_types=1);

namespace Litecommerce\Modules\Media\Providers;

use Illuminate\Support\ServiceProvider;
use Litecommerce\Modules\DemoData\Contracts\DemoDataContributor;
use Litecommerce\Modules\Media\Application\MediaDemoDataContributor;
use Litecommerce\Modules\Media\Application\VerifyPrivateFiles;
use Litecommerce\Modules\Media\Console\MigrateStorageCommand;
use Litecommerce\Modules\Media\Contracts\MediaAttachments;
use Litecommerce\Modules\Media\Contracts\MediaLibrary;
use Litecommerce\Modules\Media\Contracts\PrivateFileLibrary;
use Litecommerce\Modules\Media\Contracts\PublicStorageLinker;
use Litecommerce\Modules\Media\Infrastructure\FilesystemPublicStorageLinker;
use Litecommerce\Modules\Media\Infrastructure\Repositories\EloquentMediaAttachments;
use Litecommerce\Modules\Media\Infrastructure\Repositories\EloquentMediaLibrary;
use Litecommerce\Modules\Media\Infrastructure\Repositories\EloquentPrivateFileLibrary;
use Litecommerce\Modules\Scheduler\Contracts\ScheduledTaskRegistry;

final class MediaServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(MediaLibrary::class, EloquentMediaLibrary::class);
        $this->app->bind(MediaAttachments::class, EloquentMediaAttachments::class);
        $this->app->bind(PublicStorageLinker::class, FilesystemPublicStorageLinker::class);
        $this->app->bind(PrivateFileLibrary::class, EloquentPrivateFileLibrary::class);
        $this->app->singleton(MediaDemoDataContributor::class);
        $this->commands([MigrateStorageCommand::class]);
        $this->app->tag(MediaDemoDataContributor::class, DemoDataContributor::class);
    }

    public function boot(): void
    {
        $this->loadTranslationsFrom(__DIR__.'/../lang', 'media');

        $this->app->make(ScheduledTaskRegistry::class)->register(
            'core.media.verify_private_files',
            3600,
            fn () => $this->app->make(VerifyPrivateFiles::class)->run((int) config('media.verify_batch', 10)),
        );
    }
}
