<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Storage;

\defined('_JEXEC') or die;

/**
 * Where the sitemap files are: <folder>/<name>.xml, and the parts <name>_1.xml, <name>_2.xml, ... of a split
 * sitemap. The only class that knows the file names.
 */
final class SitemapFiles
{
    public readonly string $folder;

    /**
     * @param   string  $folder  Folder of the files (the site root).
     * @param   string  $name    File name without .xml, e.g. "sitemap".
     *
     * @throws  \InvalidArgumentException  For a name that is not a plain file name.
     */
    public function __construct(string $folder, public readonly string $name)
    {
        if (!preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $name)) {
            throw new \InvalidArgumentException('Invalid sitemap file name: ' . $name);
        }

        $this->folder = rtrim($folder, '/\\');
    }

    public function main(): string
    {
        return $this->folder . '/' . $this->name . '.xml';
    }

    public function part(int $number): string
    {
        return $this->folder . '/' . $this->name . '_' . $number . '.xml';
    }

    /**
     * Parts on disk, by number. Other files that start with the name ("sitemap_old.xml") are not parts.
     *
     * @return  array<int, string>
     */
    public function parts(): array
    {
        $parts = [];

        foreach (glob($this->folder . '/' . $this->name . '_*.xml') ?: [] as $file) {
            if (preg_match('/^' . preg_quote($this->name, '/') . '_([1-9]\d*)\.xml$/', basename($file), $match)) {
                $parts[(int) $match[1]] = $file;
            }
        }

        ksort($parts);

        return $parts;
    }

    /**
     * The main file and the parts that exist, in that order.
     *
     * @return  string[]
     */
    public function existing(): array
    {
        $main = $this->main();

        return array_merge(is_file($main) ? [$main] : [], array_values($this->parts()));
    }
}
