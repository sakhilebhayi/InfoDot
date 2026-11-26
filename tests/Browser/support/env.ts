import fs from 'node:fs';
import path from 'node:path';

/** Where the harness lives and what the app under test is told. Nothing here is a secret. */
export const repoRoot = path.resolve(__dirname, '..', '..', '..');
export const browserRoot = path.resolve(__dirname, '..');
export const dataDir = path.join(browserRoot, '.data');
export const screensDir = path.join(browserRoot, 'screens');
export const databaseFile = path.join(dataDir, 'e2e.sqlite');
export const runFile = path.join(dataDir, 'run.json');
export const logFile = path.join(repoRoot, 'storage', 'logs', 'laravel.log');

export const port = Number(process.env.E2E_PORT ?? 8132);
export const baseURL = `http://127.0.0.1:${port}`;

/**
 * The environment every artisan call and the served app run with. It wins over the developer's .env, so the browser
 * run can only ever touch the scratch SQLite file in tests/Browser/.data, sends no mail and calls no other platform.
 */
export function appEnv(): NodeJS.ProcessEnv {
    return {
        ...process.env,
        ...blankedServiceSecrets(),
        ...registryAddresses(),
        APP_ENV: 'local',
        APP_DEBUG: 'false',
        APP_URL: baseURL,
        DB_CONNECTION: 'sqlite',
        DB_DATABASE: databaseFile,
        DB_URL: '',
        DATABASE_URL: '',
        MAIL_MAILER: 'log',
        LOG_CHANNEL: 'single',
        SESSION_DRIVER: 'file',
        CACHE_STORE: 'file',
        CACHE_DRIVER: 'file',
        QUEUE_CONNECTION: 'sync',
        SCOUT_DRIVER: 'collection',
        BROADCAST_CONNECTION: 'log',
        // The harness creates its people on reserved domains (infodot.test, example.com) that can receive no mail; the run's mailer only writes to the log.
        BLOCK_RESERVED_EMAIL_DOMAINS: 'false',
        FILESYSTEM_DISK: 'local',
        // No other platform is called from a browser run: with these empty the clients stay switched off.
        DOPEMINE_INGEST_SECRET: '',
        PARTICIPATION_ENABLED: 'false',
        PARTICIPATION_SECRET: '',
        KNOWLEDGE_SECRET_BRAIN: '',
        KNOWLEDGE_SECRET_INFODOT: '',
        LICENCE_FINANCE_CONNECTED: 'false',
        FINANCE_ENTITLEMENT_SECRET: '',
        SENTRY_LARAVEL_DSN: '',
        OPS_ALERT_ADDRESS: '',
    };
}

/** Any per-platform service secret a developer keeps in .env is blanked for the run, so nothing is signed or sent. */
function blankedServiceSecrets(): Record<string, string> {
    const file = path.join(repoRoot, '.env');
    if (!fs.existsSync(file)) {
        return {};
    }

    const blanks: Record<string, string> = {};
    for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
        const key = line.split('=')[0].trim();
        if (/^ECOSYSTEM_SECRET_[A-Z0-9_]+$/.test(key)) {
            blanks[key] = '';
        }
    }

    return blanks;
}

/**
 * R-72 (B-03): a developer's .env often points the platforms at local copies (http://localhost:...), which the launch
 * refuses as untrusted, so every platform journey answered 503. The run puts each one back on its own https address
 * under infodot.co.za; the browsers block those hosts, so nothing is ever sent there.
 */
function registryAddresses(): Record<string, string> {
    const file = path.join(repoRoot, '.env');
    if (!fs.existsSync(file)) {
        return {};
    }

    const addresses: Record<string, string> = {};
    for (const line of fs.readFileSync(file, 'utf8').split(/\r?\n/)) {
        const match = line.split('=')[0].trim().match(/^DOT_([A-Z0-9]+)_URL$/);
        if (match) {
            addresses[match[0]] = `https://${match[1].toLowerCase()}.infodot.co.za`;
        }
    }

    return { ...addresses, ECOSYSTEM_TRUSTED_DOMAINS: 'infodot.co.za', ECOSYSTEM_ALLOW_INSECURE_HANDOFF: 'false' };
}
