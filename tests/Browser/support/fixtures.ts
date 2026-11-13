import { test as base, expect, type Browser, type BrowserContext, type BrowserContextOptions, type Page, type TestInfo } from '@playwright/test';
import crypto from 'node:crypto';
import fs from 'node:fs';
import path from 'node:path';
import { op, run, unique, verificationLink } from './app';
import { baseURL, dataDir, screensDir } from './env';

type Stored = { status: number; headers: Record<string, string>; body: Buffer };

/**
 * Fonts and the sign-in photograph come from other hosts. Every new browser context starts with an empty cache, and
 * those hosts can take seconds to answer, so each file is fetched once, kept in .data/elsewhere and replayed from there
 * (per browser engine: the font stylesheet differs between them). Delete that folder to fetch them afresh.
 */
const elsewhere = new Map<string, Stored>();
const elsewhereDir = path.join(dataDir, 'elsewhere');

function kept(engine: string, address: string): Stored | undefined {
    const key = `${engine}:${address}`;
    const inMemory = elsewhere.get(key);
    if (inMemory) {
        return inMemory;
    }

    const file = path.join(elsewhereDir, `${engine}-${crypto.createHash('sha1').update(address).digest('hex')}`);
    if (!fs.existsSync(`${file}.json`) || !fs.existsSync(`${file}.body`)) {
        return undefined;
    }
    const meta = JSON.parse(fs.readFileSync(`${file}.json`, 'utf8'));
    const stored = { status: meta.status, headers: meta.headers, body: fs.readFileSync(`${file}.body`) };
    elsewhere.set(key, stored);

    return stored;
}

function keep(engine: string, address: string, stored: Stored): void {
    elsewhere.set(`${engine}:${address}`, stored);
    fs.mkdirSync(elsewhereDir, { recursive: true });
    const file = path.join(elsewhereDir, `${engine}-${crypto.createHash('sha1').update(address).digest('hex')}`);
    // Written beside and renamed, so a second worker never reads half a file.
    const part = `${file}.${process.pid}.part`;
    fs.writeFileSync(part, stored.body);
    fs.renameSync(part, `${file}.body`);
    fs.writeFileSync(part, JSON.stringify({ address, status: stored.status, headers: stored.headers }));
    fs.renameSync(part, `${file}.json`);
}

/** Block the live Dot hosts and replay third-party files, on one browser context. */
async function guard(context: BrowserContext, engine: string): Promise<void> {
    await context.route((url) => /^https?:$/.test(url.protocol) && !url.href.startsWith(baseURL), async (route) => {
        const request = route.request();
        if (/(^|\.)infodot\.co\.za$/i.test(new URL(request.url()).hostname)) {
            return route.abort('blockedbyclient');
        }
        if (request.method() !== 'GET') {
            return route.continue();
        }

        let stored = kept(engine, request.url());
        if (!stored) {
            try {
                const response = await route.fetch({ timeout: 60_000 });
                stored = { status: response.status(), headers: response.headers(), body: await response.body() };
                if (response.ok()) {
                    keep(engine, request.url(), stored);
                }
            } catch {
                return route.abort('failed').catch(() => undefined);
            }
        }

        return route.fulfill({ status: stored.status, headers: stored.headers, body: stored.body }).catch(() => undefined);
    });
}

/**
 * The shared test object. Every browser context refuses to talk to the live Dot hosts, so a stray link to another
 * platform can never reach production from a test run.
 */
export const test = base.extend({
    context: async ({ context, browserName }, use) => {
        await guard(context, browserName);
        await use(context);
    },
});

/** A second person's browser inside one test: use this, never browser.newContext(), so it is guarded the same way. */
export async function openContext(browser: Browser, options: BrowserContextOptions = {}): Promise<BrowserContext> {
    const context = await browser.newContext({ baseURL, ...options });
    await guard(context, browser.browserType().name());

    return context;
}

export { expect };

export const password = (): string => run().password;

/**
 * Sign in through the form, the way a person does. The app allows 30 sign-ins a minute from one address; a full run
 * can pass that, so if the limit answers, the run empties the counters and tries once more.
 */
export async function signIn(page: Page, email: string): Promise<void> {
    for (const attempt of [1, 2]) {
        await page.goto('/login');
        await page.getByLabel('Email').fill(email);
        await page.locator('#password').fill(password());
        await page.getByRole('button', { name: 'Sign in' }).click();
        await page.waitForLoadState('load');
        if (attempt === 1 && await page.getByText(/Too many (login )?attempts/).count() > 0) {
            op({ op: 'clear-rate-limits' });
            continue;
        }
        break;
    }
    await expect(page).toHaveURL(/\/home$/);
}

/** Sign out through the account menu. */
export async function signOut(page: Page): Promise<void> {
    await page.getByRole('button', { name: 'Account menu' }).click();
    await page.getByRole('button', { name: 'Log out' }).click();
    await expect(page).toHaveURL(new RegExp(`^${baseURL}/?$`));
}

/**
 * Fill in and send the sign-up form; the person lands on "Verify your email". The app allows ten sign-ups in ten
 * minutes from one address, and the whole matrix makes more than that, so the counters are emptied once if it answers.
 */
