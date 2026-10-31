/**
 * Tools > Bootstrap Shortcodes: run a scan from the browser and read the report.
 */
const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

test.describe('Dependency report', () => {
    test.beforeAll(async ({ requestUtils }) => {
        await requestUtils.createPost({
            title: 'Report fixture',
            content: '[bs_row class="row extra"][bs_col class="col-sm-6"][bs_well]x[/bs_well][/bs_col][/bs_row] [bs_button value="b"]',
            status: 'publish',
        });
        await requestUtils.createPost({ title: 'Report draft', content: '[bs_lead]d[/bs_lead]', status: 'draft' });
    });

    test.afterAll(async ({ requestUtils }) => {
        await requestUtils.deleteAllPosts();
    });

    test('scans the site and reports components, posts, surfaces and the share summary', async ({ admin, page }) => {
        await admin.visitAdminPage('tools.php', 'page=bs-shortcodes-tools');
        await expect(page.getByRole('heading', { name: 'Bootstrap Shortcodes: dependency report' })).toBeVisible();

        await page.getByRole('button', { name: /Scan this site|Scan again/ }).click();
        await expect(page.getByRole('heading', { name: 'If you deactivated this plugin today' })).toBeVisible({ timeout: 30000 });

        const components = page.locator('table.bs-components');
        await expect(components).toContainText('[bs_well]');
        await expect(components).toContainText('[bs_button]');
        await expect(components).toContainText('[bs_lead]');

        const posts = page.locator('table.bs-posts');
        await expect(posts).toContainText('Report fixture');
        await expect(posts).toContainText('nested inside another component');
        await expect(posts).toContainText('custom CSS classes');
        await expect(posts.getByRole('link', { name: 'Report fixture' })).toHaveAttribute('href', /post\.php\?post=\d+&action=edit/);
        await expect(posts).toContainText('draft');

        await expect(page.getByRole('heading', { name: 'Not scanned' })).toBeVisible();
        await expect(page.getByRole('link', { name: 'Download full report (JSON)' })).toBeVisible();

        // The share summary is opt-in, inspectable, and free of identifiers.
        await page.locator('details.bs-share summary').click();
        const payload = await page.locator('details.bs-share textarea').inputValue();
        const parsed = JSON.parse(payload);
        expect(parsed.components.bs_well.posts).toBe(1);
        expect(payload).not.toContain('Report fixture');
        expect(payload).not.toContain('http');
    });

    test('the report lives under Tools for administrators', async ({ page, admin }) => {
        await admin.visitAdminPage('tools.php');
        await expect(page.locator('#menu-tools')).toContainText('Bootstrap Shortcodes');
    });
});
