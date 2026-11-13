import type { Page } from '@playwright/test';
import { makeUser } from '../support/app';
import { expect, password, signIn, test } from '../support/fixtures';

type Reached = { key: string; visible: boolean };

/**
 * Give every control of the form a key (radios of one group share theirs: a group is one tab stop), remember how
 * each looks at rest, then press Tab from the top of the page and note where focus lands and whether it shows.
 */
async function tabThrough(page: Page, formSelector: string, limit = 90): Promise<{ wanted: string[]; reached: Reached[] }> {
    const wanted = await page.evaluate((selector) => {
        const form = document.querySelector(selector);
        if (!form) {
            throw new Error(`No form matches ${selector}`);
        }
        const keys: string[] = [];
        for (const el of Array.from(form.querySelectorAll<HTMLElement>('input:not([type="hidden"]), select, textarea, button, a[href]'))) {
            const box = el.getBoundingClientRect();
            const style = getComputedStyle(el);
            if ((el as HTMLInputElement).disabled || el.tabIndex < 0 || style.visibility === 'hidden' || style.display === 'none' || (box.width === 0 && box.height === 0)) {
                continue;
            }
            const input = el as HTMLInputElement;
            const key = input.type === 'radio' ? `radio:${input.name}` : `${el.tagName.toLowerCase()}:${el.id || input.name || (el.innerText || el.getAttribute('aria-label') || '').trim().slice(0, 30)}`;
            el.dataset.kbKey = key;
            const rest = getComputedStyle(el);
            el.dataset.kbRest = [rest.outlineStyle, rest.outlineWidth, rest.outlineColor, rest.boxShadow, rest.borderColor].join('|');
            if (!keys.includes(key)) {
                keys.push(key);
            }
        }

        return keys;
    }, formSelector);

    await page.evaluate(() => {
        (document.activeElement as HTMLElement | null)?.blur();
        window.scrollTo(0, 0);
    });

    const reached: Reached[] = [];
    for (let i = 0; i < limit; i += 1) {
        await page.keyboard.press('Tab');
        const now = await page.evaluate(() => {
            const el = document.activeElement as HTMLElement | null;
            if (!el || el === document.body || !el.dataset.kbKey) {
                return null;
            }
            const style = getComputedStyle(el);
            // The password field draws its focus ring on the wrapper that also holds the show/hide button.
            const wrapper = el.closest('.ix-pw');
            const around = wrapper ? getComputedStyle(wrapper) : null;
            const focused = [style.outlineStyle, style.outlineWidth, style.outlineColor, style.boxShadow, style.borderColor].join('|');
            const ring = (style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0) || (around !== null && ((around.outlineStyle !== 'none' && parseFloat(around.outlineWidth) > 0) || around.boxShadow !== 'none'));

            return { key: el.dataset.kbKey, visible: ring || focused !== el.dataset.kbRest };
        });
        if (now && !reached.some((r) => r.key === now.key)) {
            reached.push(now);
        }
        if (reached.length === wanted.length) {
            break;
        }
    }

    return { wanted, reached };
}

async function expectFormIsKeyboardReachable(page: Page, formSelector: string): Promise<void> {
    const { wanted, reached } = await tabThrough(page, formSelector);
    expect(wanted.length, 'the form has controls').toBeGreaterThan(1);
    // Every control, in the order they are laid out. A form that opens with the cursor already in its first field is
    // entered there, so Tab reaches that field last, after going round the page: the order is compared as a circle.
    const keys = reached.map((r) => r.key);
    const start = keys.indexOf(wanted[0]);
    expect(start, `${wanted[0]} is reached`).toBeGreaterThanOrEqual(0);
    expect([...keys.slice(start), ...keys.slice(0, start)]).toEqual(wanted);
    // And each one shows that it has focus.
    expect(reached.filter((r) => !r.visible).map((r) => r.key), 'controls with no visible focus').toEqual([]);
}

