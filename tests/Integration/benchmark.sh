#!/bin/bash
# Measures the qrzlite-sitemap:generate console command on a site with the package installed, as the site
# grows (large-content.php): wall and CPU time, and peak memory as the largest resident set size of the PHP
# process. "Joomla's own" is the peak of a console command that does nothing (list). Prints a Markdown table.
#
#   tests/Integration/benchmark.sh /path/to/joomla 1000 10000 50000
#
# Needs GNU time (/usr/bin/time).
set -euo pipefail
site=$(realpath "$1"); shift
here=$(cd "$(dirname "$0")" && pwd)
work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

# Runs a command; prints "wall user system max-rss-KB"
measure() {
  /usr/bin/time -f '%e %U %S %M' -o "$work/time" "$@" > "$work/out" 2>&1 || { cat "$work/out" >&2; exit 1; }
  cat "$work/time"
}

# URLs in the written sitemap: in the split files when sitemap.xml is an index
urls() {
  if grep -q '<sitemapindex' "$site/sitemap.xml"; then
    cat "$site"/sitemap_*.xml | grep -c '<loc>'
  else
    grep -c '<loc>' "$site/sitemap.xml"
  fi
}

read -r _ _ _ joomla < <(measure php "$site/cli/joomla.php" list)

echo "| Articles | URLs | Wall | CPU (user + system) | CPU per URL | Peak memory | Above Joomla's own | Per URL |"
echo "|---:|---:|---:|---:|---:|---:|---:|---:|"

for articles in "$@"; do
  php "$here/large-content.php" "$site" "$articles" > /dev/null
  read -r wall user sys rss < <(measure php "$site/cli/joomla.php" qrzlite-sitemap:generate -n)
  count=$(urls)
  php -r '[, $a, $n, $wall, $user, $sys, $rss, $base] = $argv; $cpu = $user + $sys; $above = max(0, $rss - $base);
    printf("| %s | %s | %.1f s | %.1f s | %d µs | %.0f MB | %.0f MB | %.1f KB |\n", number_format($a), number_format($n), $wall, $cpu,
      round($cpu / $n * 1e6), $rss / 1024, $above / 1024, $above / $n);' \
    "$articles" "$count" "$wall" "$user" "$sys" "$rss" "$joomla"
done

php -r 'printf("\nJoomla'"'"'s own peak (console, no command): %.0f MB, PHP %s\n", $argv[1] / 1024, PHP_VERSION);' "$joomla"
