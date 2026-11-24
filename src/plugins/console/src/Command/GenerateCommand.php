<?php

/**
 * @package     QrzLite Sitemap
 * @copyright   (C) 2026 Yuriy Wozniak
 * @license     GNU General Public License version 2 or later; see LICENSE
 */

namespace QrzLite\Plugin\Console\Sitemap\Command;

\defined('_JEXEC') or die;

use Joomla\Console\Command\AbstractCommand;
use Joomla\Registry\Registry;
use QrzLite\Component\Sitemap\Administrator\Service\SitemapFactory;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * php cli/joomla.php qrzlite-sitemap:generate
 */
class GenerateCommand extends AbstractCommand
{
    /**
     * @var  string
     */
    protected static $defaultName = 'qrzlite-sitemap:generate';

    public function __construct(private readonly SitemapFactory $factory)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->setDescription('Generate the XML sitemap and the links of the HTML sitemap page');
        $this->addOption('dry-run', null, InputOption::VALUE_NONE, 'Only build the list: write no files, save no links');
        $this->addOption('output', null, InputOption::VALUE_REQUIRED, 'Write the files into this folder instead of the site root (links are not saved)');
        $this->addOption('options', null, InputOption::VALUE_REQUIRED, 'JSON file with sitemap options that replace the saved ones for this run');
        $this->setHelp(
            <<<'EOF'
The <info>%command.name%</info> command writes sitemap.xml into the site root and saves the links shown on the HTML
sitemap page. The site URL comes from the component options, else live_site, else the --live-site option.

  <info>php %command.full_name%</info>
  <info>php %command.full_name% --dry-run -v</info>     list what is left out and why
  <info>php %command.full_name% --dry-run -vv</info>    also list the URLs
EOF
        );
    }

    protected function doExecute(InputInterface $input, OutputInterface $output): int
    {
        $io     = new SymfonyStyle($input, $output);
        $dryRun = (bool) $input->getOption('dry-run');
        $folder = $input->getOption('output');

        try {
            $overrides = null;

            if ($file = $input->getOption('options')) {
                $json = @file_get_contents((string) $file);

                if ($json === false || !\is_array(json_decode($json, true))) {
                    throw new \RuntimeException('Cannot read the options file ' . $file);
                }

                $overrides = new Registry($json);
            }

            if ($folder !== null && !is_dir((string) $folder)) {
                throw new \RuntimeException('No such folder: ' . $folder);
            }

            $result = $this->factory->createGenerator($overrides)->generate(!$dryRun, $folder === null ? null : (string) $folder);
        } catch (\Throwable $e) {
            $io->error($e->getMessage());

            return 1;
        }

        if ($output->isVeryVerbose()) {
            $io->section('URLs');
            $io->listing(array_map(static fn ($entry) => $result->siteUrl . $entry->path, $result->entries));
        }

        if ($output->isVerbose() && $result->exclusions) {
            $io->section('Left out');
            $io->table(['Item', 'Reason'], $result->exclusions);
        }

        $io->success(($dryRun ? 'Dry run: ' : '') . $result->summary());

        return 0;
    }
}
