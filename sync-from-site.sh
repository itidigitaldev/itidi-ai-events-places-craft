#!/usr/bin/env bash
set -euo pipefail

if [ -z "${1:-}" ]; then
  echo "Usage: bash sync-from-site.sh \"Commit message describing what changed\"" >&2
  exit 1
fi

MESSAGE="$1"
SITE_REPO="/d/Documents/ITI/craftcms"
DIST_REPO="/d/Documents/ITI/craft-iti-plugin-dist"

cd "$DIST_REPO"

TMP_TAR="$(mktemp)"
git -C "$SITE_REPO" archive --format=tar HEAD:plugins/craft-iti-plugin > "$TMP_TAR"

# Clear the working tree (except .git and this script) before extracting,
# so files removed on the site side are removed here too.
find . -mindepth 1 -maxdepth 1 ! -name '.git' ! -name 'sync-from-site.sh' -exec rm -rf {} +

tar -xf "$TMP_TAR" -C "$DIST_REPO"
rm -f "$TMP_TAR"

# git archive from a Windows checkout comes out CRLF; this dist repo is LF-only.
find . -type f \
  -not -path './.git/*' \
  -not -name '*.png' -not -name '*.jpg' -not -name '*.jpeg' -not -name '*.gif' \
  -exec sed -i 's/\r$//' {} +

git add -A

if git diff --cached --quiet; then
  echo "Nothing changed — dist repo already matches the site's plugin copy."
  exit 0
fi

git commit -m "$MESSAGE"
git push origin main
