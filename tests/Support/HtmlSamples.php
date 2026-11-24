<?php

namespace QrzLite\Tests\Support;

/**
 * HTML that the tests render, kept for the HTML and CSS linters (npm run lint) when QRZLITE_HTML_OUT names
 * a folder:
 *
 *   QRZLITE_HTML_OUT=.cache/html vendor/bin/phpunit
 */
final class HtmlSamples
{
    public static function save(string $name, string $html): void
    {
        $folder = getenv('QRZLITE_HTML_OUT') ?: '';

        if ($folder === '') {
            return;
        }

        if (!is_dir($folder)) {
            mkdir($folder, 0777, true);
        }

        // A layout gives a part of a page: put it into a minimal page of its own.
        if (!str_starts_with(ltrim($html), '<!DOCTYPE')) {
            $html = "<!DOCTYPE html>\n<html lang=\"en\">\n<head>\n<meta charset=\"utf-8\">\n<title>" . $name . "</title>\n</head>\n<body>\n"
                . $html . "\n</body>\n</html>\n";
        }

        file_put_contents($folder . '/' . $name . '.html', $html);
    }
}
