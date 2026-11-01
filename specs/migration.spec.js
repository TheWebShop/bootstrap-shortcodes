/**
 * The whole exit path in a browser: scan, preview, migrate one post, roll it
 * back, migrate in a batch, install the legacy-assets companion, deactivate
 * Bootstrap Shortcodes, and check the migrated content still renders and works
 * on desktop and phone viewports. Finally reactivate so other specs are unaffected.
 */
const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

const CLASSIC = [
    '[bs_row class="row"][bs_col class="col-sm-8"]Main column text[/bs_col][bs_col class="col-sm-4"]Side column text[/bs_col][/bs_row]',
    '',
    '[bs_button type="primary" value="Go somewhere" href="https://example.com/"]',
    '',
    '[bs_well size="sm"]A well of text[/bs_well]',
    '[bs_notification type="info"]An info notice[/bs_notification]',
    '[bs_lead]Lead text here[/bs_lead]',
    'Inline: [bs_label type="success"]Label text[/bs_label] and [bs_icon name="glyphicon glyphicon-star"] star and [bs_tooltip]tooltip words[/bs_tooltip].',
    '',
    '[bs_collapse id="faq"][bs_citem id="q1" parent="faq" title="First question"]First answer[/bs_citem][bs_citem id="q2" parent="faq" title="Second question" open="true"]Second answer[/bs_citem][/bs_collapse]',
    '',
    '[bs_tabs][bs_thead][bs_tab class="active" href="#t1" title="Tab one"][bs_tab href="#t2" title="Tab two"][/bs_thead][bs_tcontents][bs_tcontent class="active" id="t1"]Pane one text[/bs_tcontent][bs_tcontent id="t2"]Pane two text[/bs_tcontent][/bs_tcontents][/bs_tabs]',
].join('\n');

const BLOCKS = [
    '<!-- wp:paragraph -->',
    '<p>Block intro</p>',
    '<!-- /wp:paragraph -->',
    '',
    '<!-- wp:shortcode -->',
    '[bs_row][bs_col class="col-sm-6"]Block left[/bs_col][bs_col class="col-sm-6"]Block right[/bs_col][/bs_row]',
    '<!-- /wp:shortcode -->',
].join('\n');

const TOOLS = { path: 'tools.php', query: 'page=bs-shortcodes-tools' };

async function scan(admin, page) {
    await admin.visitAdminPage(TOOLS.path, TOOLS.query);
    await page.getByRole('button', { name: /Scan this site|Scan again/ }).click();
    await expect(page.getByRole('heading', { name: 'Migrate', exact: true })).toBeVisible({ timeout: 30000 });
}

async function expectMigratedPageWorks(page, link) {
    await page.goto(link);
    // Grid
    await expect(page.locator('.row .col-sm-8')).toContainText('Main column text');
    // Button
    const button = page.locator('a.btn.btn-primary');
    await expect(button).toHaveAttribute('href', 'https://example.com/');
    await expect(button).toBeVisible();
    // Well, alert, lead, label with self-contained styling
    await expect(page.locator('div.well')).toHaveCSS('background-color', 'rgb(245, 245, 245)');
    await expect(page.locator('div.alert[role="alert"]')).toContainText('An info notice');
    await expect(page.locator('.lead')).toContainText('Lead text here');
    await expect(page.locator('span.label-success')).toHaveCSS('background-color', 'rgb(92, 184, 92)');
    // Icon as inline SVG, tooltip as native title
    await expect(page.locator('svg.bs-icon-star path')).toHaveCount(1);
    await expect(page.locator('span.bs-tooltip')).toHaveAttribute('title', 'tooltip words');
    // Collapse: second open, first toggles by click and by keyboard
    await expect(page.locator('details#q2')).toHaveAttribute('open', '');
    await expect(page.locator('details#q1')).not.toHaveAttribute('open', '');
    await page.locator('details#q1 summary').click();
    await expect(page.locator('details#q1')).toHaveAttribute('open', '');
    await expect(page.locator('details#q1')).toContainText('First answer');
    // Exclusive group: opening q1 closed q2 (name attribute); keyboard toggles q1 back.
    await expect(page.locator('details#q2')).not.toHaveAttribute('open', '');
    await page.locator('details#q1 summary').focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('details#q1')).not.toHaveAttribute('open', '');
    // Tabs: sections, first open
    await expect(page.locator('details#t1')).toHaveAttribute('open', '');
    await expect(page.locator('details#t1 summary')).toHaveText('Tab one');
    await page.locator('details#t2 summary').click();
    await expect(page.locator('details#t2')).toContainText('Pane two text');
    // No literal shortcodes anywhere
    await expect(page.locator('body')).not.toContainText('[bs_');
}

