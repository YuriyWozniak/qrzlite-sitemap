<?php

namespace QrzLite\Tests\Unit;

use PHPUnit\Framework\TestCase;
use QrzLite\Component\Sitemap\Administrator\Sitemap\CategoryTree;
use QrzLite\Component\Sitemap\Administrator\Sitemap\Context;

final class CategoryTreeTest extends TestCase
{
    private function tree(array $rows, ?array $languages = null, bool $archived = false): CategoryTree
    {
        return new CategoryTree($rows, new Context([1, 5], new \DateTimeImmutable('2026-06-15'), '', $languages, $archived));
    }

    private static function row(int $id, int $parent, int $state = 1, int $access = 1, string $language = '*'): array
    {
        return ['id' => $id, 'parent_id' => $parent, 'published' => $state, 'access' => $access, 'language' => $language];
    }

    public function testPublishedChainIsReachable(): void
    {
        $tree = $this->tree([self::row(2, 1), self::row(3, 2), self::row(4, 3)]);

        $this->assertTrue($tree->has(4));
        $this->assertNull($tree->reason(4));
    }

    public function testUnpublishedParentHidesChildren(): void
    {
        $tree = $this->tree([self::row(2, 1, 0), self::row(3, 2), self::row(4, 3)]);

        $this->assertSame('parent category #2 unpublished', $tree->reason(4));
        $this->assertSame('category unpublished', $tree->reason(2));
    }

    public function testRegisteredParentHidesChildren(): void
    {
        $tree = $this->tree([self::row(2, 1, 1, 2), self::row(3, 2)]);

        $this->assertSame('parent category #2 not public (access level 2)', $tree->reason(3));
    }

    public function testArchivedParentFollowsTheOption(): void
    {
        $rows = [self::row(2, 1, 2), self::row(3, 2)];

        $this->assertSame('parent category #2 archived', $this->tree($rows)->reason(3));
        $this->assertNull($this->tree($rows, null, true)->reason(3));
    }

    public function testLanguageOfParent(): void
    {
        $tree = $this->tree([self::row(2, 1, 1, 1, 'ru-RU'), self::row(3, 2, 1, 1, '*')], ['uk-UA']);

        $this->assertSame('parent category #2 language ru-RU not published', $tree->reason(3));
    }

    public function testMissingCategory(): void
    {
        $this->assertSame('category #9 not found', $this->tree([])->reason(9));
    }

    public function testCycleDoesNotLoop(): void
    {
        $tree = $this->tree([self::row(2, 3), self::row(3, 2)]);

        $this->assertNull($tree->reason(2));
    }
}
