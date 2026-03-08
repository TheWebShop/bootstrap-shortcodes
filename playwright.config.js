const baseConfig = require('@wordpress/scripts/config/playwright.config.js');

module.exports = {
    ...baseConfig,
    testDir: './specs',
};
