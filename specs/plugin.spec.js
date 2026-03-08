/**
 * WordPress dependencies
 */
const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Plugin Activation', () => {

	test('Should be able to activate the plugin', async ({ admin, page }) => {
		await admin.visitAdminPage('plugins.php');

		const pluginRow = page.locator('tr[data-slug="bootstrap-shortcodes"]');

		// If the plugin happens to be inactive, ensure we can activate it
		const activateLink = pluginRow.locator('.activate a');
		if (await activateLink.isVisible()) {
			await activateLink.click();
		}

		// The row should have the 'active' class (and not 'inactive')
		await expect(pluginRow).toHaveClass(/active/);
	});

});
