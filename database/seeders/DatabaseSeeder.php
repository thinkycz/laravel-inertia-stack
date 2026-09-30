<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use RuntimeException;
use Thinkycz\LaravelCore\Support\Config;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        if (!Config::inject()->appEnvIs(['local'])) {
            throw new RuntimeException('Demo seeding is only available in the local environment.');
        }

        $this->callOnce(UserSeeder::class);
    }
}
