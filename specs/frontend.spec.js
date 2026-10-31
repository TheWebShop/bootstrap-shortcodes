/**
 * Frontend rendering and interaction of every shortcode, under the three asset
 * setups the plugin supports, plus content saved by the block editor.
 */
const { test, expect } = require('@wordpress/e2e-test-utils-playwright');

const EVERY_COMPONENT = [
    '[bs_row class="row"][bs_col class="col-sm-6"]Left column[/bs_col][bs_col class="col-sm-6"]Right column[/bs_col][/bs_row]',
    '[bs_tabs][bs_thead][bs_tab class="active" href="#t1" title="First tab"][bs_tab href="#t2" title="Second tab"][/bs_thead]',
    '[bs_tcontents][bs_tcontent class="active" id="t1"]Pane one[/bs_tcontent][bs_tcontent id="t2"]Pane two[/bs_tcontent][/bs_tcontents][/bs_tabs]',
    '[bs_collapse id="acc"][bs_citem id="c1" parent="acc" title="Item one"]Body one[/bs_citem][bs_citem id="c2" parent="acc" title="Item two" open="true"]Body two[/bs_citem][/bs_collapse]',
    '[bs_notification type="info"]A notice[/bs_notification]',
    '[bs_well size="sm"]A well[/bs_well]',
    '[bs_button type="primary" value="Go somewhere" href="https://example.com/"]',
    '[bs_label type="success"]A label[/bs_label]',
    '[bs_icon name="glyphicon glyphicon-star"]',
    '[bs_lead]Lead text[/bs_lead]',
    '[bs_tooltip placement="top" trigger="hover"]Hover me[/bs_tooltip]',
].join('\n');

const SETTINGS_PAGE = { path: 'options-general.php', query: 'page=bootstrap-shortcodes/bootstrap-shortcodes.php' };

async function setAssetOptions(admin, page, { css, js, conditional = false }) {
    await admin.visitAdminPage(SETTINGS_PAGE.path, SETTINGS_PAGE.query);
    await page.locator('input[name="bs_options[chk_default_options_css]"]').setChecked(css);
    await page.locator('input[name="bs_options[chk_default_options_js]"]').setChecked(js);
    await page.locator('input[name="bs_options[chk_conditional_assets]"]').setChecked(conditional);
    await page.locator('input[type="submit"]').click();
    await expect(page.locator('#setting-error-settings_updated, .notice-success')).toBeVisible();
}

// Match by handle (WordPress prints id="<handle>-css|js"), not by file, because the
// theme helper deliberately reuses the plugin's files under its own handles.
function pluginAssets(page) {
    return {
        css: page.locator('link#bs_bootstrap-css'),
        js: page.locator('script#bs_bootstrap-js'),
        init: page.locator('script#bs_init-js'),
    };
}

async function expectInteractionsWork(page) {
    // Tabs
    await expect(page.locator('#t1')).toBeVisible();
    await expect(page.locator('#t2')).toBeHidden();
    await page.locator('a[href="#t2"]').click();
    await expect(page.locator('#t2')).toBeVisible();
    await expect(page.locator('#t1')).toBeHidden();
    // Collapse: second item starts open, first toggles open on click
    await expect(page.locator('#c2')).toHaveClass(/\bin\b/);
    await page.locator('a[href="#c1"]').click();
    await expect(page.locator('#c1')).toHaveClass(/\bin\b/);
    await expect(page.locator('a[href="#c1"]')).toHaveAttribute('aria-expanded', 'true');
    // Tooltip
    await page.locator('a[data-toggle="tooltip"]').hover();
    await expect(page.locator('.tooltip.in')).toContainText('Hover me');
    // Dismissible alert
    await page.locator('.alert .close').click();
    await expect(page.locator('.alert')).toHaveCount(0);
}

