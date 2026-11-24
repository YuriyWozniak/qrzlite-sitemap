<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Sitemap;

\defined('_JEXEC') or die;

/**
 * Writes the sitemap files into a folder: <name>.xml with all URLs, or, above the split limit, <name>_1.xml,
 * <name>_2.xml, ... and a sitemap index in <name>.xml. Split files left over from an earlier, longer sitemap
 * are deleted.
 */
final class Publisher
{
    /** The protocol allows at most 50,000 URLs per file. */
    public const MAX_URLS = 50000;

    private int $splitLimit;

    public function __construct(
        private readonly FileWriter $writer,
        private readonly SitemapXml $xml,
        int $splitLimit = self::MAX_URLS
    ) {
        $this->splitLimit = max(1, min(self::MAX_URLS, $splitLimit));
    }

    /**
     * @param   string              $folder    Folder to write into (the site root).
     * @param   string              $name      File name without .xml, e.g. "sitemap".
     * @param   string              $baseUrl   Site URL, e.g. https://example.com or https://example.com/sub
     * @param   Entry[]             $entries   URLs to list.
     * @param   \DateTimeImmutable  $now       Time of writing (lastmod of the split files).
     *
     * @return  array{files: string[], mode: string, deleted: string[], kept: string[]}  Written files, write mode
     *          of the main file, deleted leftovers and leftovers that could not be deleted.
     */
    public function publish(string $folder, string $name, string $baseUrl, array $entries, \DateTimeImmutable $now): array
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $name)) {
            throw new \InvalidArgumentException('Invalid sitemap file name: ' . $name);
        }

        if (!preg_match('#^https?://[^/?\#\s]+(/[^?\#\s]*)?$#i', $baseUrl)) {
            throw new \InvalidArgumentException('Invalid site URL: ' . $baseUrl);
        }

        if ($entries === []) {
            // Keep the old sitemap rather than publishing an empty one.
            throw new \RuntimeException('No URLs to write; the sitemap was not changed');
        }

        $folder  = rtrim($folder, '/\\');
        $baseUrl = rtrim($baseUrl, '/');
        $chunks  = array_chunk(array_values($entries), $this->splitLimit);
        $files   = [];

        if (\count($chunks) === 1) {
            $main = $folder . '/' . $name . '.xml';
            $mode = $this->writer->write($main, $this->xml->urlset($baseUrl, $chunks[0]));
            $files[] = $main;
        } else {
            $locs = [];

            foreach ($chunks as $i => $chunk) {
                $file    = $folder . '/' . $name . '_' . ($i + 1) . '.xml';
                $this->writer->write($file, $this->xml->urlset($baseUrl, $chunk));
                $files[] = $file;
                $locs[]  = $baseUrl . '/' . $name . '_' . ($i + 1) . '.xml';
            }

            // The index goes last, so it never points to a file that is not written yet.
            $main    = $folder . '/' . $name . '.xml';
            $mode    = $this->writer->write($main, $this->xml->index($locs, $now));
            $files[] = $main;
        }

        $deleted = [];
        $kept    = [];
        $parts   = \count($chunks) === 1 ? 0 : \count($chunks);

        foreach (glob($folder . '/' . $name . '_*.xml') ?: [] as $file) {
            if (preg_match('/_(\d+)\.xml$/', $file, $match) && (int) $match[1] > $parts) {
                if ($this->writer->delete($file)) {
                    $deleted[] = $file;
                } else {
                    $kept[] = $file;
                }
            }
        }

        return ['files' => $files, 'mode' => $mode, 'deleted' => $deleted, 'kept' => $kept];
    }
}
