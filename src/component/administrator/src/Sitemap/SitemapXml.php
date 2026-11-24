<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Component\Sitemap\Administrator\Sitemap;

\defined('_JEXEC') or die;

/**
 * Writes sitemap XML (https://www.sitemaps.org/protocol.html): a urlset with the URLs, or a sitemap index that
 * lists several urlset files. Elements follow the order of the official schema: loc, lastmod, changefreq, priority.
 */
final class SitemapXml
{
    public const NAMESPACE = 'http://www.sitemaps.org/schemas/sitemap/0.9';

    public const CHANGEFREQS = ['always', 'hourly', 'daily', 'weekly', 'monthly', 'yearly', 'never'];

    /**
     * URLs written before the writer's buffer is emptied. libxml grows that buffer by small steps, so a file
     * written in one piece takes time that grows with the square of its size: 50,000 URLs took 2.6 s instead
     * of 0.2 s.
     */
    private const URLS_PER_PIECE = 1000;

    private string $priority;

    /**
     * @param   string               $changefreq  One of CHANGEFREQS, '' to leave it out.
     * @param   string               $priority    0.0 to 1.0, '' to leave it out.
     * @param   ?string              $stylesheet  URL of an XSL stylesheet for viewing in a browser, null for none.
     * @param   ?\DateTimeImmutable  $generated   Time written into a comment, null for no comment.
     */
    public function __construct(
        private readonly string $changefreq = '',
        string $priority = '',
        private readonly ?string $stylesheet = null,
        private readonly ?\DateTimeImmutable $generated = null
    ) {
        if ($changefreq !== '' && !\in_array($changefreq, self::CHANGEFREQS, true)) {
            throw new \InvalidArgumentException('Invalid changefreq: ' . $changefreq);
        }

        if ($priority !== '' && (!is_numeric($priority) || (float) $priority < 0 || (float) $priority > 1)) {
            throw new \InvalidArgumentException('Invalid priority: ' . $priority);
        }

        $this->priority = $priority === '' ? '' : number_format((float) $priority, 1, '.', '');
    }

    /**
     * Absolute, RFC 3986 encoded URL of a path: non-ASCII characters, spaces and other unsafe characters are
     * percent-encoded, existing %XX escapes are kept.
     */
    public static function loc(string $baseUrl, string $path): string
    {
        $path = preg_replace_callback(
            '/%(?![0-9A-Fa-f]{2})|[^A-Za-z0-9\-._~:\/?#\[\]@!$&\'()*+,;=%]/',
            static fn (array $match): string => rawurlencode($match[0]),
            $path
        );

        return rtrim($baseUrl, '/') . $path;
    }

    public static function w3cDate(\DateTimeImmutable $date): string
    {
        return $date->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:sP');
    }

    /**
     * @param   string   $baseUrl  Site URL without the trailing slash, e.g. https://example.com
     * @param   Entry[]  $entries
     */
    public function urlset(string $baseUrl, array $entries): string
    {
        $xml     = $this->start('urlset');
        $pieces  = [];
        $written = 0;

        foreach ($entries as $entry) {
            if (++$written % self::URLS_PER_PIECE === 0) {
                $pieces[] = $xml->outputMemory();
            }

            $xml->startElement('url');
            $xml->writeElement('loc', self::loc($baseUrl, $entry->path));

            if ($entry->lastmod !== null) {
                $xml->writeElement('lastmod', self::w3cDate($entry->lastmod));
            }

            if ($this->changefreq !== '') {
                $xml->writeElement('changefreq', $this->changefreq);
            }

            if ($this->priority !== '') {
                $xml->writeElement('priority', $this->priority);
            }

            $xml->endElement();
        }

        return $this->end($xml, $pieces);
    }

    /**
     * @param   string[]            $locs     Absolute URLs of the urlset files.
     * @param   \DateTimeImmutable  $lastmod  When they were written.
     */
    public function index(array $locs, \DateTimeImmutable $lastmod): string
    {
        $xml = $this->start('sitemapindex');

        foreach ($locs as $loc) {
            $xml->startElement('sitemap');
            $xml->writeElement('loc', $loc);
            $xml->writeElement('lastmod', self::w3cDate($lastmod));
            $xml->endElement();
        }

        return $this->end($xml);
    }

    private function start(string $root): \XMLWriter
    {
        $xml = new \XMLWriter();
        $xml->openMemory();
        $xml->setIndent(true);
        $xml->setIndentString(' ');
        $xml->startDocument('1.0', 'UTF-8');

        if ($this->stylesheet !== null && $this->stylesheet !== '') {
            $xml->writePi(
                'xml-stylesheet',
                'type="text/xsl" href="' . htmlspecialchars($this->stylesheet, ENT_QUOTES | ENT_XML1, 'UTF-8') . '"'
            );
        }

        if ($this->generated !== null) {
            $xml->writeComment(' QrzLite Sitemap ' . self::w3cDate($this->generated) . ' ');
        }

        $xml->startElement($root);
        $xml->writeAttribute('xmlns', self::NAMESPACE);

        return $xml;
    }

    /**
     * @param   string[]  $pieces  What was taken from the buffer before.
     */
    private function end(\XMLWriter $xml, array $pieces = []): string
    {
        $xml->endElement();
        $xml->endDocument();
        $pieces[] = $xml->outputMemory();

        return implode('', $pieces);
    }
}
