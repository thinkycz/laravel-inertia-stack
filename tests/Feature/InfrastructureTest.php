<?php

declare(strict_types=1);

use App\Jobs\PruneExpiredTokenJob;
use Database\Factories\UserFactory;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Redis;
use Thinkycz\LaravelCore\Support\Config;

\test('test databases are explicitly isolated and transactional', function (): void {
    $driver = DB::connection()->getDriverName();
    if ($driver === 'mysql') {
        \expect(DB::connection()->getDatabaseName())->toBe('starter_integration_tests');
        \expect(DB::selectOne('SELECT VERSION() AS version')->version)->toStartWith('8.');
    } else {
        \expect($driver)->toBe('sqlite');
        \expect(DB::connection()->getDatabaseName())->toBe(':memory:');
    }
    $count = DB::table('users')->count();
    DB::beginTransaction();
    UserFactory::new()->createOne();
    DB::rollBack();
    \expect(DB::table('users')->count())->toBe($count);
});

\test('isolated Redis supports cache locks and queued jobs', function (): void {
    if (DB::connection()->getDriverName() !== 'mysql') {
        \expect(Config::inject()->assertString('cache.default'))->toBe('array');

        return;
    }
    \expect(Config::inject()->assertString('database.redis.options.prefix'))->toBe('{starter_integration_tests}:');
    $cache = Cache::store('redis');
    $cache->put('probe', 'ready', 30);
    \expect($cache->get('probe'))->toBe('ready');
    $lock = $cache->lock('probe-lock', 10);
    \expect($lock->get())->toBeTrue();
    \expect($cache->lock('probe-lock', 10)->get())->toBeFalse();
    $lock->release();
    $cache->forget('probe');
    Queue::connection('redis')->push(new PruneExpiredTokenJob(0), '', 'starter-integration');
    $job = Queue::connection('redis')->pop('starter-integration');
    \expect($job)->not->toBeNull();
    $job->fire();
    $job->delete();
    \expect(Queue::connection('redis')->size('starter-integration'))->toBe(0);
    Redis::connection()->del('queues:starter-integration:notify');
});