const COMPANION = 'bootstrap-legacy-assets/bootstrap-legacy-assets';

// Start and finish without the companion, whatever an earlier run left behind.
async function removeCompanion(requestUtils) {
    try {
        await requestUtils.rest({ method: 'PUT', path: `/wp/v2/plugins/${COMPANION}`, data: { status: 'inactive' } });
    } catch (e) { /* not installed */ }
    try {
        await requestUtils.rest({ method: 'DELETE', path: `/wp/v2/plugins/${COMPANION}` });
    } catch (e) { /* not installed or not deletable here */ }
}

test.describe('Migration end to end', () => {
    let classic;
    let blocks;

    test.beforeAll(async ({ requestUtils }) => {
        await removeCompanion(requestUtils);
        await requestUtils.deleteAllPosts();
        classic = await requestUtils.createPost({ title: 'Classic migration', content: CLASSIC, status: 'publish' });
        blocks = await requestUtils.createPost({ title: 'Block migration', content: BLOCKS, status: 'publish' });
    });

    test('preview, migrate, roll back, batch-migrate, hand off assets, deactivate and verify', async ({ admin, page, requestUtils }) => {
        test.setTimeout(180000);

        // Default asset settings so the pre-migration page is the normal one.
        await admin.visitAdminPage('options-general.php', 'page=bootstrap-shortcodes/bootstrap-shortcodes.php');
        await page.locator('input[name="bs_options[chk_default_options_css]"]').setChecked(true);
        await page.locator('input[name="bs_options[chk_default_options_js]"]').setChecked(true);
        await page.locator('input[name="bs_options[chk_conditional_assets]"]').setChecked(false);
        await page.locator('input[type="submit"]').click();

        await scan(admin, page);
        const row = page.locator('table.bs-migrate-table tr', { hasText: 'Classic migration' });
        await expect(row).toContainText('bs_row');

        // Preview shows a diff, notes, and no write has happened.
        await row.getByRole('link', { name: 'Preview' }).click();
        await expect(page.getByRole('heading', { name: 'Preview: Classic migration' })).toBeVisible();
        await expect(page.locator('table.diff')).toBeVisible();
        await expect(page.locator('.bs-notes')).toContainText('Tabs become collapsible sections');
        await expect(page.locator('.bs-notes')).toContainText('dismiss');
        const before = await requestUtils.rest({ path: `/wp/v2/posts/${classic.id}`, params: { context: 'edit' } });
        expect(before.content.raw).toContain('[bs_row');

        // Migrate this post.
        await page.getByRole('button', { name: 'Migrate this post' }).click();
        await expect(page.locator('.notice.is-dismissible.notice-success')).toContainText('1 post migrated');
        const after = await requestUtils.rest({ path: `/wp/v2/posts/${classic.id}`, params: { context: 'edit' } });
        expect(after.content.raw).not.toContain('[bs_row');
        expect(after.content.raw).toContain('<details');

        // It renders and works with the plugin still active.
        await expectMigratedPageWorks(page, classic.link);

        // Roll back restores the exact original.
        await admin.visitAdminPage(TOOLS.path, TOOLS.query);
        const rollbackRow = page.locator('table.bs-rollback-table tr', { hasText: 'Classic migration' });
        await rollbackRow.locator('input[type="checkbox"]').check();
        page.once('dialog', (d) => d.accept());
        await page.getByRole('button', { name: 'Roll back selected' }).click();
        await expect(page.locator('.notice.is-dismissible.notice-success')).toContainText('1 post rolled back');
        const restored = await requestUtils.rest({ path: `/wp/v2/posts/${classic.id}`, params: { context: 'edit' } });
        expect(restored.content.raw).toBe(CLASSIC);

        // Batch-migrate both posts through the AJAX path.
        await scan(admin, page);
        await page.locator('table.bs-migrate-table .bs-check-all').check();
        page.once('dialog', (d) => d.accept());
        await page.getByRole('button', { name: 'Migrate selected' }).click();
        await expect(page.locator('.bs-migrate-progress')).toContainText('done', { timeout: 30000 });
        await expect(page.locator('table.bs-migrate-table tr', { hasText: 'Classic migration' })).toContainText('migrated');
        await expect(page.locator('table.bs-migrate-table tr', { hasText: 'Block migration' })).toContainText('migrated');
        const migratedBlocks = await requestUtils.rest({ path: `/wp/v2/posts/${blocks.id}`, params: { context: 'edit' } });
        expect(migratedBlocks.content.raw).toContain('<!-- wp:columns');
        expect(migratedBlocks.content.raw).not.toContain('wp:shortcode');

        // After a re-scan nothing is left in post content.
        await scan(admin, page);
        await expect(page.locator('.bs-impact')).toContainText('No post content uses the shortcodes');

        // The block editor's own validation: open both migrated posts with the
        // block editor and make sure no block reports invalid content.
        await admin.visitAdminPage('options-writing.php');
        await page.locator('input[name="classic-editor-replace"][value="block"]').check();
        await page.locator('input[name="classic-editor-allow-users"][value="allow"]').check();
        await page.locator('#submit').click();
        try {
            for (const id of [blocks.id, classic.id]) {
                // classic-editor__forget is what the plugin's own "Edit (Block editor)" link
                // uses; without it a post with no blocks opens in TinyMCE.
                await admin.visitAdminPage('post.php', `post=${id}&action=edit&classic-editor__forget`);
                // The block editor boots slowly on CI runners; wait for its canvas, not a heading.
                await page.locator('iframe[name="editor-canvas"], .block-editor-writing-flow').first().waitFor({ timeout: 60000 });
                // The block list lives in an iframe only when every block supports it; a
                // Classic block (HTML-mode output) keeps the editor inline. Look in both.
                const iframed = (await page.locator('iframe[name="editor-canvas"]').count()) > 0;
                const root = iframed ? page.frameLocator('iframe[name="editor-canvas"]') : page;
                await expect(root.locator('.is-root-container')).toBeVisible();
                await expect(root.locator('.wp-block').first()).toBeVisible();
                await expect(root.locator('.block-editor-warning')).toHaveCount(0);
                await expect(page.locator('.block-editor-warning')).toHaveCount(0);
                await expect(root.locator('body')).not.toContainText('unexpected or invalid content');
            }
        } finally {
            // Put the Classic Editor plugin back exactly as the editor specs expect,
            // even when a check above failed: classic by default and no per-user
            // switching (otherwise this user's last-used editor, now "block", would
            // win on post-new.php).
            await admin.visitAdminPage('options-writing.php');
            await page.locator('input[name="classic-editor-replace"][value="classic"]').check();
            await page.locator('input[name="classic-editor-allow-users"][value="disallow"]').check();
            await page.locator('#submit').click();
        }
        await admin.visitAdminPage(TOOLS.path, TOOLS.query);

        // Hand off the assets: consent is required, then install + activate.
        await page.getByRole('button', { name: 'Install and activate the companion' }).click();
        await expect(page.locator('.notice.is-dismissible.notice-error')).toContainText('Tick the confirmation box');
        await page.locator('input[name="bs_companion_consent"]').check();
        await page.getByRole('button', { name: 'Install and activate the companion' }).click();
        // Hosts where PHP cannot write the plugins directory get the manual path instead;
        // the zip must still download. The PHPUnit suite covers the real install.
        const notice = page.locator('.notice.is-dismissible').first();
        await expect(notice).toBeVisible();
        const companionInstalled = (await notice.textContent()).includes('Legacy Assets is installed and active');
        if (!companionInstalled) {
            await expect(notice).toContainText(/cannot write to the plugins directory|Could not create directory/);
            const zipUrl = await page.getByRole('link', { name: 'Download the companion zip instead' }).getAttribute('href');
            const zip = await page.request.get(zipUrl);
            expect(zip.ok()).toBeTruthy();
            expect(zip.headers()['content-type']).toContain('application/zip');
            expect((await zip.body()).length).toBeGreaterThan(100000);
        }

        // Deactivate Bootstrap Shortcodes for real.
        await admin.visitAdminPage('plugins.php');
        const pluginRow = page.locator('tr[data-slug="bootstrap-shortcodes"]');
        await pluginRow.locator('.deactivate a').click();
        await expect(pluginRow).toHaveClass(/\binactive\b/);
        if (companionInstalled) {
            await expect(page.locator('tr[data-plugin="bootstrap-legacy-assets/bootstrap-legacy-assets.php"]')).toHaveClass(/\bactive\b/);
        }

        // Frontend without the plugin: desktop and phone.
        await expectMigratedPageWorks(page, classic.link);
        await expect(page.locator('link#bs_bootstrap-css')).toHaveCount(0);
        await expect(page.locator('link#bs-legacy-bootstrap-css')).toHaveCount(companionInstalled ? 1 : 0);
        await expect(page.locator('link#bs-legacy-shortcodes-css')).toHaveCount(companionInstalled ? 1 : 0);
        if (companionInstalled) {
            // shortcodes.css points its icon-* sprite at ../images/; the companion must ship it.
            const css = await page.locator('link#bs-legacy-shortcodes-css').getAttribute('href');
            const sprite = await page.request.get(new URL('../images/glyphicons-halflings.png', css).href);
            expect(sprite.ok()).toBeTruthy();
        }
        await page.goto(blocks.link);
        await expect(page.locator('.wp-block-columns .wp-block-column').first()).toContainText('Block left');

        await page.setViewportSize({ width: 390, height: 844 });
        await expectMigratedPageWorks(page, classic.link);
        const mainBox = await page.locator('.row .col-sm-8').boundingBox();
        expect(mainBox.width).toBeLessThanOrEqual(390);

        // And with the companion gone too: self-contained styling needs nothing.
        await page.setViewportSize({ width: 1280, height: 800 });
        if (companionInstalled) {
            await admin.visitAdminPage('plugins.php');
            await page.locator('tr[data-plugin="bootstrap-legacy-assets/bootstrap-legacy-assets.php"] .deactivate a').click();
        }
        await page.goto(classic.link);
        await expect(page.locator('link#bs-legacy-bootstrap-css')).toHaveCount(0);
        await expect(page.locator('div.well')).toHaveCSS('background-color', 'rgb(245, 245, 245)');
        await expect(page.locator('svg.bs-icon-star')).toBeVisible();
        await expect(page.locator('details#q2')).toHaveAttribute('open', '');

        // Leave the site as other specs expect it.
        await admin.visitAdminPage('plugins.php');
        await page.locator('tr[data-slug="bootstrap-shortcodes"] .activate a').click();
        await expect(page.locator('tr[data-slug="bootstrap-shortcodes"]')).toHaveClass(/\bactive\b/);
    });

    test.afterAll(async ({ requestUtils }) => {
        await requestUtils.deleteAllPosts();
        await removeCompanion(requestUtils);
    });
});
