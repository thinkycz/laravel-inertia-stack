import { spawn, spawnSync } from 'node:child_process';
import { randomBytes } from 'node:crypto';
import { mkdtemp, rm } from 'node:fs/promises';
import { tmpdir } from 'node:os';
import { join } from 'node:path';
import net from 'node:net';

const suffix = randomBytes(6).toString('hex');
const container = `starter-test-${suffix}`;
const database = `starter_test_${suffix}`;
const directory = await mkdtemp(join(tmpdir(), 'starter-services-'));
const run = (command, args) => {
    const result = spawnSync(command, args, { encoding: 'utf8' });
    if (result.status !== 0) throw new Error(result.stderr || result.stdout);
    return result.stdout.trim();
};
let redis;
try {
    run('docker', [
        'run',
        '--detach',
        '--rm',
        '--name',
        container,
        '-p',
        '127.0.0.1::3306',
        '-e',
        'MYSQL_ROOT_PASSWORD=disposable-test-password',
        '-e',
        `MYSQL_DATABASE=${database}`,
        'mysql:8.4',
    ]);
    const mysqlPort = run('docker', ['port', container, '3306/tcp'])
        .split(':')
        .at(-1);
    const socket = net.createServer();
    await new Promise((resolve) => socket.listen(0, '127.0.0.1', resolve));
    const redisPort = socket.address().port;
    await new Promise((resolve) => socket.close(resolve));
    redis = spawn(
        'redis-server',
        [
            '--port',
            String(redisPort),
            '--bind',
            '127.0.0.1',
            '--save',
            '',
            '--appendonly',
            'no',
            '--dir',
            directory,
        ],
        { stdio: 'ignore' },
    );
    let ready = false;
    for (let attempt = 0; attempt < 60; attempt++) {
        const result = spawnSync(
            'docker',
            [
                'exec',
                container,
                'mysqladmin',
                'ping',
                '-h',
                '127.0.0.1',
                '-pdisposable-test-password',
            ],
            { stdio: 'ignore' },
        );
        if (result.status === 0) {
            ready = true;
            break;
        }
        await new Promise((resolve) => setTimeout(resolve, 1000));
    }
    if (!ready) throw new Error('Disposable MySQL did not become ready.');
    const test = spawn(
        'php',
        [
            'vendor/bin/pest',
            '-c',
            'tests/Integration/phpunit.xml',
            '--no-coverage',
            '--compact',
        ],
        {
            stdio: 'inherit',
            env: {
                ...process.env,
                STARTER_TEST_DATABASE: database,
                STARTER_TEST_MYSQL_PORT: mysqlPort,
                STARTER_TEST_REDIS_PORT: String(redisPort),
            },
        },
    );
    process.exitCode = await new Promise((resolve) => test.on('exit', resolve));
} finally {
    if (redis && redis.exitCode === null) {
        redis.kill('SIGTERM');
        await new Promise((resolve) => redis.on('exit', resolve));
    }
    spawnSync('docker', ['rm', '--force', container], { stdio: 'ignore' });
    await rm(directory, { recursive: true, force: true });
}
