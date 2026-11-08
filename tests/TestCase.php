<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Litecommerce\Modules\Updates\Contracts\ReleaseSource;
use Tests\Doubles\OfflineReleaseSource;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app->bind(ReleaseSource::class, OfflineReleaseSource::class);
    }
}