export async function register(page: Page, name: string): Promise<string> {
    const email = `${unique('e2e-')}@infodot.test`;
    for (const attempt of [1, 2]) {
        await page.goto('/register');
        await page.getByLabel('Name').fill(name);
        await page.getByLabel('Email').fill(email);
        await page.locator('#password').fill(password());
        await page.locator('#password_confirmation').fill(password());
        await page.getByRole('checkbox', { name: /I agree to the/ }).check();
        await page.getByRole('button', { name: 'Sign Up' }).click();
        await page.waitForLoadState('load');
        if (attempt === 1 && await page.getByText('Too many attempts. Please wait a few minutes and try again.').count() > 0) {
            op({ op: 'clear-rate-limits' });
            continue;
        }
        break;
    }
    await expect(page.getByRole('heading', { name: 'Verify your email' })).toBeVisible();

    return email;
}

/** Open the link from the verification email (read out of the mail log) in the same browser. */
export async function verifyByEmailLink(page: Page, email: string): Promise<void> {
    await expect.poll(() => {
        try {
            return verificationLink(email);
        } catch {
            return null;
        }
    }, { message: `a verification email for ${email}` }).not.toBeNull();
    await page.goto(verificationLink(email));
}

/** The page is no wider than the screen. */
export async function expectNoSidewaysScroll(page: Page): Promise<void> {
    const { scroll, client } = await page.evaluate(() => ({ scroll: document.documentElement.scrollWidth, client: document.documentElement.clientWidth }));
    expect(scroll, `${new URL(page.url()).pathname} is ${scroll}px wide on a ${client}px screen`).toBeLessThanOrEqual(client);
}

export type Watch = { problems: string[]; pages: string[] };

/**
 * Listen to a page for everything a visitor must never meet: a console error, an uncaught script error, a request to
 * the app that failed, or a document answered with 4xx/5xx.
 */
export function watch(page: Page): Watch {
    const record: Watch = { problems: [], pages: [] };
    const where = () => {
        try {
            return new URL(page.url()).pathname;
        } catch {
            return page.url();
        }
    };

    page.on('console', (message) => {
        if (message.type() === 'error') {
            record.problems.push(`console error on ${where()}: ${message.text()}`);
        }
    });
    page.on('pageerror', (error) => record.problems.push(`uncaught error on ${where()}: ${error.message}`));
    page.on('requestfailed', (request) => {
        const reason = request.failure()?.errorText ?? '';
        // A request the browser dropped because the person moved to the next page is not a failure of the app.
        if (request.url().startsWith(baseURL) && !/aborted|cancell?ed|NS_BINDING_ABORTED/i.test(reason)) {
            record.problems.push(`request failed on ${where()}: ${request.method()} ${request.url()} (${reason})`);
        }
    });
    page.on('response', (response) => {
        if (!response.url().startsWith(baseURL)) {
            return;
        }
        if (response.status() >= 400) {
            record.problems.push(`${response.status()} on ${where()}: ${response.request().method()} ${response.url()}`);
        }
    });

    return record;
}

/** Pictures that did not load: the browser finished with them and they have no size. */
export async function brokenImages(page: Page): Promise<string[]> {
    // Bring lazy pictures in first, then give them a moment.
    await page.evaluate(async () => {
        for (const img of Array.from(document.images)) {
            img.loading = 'eager';
        }
        await Promise.all(Array.from(document.images).map((img) => (img.complete ? null : new Promise((done) => {
            img.addEventListener('load', done, { once: true });
            img.addEventListener('error', done, { once: true });
            setTimeout(done, 5000);
        }))));
    });

    return page.evaluate(() => Array.from(document.images)
        .filter((img) => img.getAttribute('src') && img.complete && img.naturalWidth === 0)
        .map((img) => img.currentSrc || img.src));
}

/** A full-page picture for the reviewer, in tests/Browser/screens/<size>/. */
export async function screenshot(page: Page, size: string, name: string): Promise<string> {
    const file = path.join(screensDir, size, `${name}.png`);
    fs.mkdirSync(path.dirname(file), { recursive: true });
    // Walk down the page and back first: the public home page draws its sections as they scroll into view.
    await page.evaluate(async () => {
        const step = Math.max(200, window.innerHeight - 100);
        for (let y = 0; y < document.documentElement.scrollHeight; y += step) {
            window.scrollTo(0, y);
            await new Promise((done) => setTimeout(done, 60));
        }
        window.scrollTo(0, 0);
        await new Promise((done) => setTimeout(done, 150));
    });
    await page.screenshot({ path: file, fullPage: true, animations: 'disabled' });

    return path.relative(screensDir, file);
}

export type Refusal = { status: number; path: string };

/** Open an address the person has no right to and say how the app answered. A server error is never an answer. */
export async function refusal(page: Page, address: string): Promise<Refusal> {
    const response = await page.goto(address);
    const status = response?.status() ?? 0;
    expect(status, `${address} answered ${status}`).toBeLessThan(500);
    expect(status, `${address} gave no answer`).toBeGreaterThan(0);

    return { status, path: new URL(page.url()).pathname };
}

export function isHandheld(testInfo: TestInfo): boolean {
    return /^(phone|tablet)/.test(testInfo.project.name);
}
