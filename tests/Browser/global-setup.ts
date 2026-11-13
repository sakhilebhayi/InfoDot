import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { artisan, op } from './support/app';
import { demo, seededAccounts } from './support/pages';
import { dataDir, databaseFile, repoRoot, runFile, screensDir } from './support/env';

/**
 * Before any browser opens: an empty scratch database, the demonstration baseline, a password made for this run
 * and the pictures the tests upload. Everything goes through artisan, with the harness environment (support/env.ts).
 */
export default async function globalSetup(): Promise<void> {
    if (!fs.existsSync(path.join(repoRoot, 'public', 'build', 'manifest.json'))) {
        throw new Error('public/build is missing: run `npm run build` before the browser tests.');
    }

    fs.mkdirSync(dataDir, { recursive: true });
    fs.mkdirSync(screensDir, { recursive: true });
    for (const stale of [databaseFile, `${databaseFile}-journal`, `${databaseFile}-wal`, `${databaseFile}-shm`]) {
        fs.rmSync(stale, { force: true });
    }
    fs.rmSync(path.join(dataDir, 'a11y'), { recursive: true, force: true });
    fs.writeFileSync(databaseFile, '');

    // Generated here, kept only in the ignored .data folder, never written into a spec.
    const password = `E2e-${crypto.randomBytes(12).toString('base64url')}-9a`;
    fs.writeFileSync(runFile, JSON.stringify({ password, tag: crypto.randomBytes(3).toString('hex') }));

    op({ op: 'write-ahead-log' });
    artisan(['migrate:fresh', '--force', '--seed']);
    artisan(['cache:clear']);
    artisan(['view:clear']);

    // The labelled demonstration content the owner shows (docs/programme/r49/DEMONSTRATION_CONTENT.md), with its moderator.
    // Its accounts get one-time passwords that are only printed; the run gives them its own instead.
    artisan(['demo:install', '--with-moderator']);
    op({ op: 'reset-passwords', emails: [...seededAccounts, ...Object.values(demo)] });
    op({ op: 'fixtures', dir: path.join(dataDir, 'fixtures') });
}
