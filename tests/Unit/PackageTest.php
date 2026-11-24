<?php

namespace QrzLite\Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * Checks of the package files that Joomla would only report at install time.
 */
final class PackageTest extends TestCase
{
    private const SRC = __DIR__ . '/../../src';

    private static function files(string $pattern): array
    {
        $found    = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(self::SRC, \FilesystemIterator::SKIP_DOTS));

        foreach ($iterator as $file) {
            if ($file->isFile() && preg_match($pattern, $file->getFilename())) {
                $found[] = str_replace('\\', '/', $file->getPathname());
            }
        }

        sort($found);

        return $found;
    }

    public function testManifestsShareTheVersion(): void
    {
        $version = (string) simplexml_load_file(self::SRC . '/pkg_qrzlitesitemap.xml')->version;

        $this->assertMatchesRegularExpression('/^\d+\.\d+\.\d+$/', $version);

        foreach (['component', 'plugins/console', 'plugins/task'] as $folder) {
            $this->assertSame($version, (string) simplexml_load_file(self::SRC . '/' . $folder . '/qrzlitesitemap.xml')->version, $folder);
        }
    }

    public function testManifestFoldersExist(): void
    {
        $component = simplexml_load_file(self::SRC . '/component/qrzlitesitemap.xml');

        foreach ([['site', $component->files], ['administrator', $component->administration->files]] as [$base, $files]) {
            foreach ($files->folder as $folder) {
                $this->assertDirectoryExists(self::SRC . '/component/' . $base . '/' . $folder);
            }

            foreach ($files->filename as $file) {
                $this->assertFileExists(self::SRC . '/component/' . $base . '/' . $file);
            }
        }

        foreach ($component->media->folder as $folder) {
            $this->assertDirectoryExists(self::SRC . '/component/media/' . $folder);
        }

        foreach (['console', 'task'] as $plugin) {
            foreach (simplexml_load_file(self::SRC . '/plugins/' . $plugin . '/qrzlitesitemap.xml')->files->folder as $folder) {
                $this->assertDirectoryExists(self::SRC . '/plugins/' . $plugin . '/' . $folder);
            }
        }
    }

    public function testXmlFilesAreWellFormed(): void
    {
        foreach (self::files('/\.(xml|xsl)$/') as $file) {
            $dom = new \DOMDocument();
            $this->assertTrue(@$dom->load($file), $file);
        }
    }

    public function testStylesheetsTransformASitemap(): void
    {
        $sitemap = new \DOMDocument();
        $sitemap->loadXML('<?xml version="1.0"?><urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            . '<url><loc>https://example.com/a.html</loc><lastmod>2026-01-02T03:04:05+00:00</lastmod></url></urlset>');

        if (!class_exists(\XSLTProcessor::class)) {
            $this->markTestSkipped('The xsl extension is not installed');
        }

        foreach (self::files('/\.xsl$/') as $file) {
            $xsl = new \DOMDocument();
            $xsl->load($file);
            $processor = new \XSLTProcessor();
            $processor->importStylesheet($xsl);
            $html = $processor->transformToXml($sitemap);

            $this->assertStringContainsString('href="https://example.com/a.html"', (string) $html, $file);
            $this->assertStringContainsString('2026-01-02 03:04', (string) $html, $file);
        }
    }

    public function testPhpFilesAreProtectedAgainstDirectAccess(): void
    {
        foreach (self::files('/\.php$/') as $file) {
            $this->assertStringContainsString("\\defined('_JEXEC') or die;", (string) file_get_contents($file), $file);
        }
    }

    public function testLanguageFilesParseAndMatch(): void
    {
        $ini = self::files('/\.ini$/');
        $this->assertNotEmpty($ini);

        foreach ($ini as $file) {
            $strings = parse_ini_file($file, false, INI_SCANNER_RAW);
            $this->assertIsArray($strings, $file);
            $this->assertNotEmpty($strings, $file);

            // Every uk-UA file has the same keys as its en-GB file.
            if (str_contains($file, '/en-GB/')) {
                $other = parse_ini_file(str_replace('/en-GB/', '/uk-UA/', $file), false, INI_SCANNER_RAW);
                $this->assertSame(array_keys($strings), array_keys((array) $other), $file);
            }
        }
    }

    public function testLanguageKeysUsedInCodeExist(): void
    {
        $keys = [];

        foreach (self::files('/\.ini$/') as $file) {
            $keys += parse_ini_file($file, false, INI_SCANNER_RAW) ?: [];
        }

        foreach (self::files('/\.(php|xml)$/') as $file) {
            preg_match_all('/\b((?:COM|PLG)_QRZLITESITEMAP\w*|PLG_(?:TASK|CONSOLE)_QRZLITESITEMAP\w*)\b/', (string) file_get_contents($file), $matches);

            foreach (array_unique($matches[1]) as $key) {
                // The task plugin builds TITLE and DESC keys from this prefix.
                if ($key === 'PLG_TASK_QRZLITESITEMAP_GENERATE') {
                    $this->assertArrayHasKey($key . '_TITLE', $keys);
                    $this->assertArrayHasKey($key . '_DESC', $keys);
                    continue;
                }

                $this->assertArrayHasKey($key, $keys, $file);
            }
        }
    }
}
