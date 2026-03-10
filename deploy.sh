#!/bin/bash

# Configuration
GIT_REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
SVN_REPO_DIR="$(dirname "$GIT_REPO_DIR")/bootstrap-shortcodes-svn"
SVN_TRUNK_DIR="$SVN_REPO_DIR/trunk"

# Explicitly define files and folders to include
INCLUDES=(
  "bootstrap-shortcodes.php"
  "readme.txt"
  "CHANGELOG.md"
  "css/"
  "fonts/"
  "images/"
  "inc/"
  "js/"
  "assets/"
)

echo "🚀 Starting deployment to SVN..."

# Ensure SVN repo exists
if [ ! -d "$SVN_TRUNK_DIR" ]; then
  echo "❌ Error: SVN trunk directory not found at $SVN_TRUNK_DIR"
  exit 1
fi

# Create a temporary dist directory to stage files cleanly
DIST_DIR=$(mktemp -d)
echo "📁 Created temporary staging directory at $DIST_DIR"

# Copy exactly the included files into the dist directory
echo "📦 Copying files to staging..."
cd "$GIT_REPO_DIR" || exit 1
for INCLUDE in "${INCLUDES[@]}"; do
  if [ -e "$INCLUDE" ]; then
    rsync -R -a "$INCLUDE" "$DIST_DIR/"
  else
    echo "⚠️ Warning: Expected file/folder $INCLUDE not found!"
  fi
done

# Clear existing files in the SVN trunk (excluding the invisible .svn dir)
echo "🧹 Cleaning current SVN trunk..."
find "$SVN_TRUNK_DIR" -mindepth 1 -not -name '.svn' -delete

# Move the clean staged files into the SVN trunk
echo "🚚 Moving staged files into SVN trunk..."
cp -a "$DIST_DIR/." "$SVN_TRUNK_DIR/"

# Clean up temp directory
rm -rf "$DIST_DIR"

echo "✅ Success! Files have been mapped from Git to your SVN trunk."
echo "➡️  Next steps: Navigate to $SVN_TRUNK_DIR and run 'svn status'"
