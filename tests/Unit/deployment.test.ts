import { afterEach, describe, expect, it } from 'vitest';
import { execFileSync } from 'node:child_process';
import {
    mkdtempSync,
    mkdirSync,
    readFileSync,
    rmSync,
    writeFileSync,
    copyFileSync,
} from 'node:fs';
import { tmpdir } from 'node:os';
import { join } from 'node:path';

const directories: string[] = [];
afterEach(() => {
    for (const directory of directories.splice(0))
        rmSync(directory, { recursive: true, force: true });
});

function deploymentFixture(withManifest: boolean) {
    const directory = mkdtempSync(join(tmpdir(), 'starter-deployment-test-'));
    directories.push(directory);
    copyFileSync('Makefile', join(directory, 'Makefile'));
    mkdirSync(join(directory, 'bin'));
    const script = `#!/bin/sh
set -eu
name=$(basename "$0")
printf '%s %s\\n' "$name" "$*" >> calls.log
if [ "$name $*" = 'npm run build' ]; then
    ${withManifest ? 'mkdir -p public/build; echo "{}" > public/build/manifest.json' : ':'}
fi
if [ "$name $*" = 'npm prune --omit=dev' ]; then
    test -f public/build/manifest.json
fi
`;
    for (const name of ['npm', 'composer', 'artisan'])
        writeFileSync(join(directory, 'bin', name), script, { mode: 0o755 });
    return directory;
}

function runDeployment(directory: string) {
    return execFileSync(
        'make',
        [
            '--old-file=.env',
            'production',
            `MAKE_COMPOSER=${join(directory, 'bin/composer')}`,
            `MAKE_ARTISAN=${join(directory, 'bin/artisan')}`,
        ],
        {
            cwd: directory,
            env: { PATH: `${join(directory, 'bin')}:${process.env.PATH}` },
            stdio: 'pipe',
        },
    );
}

describe('deployment workflow in a disposable directory', () => {
    it('builds the manifest with locked build dependencies before removing them', () => {
        const directory = deploymentFixture(true);
        runDeployment(directory);
        const calls = readFileSync(join(directory, 'calls.log'), 'utf8')
            .trim()
            .split('\n');
        expect(calls.indexOf('npm ci --include=dev')).toBeGreaterThan(-1);
        expect(calls.indexOf('npm run build')).toBeGreaterThan(
            calls.indexOf('npm ci --include=dev'),
        );
        expect(calls.indexOf('composer install -a --no-dev')).toBeGreaterThan(
            calls.indexOf('npm run build'),
        );
        expect(calls.indexOf('npm prune --omit=dev')).toBeGreaterThan(
            calls.indexOf('npm run build'),
        );
        expect(calls.at(-1)).toBe('artisan up');
    });

    it('stops before pruning or publishing if the build leaves no manifest', () => {
        const directory = deploymentFixture(false);
        expect(() => runDeployment(directory)).toThrow();
        const calls = readFileSync(join(directory, 'calls.log'), 'utf8');
        expect(calls).not.toContain('npm prune');
        expect(calls).not.toContain('artisan up');
    });
});
