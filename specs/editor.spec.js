/**
 * WordPress dependencies
 */
const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Classic Editor Integration', () => {

    test.beforeEach(async ({ admin, page }) => {
        page.on('dialog', dialog => dialog.accept());
        await admin.visitAdminPage('post-new.php');
    });

    test('Should render Bootstrap Shortcodes buttons in the toolbar', async ({ admin, page, editor }) => {

        // Dump HTML for debugging
        const html = await page.content();
        const fs = require('fs');
        fs.writeFileSync('dom-debug.html', html);

        // Switch to Visual mode if not already there, since WP remembers mode per user
        const htmlMode = await page.locator('#wp-content-wrap.html-active').count();
        if (htmlMode > 0) {
            await page.locator('#content-tmce').click();
        }

        // Dump HTML for debugging AFTER click
        const html2 = await page.content();
        fs.writeFileSync('dom-debug-after.html', html2);

        // Wait for the TinyMCE editor to be ready
        await page.waitForSelector('#wp-content-wrap.tmce-active');

        // The Toolbar Toggle (kitchen sink) might be required to see the row of bootstrap buttons.
        // It uses aria-label="Toolbar Toggle (Shift+Alt+Z)"
        const isKitchenSinkActive = await page.locator('.mce-active[aria-label^="Toolbar Toggle"]').count();

        if (isKitchenSinkActive === 0) {
            const toggleButton = page.locator('div[aria-label^="Toolbar Toggle"] button').first();
            if (await toggleButton.isVisible()) {
                await toggleButton.click();
            }
        }

        // Look for one of the registered buttons in TinyMCE, e.g., 'Buttons'
        const bsButtonBtn = page.locator('div[aria-label="Buttons"] button');
        await expect(bsButtonBtn).toBeVisible();
    });

    test('Should insert a Button shortcode using the modal', async ({ admin, page, editor }) => {
        // Switch to Visual mode if not already there, since WP remembers mode per user
        const htmlMode = await page.locator('#wp-content-wrap.html-active').count();
        if (htmlMode > 0) {
            await page.locator('#content-tmce').click();
        }

        // Wait for the TinyMCE editor to be ready
        await page.waitForSelector('#wp-content-wrap.tmce-active');

        // Ensure kitchen sink is open
        const isKitchenSinkActive = await page.locator('.mce-active[aria-label^="Toolbar Toggle"]').count();
        if (isKitchenSinkActive === 0) {
            const toggleButton = page.locator('div[aria-label^="Toolbar Toggle"] button').first();
            if (await toggleButton.isVisible()) {
                await toggleButton.click();
            }
        }

        // Click the 'Buttons' TinyMCE button
        const bsButtonBtn = page.locator('div[aria-label="Buttons"] button');
        await expect(bsButtonBtn).toBeVisible();
        await bsButtonBtn.click();

        // The modal opens an iframe. Wait for the iframe locator.
        const modalFrame = page.frameLocator('.mce-window iframe');

        // Fill out the modal
        await modalFrame.locator('input[name="value"]').fill('E2E Dialog Button');
        await modalFrame.locator('select[name="type"]').selectOption('Danger');
        await modalFrame.locator('select[name="size"]').selectOption('lg');

        // Click Insert
        await modalFrame.locator('#insert').click();

        // Switch to Text editor to verify the shortcode was inserted
        await page.locator('#content-html').click();

        // The textarea should contain the shortcode
        const content = await page.locator('#content').inputValue();
        expect(content).toContain('[bs_button size="lg" type="danger" value="E2E Dialog Button" href=""]');
    });

    test('Should insert a Tabs shortcode using the modal', async ({ admin, page, editor }) => {
        // Switch to Visual mode if not already there, since WP remembers mode per user
        const htmlMode = await page.locator('#wp-content-wrap.html-active').count();
        if (htmlMode > 0) {
            await page.locator('#content-tmce').click();
        }

        // Wait for the TinyMCE editor to be ready
        await page.waitForSelector('#wp-content-wrap.tmce-active');

        // Ensure kitchen sink is open
        const isKitchenSinkActive = await page.locator('.mce-active[aria-label^="Toolbar Toggle"]').count();
        if (isKitchenSinkActive === 0) {
            const toggleButton = page.locator('div[aria-label^="Toolbar Toggle"] button').first();
            if (await toggleButton.isVisible()) {
                await toggleButton.click();
            }
        }

        const bsTabsBtn = page.locator('div[aria-label="Tabs"] button');
        await expect(bsTabsBtn).toBeVisible();
        await bsTabsBtn.click();

        const modalFrame = page.frameLocator('.mce-window iframe');

        // Click Insert (Using default populated tab structures in the iframe)
        await modalFrame.locator('#btn_insert').click();

        // Switch to Text editor to verify the shortcode was inserted
        await page.locator('#content-html').click();

        const content = await page.locator('#content').inputValue();

        // Assert some portion of the complex generated tabs shortcode output exists
        expect(content).toContain('[bs_tabs]');
        expect(content).toContain('Tab title');
        expect(content).toContain('[/bs_tabs]');
    });

    test('Should insert a Collapse shortcode using the modal', async ({ admin, page, editor }) => {
        const htmlMode = await page.locator('#wp-content-wrap.html-active').count();
        if (htmlMode > 0) { await page.locator('#content-tmce').click(); }
        await page.waitForSelector('#wp-content-wrap.tmce-active');
        const isKitchenSinkActive = await page.locator('.mce-active[aria-label^="Toolbar Toggle"]').count();
        if (isKitchenSinkActive === 0) {
            const toggleButton = page.locator('div[aria-label^="Toolbar Toggle"] button').first();
            if (await toggleButton.isVisible()) { await toggleButton.click(); }
        }

        const btn = page.locator('div[aria-label="Collapse"] button');
        await expect(btn).toBeVisible();
        await btn.click();

        // Native TinyMCE modals render directly in the DOM, not via iframe
        const modal = page.locator('.mce-window');
        await expect(modal).toBeVisible();

        // Click the OK/Submit button in the native dialog footer
        await modal.locator('.mce-primary').click();

        await page.locator('#content-html').click();
        const content = await page.locator('#content').inputValue();
        expect(content).toContain('[bs_collapse');
    });

    test('Should insert a Lead shortcode directly', async ({ admin, page, editor }) => {
        const htmlMode = await page.locator('#wp-content-wrap.html-active').count();
        if (htmlMode > 0) { await page.locator('#content-tmce').click(); }
        await page.waitForSelector('#wp-content-wrap.tmce-active');
        const isKitchenSinkActive = await page.locator('.mce-active[aria-label^="Toolbar Toggle"]').count();
        if (isKitchenSinkActive === 0) {
            const toggleButton = page.locator('div[aria-label^="Toolbar Toggle"] button').first();
            if (await toggleButton.isVisible()) { await toggleButton.click(); }
        }

        const btn = page.locator('div[aria-label="Lead"] button');
        await expect(btn).toBeVisible();
        await btn.click();

        await page.locator('#content-html').click();
        const content = await page.locator('#content').inputValue();
        expect(content).toContain('[bs_lead]');
    });

    test('Should insert a Well shortcode from dropdown', async ({ admin, page, editor }) => {
        const htmlMode = await page.locator('#wp-content-wrap.html-active').count();
        if (htmlMode > 0) { await page.locator('#content-tmce').click(); }
        await page.waitForSelector('#wp-content-wrap.tmce-active');
        const isKitchenSinkActive = await page.locator('.mce-active[aria-label^="Toolbar Toggle"]').count();
        if (isKitchenSinkActive === 0) {
            const toggleButton = page.locator('div[aria-label^="Toolbar Toggle"] button').first();
            if (await toggleButton.isVisible()) { await toggleButton.click(); }
        }

        const btn = page.locator('div[aria-label="Well"] button');
        await expect(btn).toBeVisible();
        await btn.click();

        await page.locator('.mce-menu-item:has-text("Small well")').click();

        await page.locator('#content-html').click();
        const content = await page.locator('#content').inputValue();
        expect(content).toContain('[bs_well size="sm"]');
    });

    test('Should insert a Label shortcode using the modal', async ({ admin, page, editor }) => {
        const htmlMode = await page.locator('#wp-content-wrap.html-active').count();
        if (htmlMode > 0) { await page.locator('#content-tmce').click(); }
        await page.waitForSelector('#wp-content-wrap.tmce-active');
        const isKitchenSinkActive = await page.locator('.mce-active[aria-label^="Toolbar Toggle"]').count();
        if (isKitchenSinkActive === 0) {
            const toggleButton = page.locator('div[aria-label^="Toolbar Toggle"] button').first();
            if (await toggleButton.isVisible()) { await toggleButton.click(); }
        }

        const btn = page.locator('div[aria-label="Labels"] button');
        await expect(btn).toBeVisible();
        await btn.click();

        const modalFrame = page.frameLocator('.mce-window iframe');
        await modalFrame.locator('input[name="text"]').fill('Sale');
        await modalFrame.locator('select[name="type"]').selectOption('Danger');
        await modalFrame.locator('#insert').click();

        await page.locator('#content-html').click();
        const content = await page.locator('#content').inputValue();
        expect(content).toContain('[bs_label type="danger"]Sale[/bs_label]');
    });

    test('Should insert an Alert shortcode using the modal', async ({ admin, page, editor }) => {
        const htmlMode = await page.locator('#wp-content-wrap.html-active').count();
        if (htmlMode > 0) { await page.locator('#content-tmce').click(); }
        await page.waitForSelector('#wp-content-wrap.tmce-active');
        const isKitchenSinkActive = await page.locator('.mce-active[aria-label^="Toolbar Toggle"]').count();
        if (isKitchenSinkActive === 0) {
            const toggleButton = page.locator('div[aria-label^="Toolbar Toggle"] button').first();
            if (await toggleButton.isVisible()) { await toggleButton.click(); }
        }

        const btn = page.locator('div[aria-label="Alerts"] button');
        await expect(btn).toBeVisible();
        await btn.click();

        const modalFrame = page.frameLocator('.mce-window iframe');
        await modalFrame.locator('button[type="submit"]').click();

        await page.locator('#content-html').click();
        const content = await page.locator('#content').inputValue();
        expect(content).toContain('[bs_notification');
    });

    test('Should insert a Tooltip shortcode using the modal', async ({ admin, page, editor }) => {
        const htmlMode = await page.locator('#wp-content-wrap.html-active').count();
        if (htmlMode > 0) { await page.locator('#content-tmce').click(); }
        await page.waitForSelector('#wp-content-wrap.tmce-active');
        const isKitchenSinkActive = await page.locator('.mce-active[aria-label^="Toolbar Toggle"]').count();
        if (isKitchenSinkActive === 0) {
            const toggleButton = page.locator('div[aria-label^="Toolbar Toggle"] button').first();
            if (await toggleButton.isVisible()) { await toggleButton.click(); }
        }

        const btn = page.locator('div[aria-label="Tooltip"] button');
        await expect(btn).toBeVisible();
        await btn.click();

        const modalFrame = page.frameLocator('.mce-window iframe');
        await modalFrame.locator('input[name="content"]').fill('My Tooltip');
        await modalFrame.locator('#insert').click();

        await page.locator('#content-html').click();
        const content = await page.locator('#content').inputValue();
        expect(content).toContain('[bs_tooltip');
    });

    test('Should insert an Icon shortcode from the grid', async ({ admin, page, editor }) => {
        const htmlMode = await page.locator('#wp-content-wrap.html-active').count();
        if (htmlMode > 0) { await page.locator('#content-tmce').click(); }
        await page.waitForSelector('#wp-content-wrap.tmce-active');
        const isKitchenSinkActive = await page.locator('.mce-active[aria-label^="Toolbar Toggle"]').count();
        if (isKitchenSinkActive === 0) {
            const toggleButton = page.locator('div[aria-label^="Toolbar Toggle"] button').first();
            if (await toggleButton.isVisible()) { await toggleButton.click(); }
        }

        const btn = page.locator('div[aria-label="Icons"] button');
        await expect(btn).toBeVisible();
        await btn.click();

        const modalFrame = page.frameLocator('.mce-window iframe');
        // Click on the first icon in the list, or the "Subtitles" icon
        await modalFrame.locator('.glyphicon-subtitles').first().click();

        await page.locator('#content-html').click();
        const content = await page.locator('#content').inputValue();
        expect(content).toContain('[bs_icon name="glyphicon glyphicon-subtitles"]');
    });

});
