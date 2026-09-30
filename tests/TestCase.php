<?php

declare(strict_types=1);

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    /**
     * @inheritDoc
     */
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    /**
     * Boot tests independently of local credentials and configuration caches.
     */
    public function createApplication(): Application
    {
        $app = require __DIR__ . '/../bootstrap/app.php';
        $app->useEnvironmentPath(__DIR__);
        $app->loadEnvironmentFrom('environment.env');
        $this->traitsUsedByTest = \class_uses_recursive(static::class);
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Headers for an Inertia JSON page request.
     *
     * @return array<string, string>
     */
    protected function inertiaHeaders(): array
    {
        $manifest = \public_path('build/manifest.json');
        $version = \is_file($manifest) ? \hash_file('xxh128', $manifest) : null;

        return [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => \is_string($version) ? $version : '',
        ];
    }
}
