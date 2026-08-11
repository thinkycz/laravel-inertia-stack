<?php

declare(strict_types=1);

namespace Tests;

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
