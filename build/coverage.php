<?php

/**
 * Fails when the unit tests cover less than the given share of the statements in src/.
 *
 *   php build/coverage.php .cache/clover.xml 95
 *
 * Lists the statements that no test ran.
 */

[, $file, $minimum] = $argv + [null, '', '95'];

$xml = @simplexml_load_file((string) $file);

if ($xml === false) {
    fwrite(STDERR, "Usage: php build/coverage.php <clover.xml> <minimum %>\n");
    exit(2);
}

$total   = 0;
$covered = 0;

foreach ($xml->xpath('//file') as $source) {
    $missed = [];

    foreach ($source->line as $line) {
        if ((string) $line['type'] !== 'stmt') {
            continue;
        }

        $total++;

        if ((int) $line['count'] > 0) {
            $covered++;
        } else {
            $missed[] = (int) $line['num'];
        }
    }

    if ($missed) {
        echo 'Not run: ', str_replace('\\', '/', (string) $source['name']), ' lines ', implode(', ', $missed), "\n";
    }
}

$percent = $total ? 100 * $covered / $total : 0;
printf("Statements covered: %d of %d (%.2f%%), minimum %s%%\n", $covered, $total, $percent, $minimum);

exit($percent + 1e-9 >= (float) $minimum ? 0 : 1);
