<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Sitemap;

\defined('_JEXEC') or die;

/**
 * Writes a file so that readers never see a half-written sitemap:
 *  - atomic: when the folder is writable, into a temporary file next to it, then renamed over the old one;
 *  - in place: when only the file itself is writable (a read-only site root), rewritten under an exclusive lock.
 */
final class FileWriter
{
    public const ATOMIC   = 'atomic';
    public const IN_PLACE = 'in-place';

    /**
     * @param   ?string  $mode  ATOMIC, IN_PLACE or null to choose by the permissions.
     *
     * @return  string  The mode used.
     *
     * @throws  \RuntimeException
     */
    public function write(string $file, string $contents, ?string $mode = null): string
    {
        $mode ??= $this->mode($file);

        match ($mode) {
            self::ATOMIC   => $this->atomic($file, $contents),
            self::IN_PLACE => $this->inPlace($file, $contents),
            default        => throw new \InvalidArgumentException('Unknown write mode ' . $mode),
        };

        return $mode;
    }

    public function mode(string $file): string
    {
        if (is_writable(\dirname($file))) {
            return self::ATOMIC;
        }

        if (is_file($file) && is_writable($file)) {
            return self::IN_PLACE;
        }

        throw new \RuntimeException('Cannot write ' . $file . ': neither the file nor its folder is writable');
    }

    /**
     * Delete a file if it exists; false when it can't be deleted.
     */
    public function delete(string $file): bool
    {
        return !is_file($file) || @unlink($file);
    }

    private function atomic(string $file, string $contents): void
    {
        $temp = $file . '.tmp-' . bin2hex(random_bytes(6));

        try {
            if (@file_put_contents($temp, $contents) !== \strlen($contents)) {
                throw new \RuntimeException('Cannot write ' . $temp);
            }

            // Keep the permissions of the file being replaced.
            @chmod($temp, is_file($file) ? (fileperms($file) & 0777) : 0644);

            if (!@rename($temp, $file)) {
                throw new \RuntimeException('Cannot replace ' . $file);
            }
        } finally {
            if (is_file($temp)) {
                @unlink($temp);
            }
        }
    }

    private function inPlace(string $file, string $contents): void
    {
        $handle = @fopen($file, 'c');

        if ($handle === false) {
            throw new \RuntimeException('Cannot open ' . $file);
        }

        try {
            if (!flock($handle, LOCK_EX) || !ftruncate($handle, 0)) {
                throw new \RuntimeException('Cannot lock ' . $file);
            }

            $length  = \strlen($contents);
            $written = 0;

            while ($written < $length) {
                $bytes = fwrite($handle, substr($contents, $written));

                if ($bytes === false || $bytes === 0) {
                    throw new \RuntimeException('Cannot write ' . $file);
                }

                $written += $bytes;
            }

            fflush($handle);
        } finally {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }
}
