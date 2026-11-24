<?php

namespace QrzLite\Tests\Unit\Plugin;

use Joomla\CMS\Application\ConsoleApplication;
use QrzLite\Component\Sitemap\Administrator\Extension\SitemapComponent;
use QrzLite\Component\Sitemap\Administrator\Storage\LinkRepository;
use QrzLite\Plugin\Console\Sitemap\Command\GenerateCommand;
use QrzLite\Tests\Support\JoomlaTestCase;
use QrzLite\Tests\Support\SiteContent;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Console\Output\OutputInterface;

final class GenerateCommandTest extends JoomlaTestCase
{
    use SiteContent;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seedSite();
        $this->component(SitemapComponent::NAME);
        $this->app(ConsoleApplication::class, ['live_site' => self::SITE_URL]);
    }

    /**
     * @param   array<string, mixed>  $options
     *
     * @return  array{int, string}  Exit code and output.
     */
    private function command(array $options = [], int $verbosity = OutputInterface::VERBOSITY_NORMAL): array
    {
        $output = new BufferedOutput($verbosity);
        $code   = (new GenerateCommand())->execute(new ArrayInput($options), $output);

        // One line per message, however wide the terminal is.
        return [$code, preg_replace('/\s+/', ' ', $output->fetch())];
    }

    public function testDescription(): void
    {
        $command = new GenerateCommand();

        $this->assertSame('qrzlite-sitemap:generate', $command->getName());
        $this->assertSame(['dry-run', 'output', 'options'], array_keys($command->getDefinition()->getOptions()));
        $this->assertStringContainsString('--dry-run -v', $command->getProcessedHelp());
    }

    public function testGenerates(): void
    {
        [$code, $output] = $this->command();

        $this->assertSame(0, $code);
        $this->assertMatchesRegularExpression('/\[OK\] 4 URLs, \d+ left out, written to sitemap\.xml \(atomic\) in \d+\.\d s/', $output);
        $this->assertFileExists(JPATH_ROOT . '/sitemap.xml');
        $this->assertCount(4, $this->rows(LinkRepository::TABLE));
        $this->assertStringNotContainsString('Left out', $output);
    }

    public function testDryRunListsWhatIsLeftOutAndTheUrls(): void
    {
        [$code, $output] = $this->command(['--dry-run' => true], OutputInterface::VERBOSITY_VERY_VERBOSE);

        $this->assertSame(0, $code);
        $this->assertFileDoesNotExist(JPATH_ROOT . '/sitemap.xml');
        $this->assertSame([], $this->rows(LinkRepository::TABLE));
        $this->assertStringContainsString('* https://example.com/news/first', $output);
        $this->assertStringContainsString('Left out', $output);
        $this->assertStringContainsString('article #3 Draft unpublished', $output);
        $this->assertMatchesRegularExpression('/\[OK\] Dry run: 4 URLs/', $output);
    }

    public function testOutputFolder(): void
    {
        mkdir(JPATH_ROOT . '/out');

        [$code] = $this->command(['--output' => JPATH_ROOT . '/out']);

        $this->assertSame(0, $code);
        $this->assertFileExists(JPATH_ROOT . '/out/sitemap.xml');
        $this->assertSame([], $this->rows(LinkRepository::TABLE));
    }

    public function testMissingOutputFolder(): void
    {
        [$code, $output] = $this->command(['--output' => JPATH_ROOT . '/missing']);

        $this->assertSame(1, $code);
        $this->assertStringContainsString('[ERROR] No such folder:', $output);
    }

    public function testOptionsFile(): void
    {
        file_put_contents(JPATH_ROOT . '/options.json', json_encode(['filename' => 'map', 'include_articles' => 0]));

        [$code] = $this->command(['--options' => JPATH_ROOT . '/options.json']);

        $this->assertSame(0, $code);
        $this->assertFileExists(JPATH_ROOT . '/map.xml');
        $this->assertCount(2, $this->rows(LinkRepository::TABLE));
    }

    public function testUnreadableOptionsFile(): void
    {
        file_put_contents(JPATH_ROOT . '/options.json', '[1, 2');

        [$code, $output] = $this->command(['--options' => JPATH_ROOT . '/options.json']);
        $this->assertSame(1, $code);
        $this->assertStringContainsString('[ERROR] Cannot read the options file', $output);

        [$code] = $this->command(['--options' => JPATH_ROOT . '/none.json']);
        $this->assertSame(1, $code);
    }

    public function testFailure(): void
    {
        $this->app(ConsoleApplication::class);
        $_SERVER['HTTP_HOST'] = 'joomla.invalid';

        [$code, $output] = $this->command();

        $this->assertSame(1, $code);
        $this->assertStringContainsString('[ERROR] The site URL is not known', $output);
        $this->assertFileDoesNotExist(JPATH_ROOT . '/sitemap.xml');
    }
}
