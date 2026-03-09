# Future Work & Improvements

This document tracks ideas for future improvements, refactoring, and features for the `bootstrap-shortcodes` plugin.

## Technical Debt & Refactoring
- The plugin was last heavily developed around 2015. It still references Bootstrap 3.3.
- Upgrade to a modern Bootstrap version (Bootstrap 5) or provide a compatibility layer.
- Refactor the procedural code in `inc/` to use modern PHP object-oriented patterns, namespaces, and autoloading.
- Consider migrating from TinyMCE buttons to Gutenberg (Block Editor) blocks, as TinyMCE is legacy in modern WordPress.

## Features
- Add support for newer Bootstrap components not currently mapped.