test.describe('Frontend rendering', () => {
    let postLink;
    let blockPostLink;

    test.beforeAll(async ({ requestUtils }) => {
        const post = await requestUtils.createPost({ title: 'Every component', content: EVERY_COMPONENT, status: 'publish' });
        postLink = post.link;
        const blockPost = await requestUtils.createPost({
            title: 'Block editor shortcode block',
            content: '<!-- wp:shortcode -->\n[bs_well size="lg"]Well from a Shortcode block[/bs_well]\n<!-- /wp:shortcode -->\n<!-- wp:paragraph -->\n<p>Plain paragraph.</p>\n<!-- /wp:paragraph -->',
            status: 'publish',
        });
        blockPostLink = blockPost.link;
    });

    test.afterAll(async ({ requestUtils }) => {
        await requestUtils.deleteAllPosts();
    });

    test('plugin-provided Bootstrap: everything renders and interacts', async ({ admin, page }) => {
        await setAssetOptions(admin, page, { css: true, js: true });
        await page.goto(postLink);

        const assets = pluginAssets(page);
        await expect(assets.css).toHaveCount(1);
        await expect(assets.js).toHaveCount(1);
        await expect(assets.init).toHaveCount(1);

        await expect(page.locator('.row .col-sm-6')).toHaveCount(2);
        await expect(page.locator('.well.well-sm')).toHaveText('A well');
        await expect(page.locator('a.btn.btn-primary')).toHaveAttribute('href', 'https://example.com/');
        await expect(page.locator('.label.label-success')).toHaveText('A label');
        await expect(page.locator('i.glyphicon-star')).toHaveAttribute('aria-hidden', 'true');
        await expect(page.locator('.lead')).toHaveText('Lead text');
        await expectInteractionsWork(page);
    });

    test('theme-provided Bootstrap: bundled CSS/JS off, tooltips still initialise', async ({ admin, page }) => {
        await setAssetOptions(admin, page, { css: false, js: false });
        await page.goto(postLink + (postLink.includes('?') ? '&' : '?') + 'e2e_theme_bootstrap=1');

        const assets = pluginAssets(page);
        await expect(assets.css).toHaveCount(0);
        await expect(assets.js).toHaveCount(0);
        await expect(assets.init).toHaveCount(1);
        await expect(page.locator('link#theme-bootstrap-css')).toHaveCount(1);
        await expectInteractionsWork(page);
    });

    test('static-only: bundled JS off, CSS components still render', async ({ admin, page }) => {
        await setAssetOptions(admin, page, { css: true, js: false });
        await page.goto(postLink);

        const assets = pluginAssets(page);
        await expect(assets.css).toHaveCount(1);
        await expect(assets.js).toHaveCount(0);
        await expect(assets.init).toHaveCount(1);
        await expect(page.locator('.well.well-sm')).toBeVisible();
        await expect(page.locator('a.btn.btn-primary')).toBeVisible();
        await expect(page.locator('.alert.alert-info')).toBeVisible();
        // No console errors from init.js when no tooltip plugin exists.
        const errors = [];
        page.on('pageerror', (e) => errors.push(e.message));
        await page.reload();
        expect(errors).toEqual([]);
    });

    test('conditional loading: assets only where shortcodes are used', async ({ admin, page, requestUtils }) => {
        const plain = await requestUtils.createPost({ title: 'Plain', content: 'No shortcodes here.', status: 'publish' });
        await setAssetOptions(admin, page, { css: true, js: true, conditional: true });

        await page.goto(plain.link);
        await expect(pluginAssets(page).css).toHaveCount(0);
        await expect(pluginAssets(page).init).toHaveCount(0);

        await page.goto(postLink);
        await expect(pluginAssets(page).css).toHaveCount(1);
        await expectInteractionsWork(page);
    });

    test('block editor content: a Shortcode block renders the component', async ({ admin, page }) => {
        await setAssetOptions(admin, page, { css: true, js: true });
        await page.goto(blockPostLink);
        await expect(page.locator('.well.well-lg')).toHaveText('Well from a Shortcode block');
    });
});
