import path from 'node:path';
import { defineConfig, devices } from '@playwright/test';
import { appEnv, baseURL, browserRoot, port, repoRoot } from './support/env';

const everything = /.*\.spec\.ts/;
const crossEngine = /(a-new-user|b-business|d-permissions|g-failure-paths)\.spec\.ts/;
const handheld = /e-mobile\.spec\.ts/;

export default defineConfig({
    testDir: path.join(browserRoot, 'specs'),
    outputDir: path.join(browserRoot, 'test-results'),
    globalSetup: path.join(browserRoot, 'global-setup.ts'),
    globalTeardown: path.join(browserRoot, 'global-teardown.ts'),
    fullyParallel: false,
    workers: Number(process.env.E2E_WORKERS ?? 3),
    retries: 0,
    timeout: 120_000,
    expect: { timeout: 10_000 },
    reporter: [['list'], ['html', { outputFolder: path.join(browserRoot, 'playwright-report'), open: 'never' }], ['json', { outputFile: path.join(browserRoot, 'test-results', 'results.json') }]],
    use: {
        baseURL,
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
        actionTimeout: 15_000,
        navigationTimeout: 30_000,
    },
    webServer: {
        // --no-reload lets the built-in server run several workers and pass the harness environment through.
        command: `php artisan serve --host=127.0.0.1 --port=${port} --no-reload`,
        cwd: repoRoot,
        url: `${baseURL}/up`,
        reuseExistingServer: false,
        timeout: 60_000,
        env: {
            ...(appEnv() as Record<string, string>),
            PHP_CLI_SERVER_WORKERS: '6',
            PHP_INI_SCAN_DIR: `${process.env.PHP_INI_SCAN_DIR ?? ''}${path.delimiter}${path.join(browserRoot, 'support', 'php')}`,
        },
    },
    projects: [
        { name: 'desktop-chromium', testMatch: everything, testIgnore: handheld, use: { ...devices['Desktop Chrome'], viewport: { width: 1440, height: 900 } } },
        { name: 'desktop-firefox', testMatch: crossEngine, use: { ...devices['Desktop Firefox'], viewport: { width: 1440, height: 900 } } },
        { name: 'desktop-webkit', testMatch: crossEngine, use: { ...devices['Desktop Safari'], viewport: { width: 1440, height: 900 } } },
        { name: 'phone-webkit', testMatch: handheld, use: { ...devices['iPhone 14'] } },
        { name: 'phone-chromium', testMatch: handheld, use: { ...devices['Pixel 7'] } },
        { name: 'tablet', testMatch: handheld, use: { ...devices['iPad (gen 7)'] } },
    ],
});
