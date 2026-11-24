#!/bin/bash
# Installs the package into a Joomla site that has the test content (setup-content.php) and checks the three
# ways of generating the sitemap and the HTML sitemap page.
#
#   tests/Integration/smoke.sh /path/to/joomla dist/pkg_qrzlitesitemap-x.y.z.zip http://127.0.0.1:8765
#
# The site's live_site must be the given URL; the built-in PHP web server is started on it.
set -euo pipefail
site=$(realpath "$1"); pkg=$(realpath "$2"); url=$3
here=$(cd "$(dirname "$0")" && pwd)
joomla() { php "$site/cli/joomla.php" "$@"; }
fail() { echo "FAIL: $*" >&2; exit 1; }

# The URLs of sitemap.xml as decoded paths, sorted
paths() {
  php -r '$x = simplexml_load_file($argv[1]); foreach ($x->url as $u) echo rawurldecode(substr((string) $u->loc, strlen($argv[2]))), "\n";' \
    "$site/sitemap.xml" "$url" | LC_ALL=C sort
}
check() {
  diff <(LC_ALL=C sort "$here/expected-urls.txt") <(paths) || fail "$1: unexpected URLs"
  php -r 'libxml_use_internal_errors(true); $d = new DOMDocument(); $d->load($argv[1]); exit($d->schemaValidate($argv[2]) ? 0 : 1);' \
    "$site/sitemap.xml" "$here/../fixtures/sitemap.xsd" || fail "$1: sitemap.xml is not valid"
  echo "ok: $1"
}

joomla extension:install --path="$pkg" -n | tail -1
joomla extension:list --type=plugin -n | grep -q qrzlitesitemap || fail "plugins not installed"

# 1. Console command
joomla qrzlite-sitemap:generate -v
check "console command"

# 2. Dry run changes nothing
before=$(stat -c %Y "$site/sitemap.xml"); sleep 1
joomla qrzlite-sitemap:generate --dry-run | tail -2
[ "$(stat -c %Y "$site/sitemap.xml")" = "$before" ] || fail "dry run wrote the file"
echo "ok: dry run"

# 3. Joomla Scheduler from the console
rm "$site/sitemap.xml"
task=$(php "$here/add-task.php" "$site")
joomla scheduler:run --id="$task" -n | tail -2
check "scheduled task"

# 4. HTML sitemap page and the files served by the web server
php -S "${url#http://}" -t "$site" "$here/router.php" > /tmp/qrzlite-php-server.log 2>&1 &
server=$!
trap 'kill $server 2>/dev/null || true' EXIT
sleep 2
page=$(curl -sSL "$url/index.php?option=com_qrzlitesitemap&view=html")
for path in /about /news/first-news /news/sub-news/sub-article; do
  grep -q "href=\"$path\"" <<< "$page" || fail "HTML sitemap has no link to $path"
done
grep -q 'noindex-article' <<< "$page" && fail "HTML sitemap lists a noindex article"
curl -sSf "$url/sitemap.xml" | grep -q '<urlset' || fail "sitemap.xml is not served"
curl -sSf "$url/media/com_qrzlitesitemap/xsl/sitemap-en-GB.xsl" | grep -q 'xsl:stylesheet' || fail "stylesheet is not served"
echo "ok: HTML sitemap page"

echo "All integration checks passed"
