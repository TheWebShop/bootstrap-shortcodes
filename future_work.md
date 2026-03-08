# Future Work & Improvements

This document tracks ideas for future improvements, refactoring, and features for the `bootstrap-shortcodes` plugin.

## Technical Debt & Refactoring
- The plugin was last heavily developed around 2015. It still references Bootstrap 3.3.
- Upgrade to a modern Bootstrap version (Bootstrap 5) or provide a compatibility layer.
- Refactor the procedural code in `inc/` to use modern PHP object-oriented patterns, namespaces, and autoloading.
- Consider migrating from TinyMCE buttons to Gutenberg (Block Editor) blocks, as TinyMCE is legacy in modern WordPress.

## Testing & Automation
- Introduce automated testing (e.g., PHPUnit for PHP logic, Playwright/Cypress for E2E testing).
- Add a CI/CD pipeline (e.g., GitHub Actions) to run tests and automate WordPress plugin directory deployments.

## Features
- Add support for newer Bootstrap components not currently mapped.

## SVN Release Fixes Needed
- Remove `well, responsive, widget` from tags in `readme.txt`. Keep only 5 maximum.
- Remove `Designwall Team` from contributors in `readme.txt`, as they do not have a registered wp.org username.
