import type { Page } from '@playwright/test';

/**
 * R-52: the top bar is the shared dot-ui header. A place is a plain link (Home), or an item inside one of the bar's groups
 * (Knowledge, Network, Platforms, Admin), or, when the bar is full, inside More; on a phone every place is in the menu drawer.
 * A person looks for the place, opens whatever holds it, and clicks. This does the same, so a spec says where it goes, not how the bar folds.
 */
export async function goTo(page: Page, label: string, group?: string): Promise<void> {
    const width = page.viewportSize()?.width ?? 1440;

    if (width <= 860) {
        await page.getByRole('button', { name: 'Menu', exact: true }).click();
        const drawer = page.getByRole('dialog', { name: 'Menu' });
        const link = drawer.getByRole('link', { name: label, exact: true });
        if (group && ! (await link.isVisible())) {
            await drawer.getByRole('button', { name: group, exact: true }).click();
        }
        await link.click();

        return;
    }

    const header = page.locator('header.dh');
    const direct = header.getByRole('link', { name: label, exact: true });
    if (await direct.first().isVisible()) {
        await direct.first().click();

        return;
    }

    const holder = group ? header.getByRole('button', { name: group, exact: true }) : null;
    if (holder && (await holder.isVisible())) {
        await holder.click();
    } else {
        await header.getByRole('button', { name: 'More', exact: true }).click();
    }
    await header.getByRole('link', { name: label, exact: true }).filter({ visible: true }).first().click();
}

/** Opens the account menu (desktop) or the drawer (phone) and clicks one of the person's own links. */
export async function accountLink(page: Page, label: string): Promise<void> {
    const width = page.viewportSize()?.width ?? 1440;

    if (width <= 860) {
        await page.getByRole('button', { name: 'Menu', exact: true }).click();
        await page.getByRole('dialog', { name: 'Menu' }).getByRole('link', { name: label, exact: true }).click();

        return;
    }
    await page.getByRole('button', { name: /^Account menu/ }).click();
    await page.locator('#dh-account-menu').getByRole('link', { name: label, exact: true }).click();
}
