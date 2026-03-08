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

    test('Should insert a shortcode using the modal and verify editor content', async ({ admin, page, editor }) => {
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

});
