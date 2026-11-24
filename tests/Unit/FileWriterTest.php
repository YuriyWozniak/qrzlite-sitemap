<?php

namespace QrzLite\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QrzLite\Component\Sitemap\Administrator\Sitemap\FileWriter;

/**
 * Failures of the file writer that do not depend on file permissions (those are in PublisherTest).
 */
final class FileWriterTest extends TestCase
{
    private string $dir;

    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/qrzlite-writer-' . getmypid();
        mkdir($this->dir);
    }

    protected function tearDown(): void
    {
        foreach (array_merge(glob($this->dir . '/*/*') ?: [], glob($this->dir . '/*') ?: []) as $path) {
            is_dir($path) ? rmdir($path) : unlink($path);
        }

        rmdir($this->dir);
    }

    public function testUnknownMode(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new FileWriter())->write($this->dir . '/a.xml', 'x', 'sideways');
    }

    public function testAtomicWriteIntoAMissingFolder(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot write ' . $this->dir . '/missing/a.xml.tmp-');

        (new FileWriter())->write($this->dir . '/missing/a.xml', 'x', FileWriter::ATOMIC);
    }

    public function testAtomicWriteOverAFolderLeavesNoTemporaryFile(): void
    {
        mkdir($this->dir . '/a.xml');

        try {
            (new FileWriter())->write($this->dir . '/a.xml', 'x');
            $this->fail('No exception');
        } catch (\RuntimeException $e) {
            $this->assertSame('Cannot replace ' . $this->dir . '/a.xml', $e->getMessage());
        }

        $this->assertSame([$this->dir . '/a.xml'], glob($this->dir . '/*'));
    }

    public function testInPlaceWriteIntoAMissingFolder(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot open ' . $this->dir . '/missing/a.xml');

        (new FileWriter())->write($this->dir . '/missing/a.xml', 'x', FileWriter::IN_PLACE);
    }

    public function testInPlaceWriteReplacesTheContents(): void
    {
        file_put_contents($this->dir . '/a.xml', 'a much longer old content');

        $this->assertSame(FileWriter::IN_PLACE, (new FileWriter())->write($this->dir . '/a.xml', 'new', FileWriter::IN_PLACE));
        $this->assertSame('new', file_get_contents($this->dir . '/a.xml'));
    }

    public function testDeletingAMissingFileIsFine(): void
    {
        $this->assertTrue((new FileWriter())->delete($this->dir . '/none.xml'));
    }
}