test.describe('I. Keyboard only', () => {
    test('sign-in: every control is reached with Tab, in order, with visible focus, and Enter signs in', async ({ page }) => {
        const person = makeUser('Keyboard Kagiso');
        await page.goto('/login');
        await expectFormIsKeyboardReachable(page, 'form[action$="/login"]');

        // The whole sign-in without a mouse.
        await page.goto('/login');
        await expect(page.getByLabel('Email')).toBeFocused();
        await page.keyboard.type(person.email);
        await page.keyboard.press('Tab');
        await expect(page.locator('#password')).toBeFocused();
        await page.keyboard.type(password());
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/\/home$/);
    });

    test('register: every control is reached with Tab, in order, with visible focus', async ({ page }) => {
        await page.goto('/register');
        await expectFormIsKeyboardReachable(page, 'form[action$="/register"]');
    });

    test('business creation: every control is reached with Tab, in order, with visible focus', async ({ page }) => {
        const person = makeUser('Keyboard Founder');
        await signIn(page, person.email);
        await page.goto('/teams/create');
        await expectFormIsKeyboardReachable(page, 'main form');

        // The radio group is one stop: the arrow keys choose within it.
        await page.getByRole('radio', { name: /^Anyone/ }).focus();
        await page.keyboard.press('ArrowDown');
        await expect(page.getByRole('radio', { name: /^InfoDot users/ })).toBeChecked();
    });

    test('the top bar: skip link first, then the navigation, search with Ctrl/Cmd+K, menus that close with Escape and give focus back', async ({ page }) => {
        const person = makeUser('Keyboard Navigator');
        await signIn(page, person.email);
        await page.goto('/questions');

        // Tab order from the top: skip link, brand, company menu, then the primary tabs in order.
        await page.evaluate(() => (document.activeElement as HTMLElement | null)?.blur());
        const seen: string[] = [];
        for (let i = 0; i < 16; i += 1) {
            await page.keyboard.press('Tab');
            seen.push(await page.evaluate(() => {
                const el = document.activeElement as HTMLElement;

                return (el.getAttribute('aria-label') || el.innerText || '').trim().replace(/\s+/g, ' ');
            }));
        }
        expect(seen[0]).toBe('Skip to content');
        const tabs = ['Home', 'Knowledge', 'Library', 'Seminars', 'Platforms', 'Businesses', 'People', 'Analytics'];
        expect(seen.filter((label) => tabs.includes(label))).toEqual(tabs);
        const companyMenu = seen.findIndex((label) => label.startsWith('Company menu'));
        expect(companyMenu).toBeGreaterThan(0);
        expect(companyMenu).toBeLessThan(seen.indexOf('Home'));

        // The skip link works.
        await page.reload();
        await page.keyboard.press('Tab');
        await expect(page.getByRole('link', { name: 'Skip to content' })).toBeFocused();
        await page.keyboard.press('Enter');
        await expect(page).toHaveURL(/#main$/);

        // A focused tab shows it.
        const knowledge = page.getByRole('navigation', { name: 'Primary' }).getByRole('link', { name: 'Knowledge', exact: true });
        await knowledge.focus();
        await page.keyboard.press('Shift+Tab');
        await page.keyboard.press('Tab');
        await expect(knowledge).toBeFocused();
        expect(await knowledge.evaluate((el) => {
            const style = getComputedStyle(el);

            return (style.outlineStyle !== 'none' && parseFloat(style.outlineWidth) > 0) || style.boxShadow !== 'none';
        }), 'the focused tab has a focus ring').toBe(true);

        // Search from anywhere.
        const search = page.getByRole('combobox', { name: /Search questions and solutions/ });
        await page.keyboard.press('ControlOrMeta+k');
        await expect(search).toBeFocused();
        await expect(page.getByRole('listbox', { name: 'Suggestions' })).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(page.getByRole('listbox', { name: 'Suggestions' })).toBeHidden();

        // Each menu: Enter opens it, Tab goes into it, Escape closes it and focus is back on its button.
        for (const name of [/^Company menu/, /^Dot Apps$/, /^Account menu$/]) {
            const button = page.getByRole('button', { name });
            await button.focus();
            await page.keyboard.press('Enter');
            await expect(button, `${name} opens`).toHaveAttribute('aria-expanded', 'true');
            // The panel fades in over a tenth of a second; a person does not press Tab before they can see it.
            const panel = button.locator('xpath=following-sibling::*[1]');
            await expect(panel).toBeVisible();
            await page.waitForTimeout(200);
            await page.keyboard.press('Tab');
            await expect(button, `${name}: Tab moves into the menu`).not.toBeFocused();
            await page.keyboard.press('Escape');
            await expect(button, `${name} closes with Escape`).toHaveAttribute('aria-expanded', 'false');
            await expect(button, `${name} gets focus back`).toBeFocused();
        }
    });

    test('a dialog closes with Escape and gives focus back to the button that opened it', async ({ page }) => {
        const person = makeUser('Keyboard Dialog');
        await signIn(page, person.email);
        await page.goto('/user/profile/edit');
        const open = page.getByRole('button', { name: 'Delete account' }).first();
        await open.focus();
        await page.keyboard.press('Enter');
        const dialog = page.getByRole('dialog').filter({ hasText: 'Are you sure you want to delete your account?' });
        await expect(dialog).toBeVisible();
        await page.keyboard.press('Escape');
        await expect(dialog).toBeHidden();
        await expect(open).toBeFocused();
    });
});
