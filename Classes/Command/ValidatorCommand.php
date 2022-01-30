<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Command;

use Ayacoo\VideoValidator\Service\VideoService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ValidatorCommand extends Command
{
    private ?LocalizationUtility $localizationUtility;

    private ?VideoService $videoService;

    protected function configure(): void
    {
        $this->setDescription('Checks online videos in TYPO3 backend for accessibility like Youtube and Vimeo');
        $this->addOption(
            'extension',
            null,
            InputOption::VALUE_REQUIRED,
            'e.g. Youtube',
        );
        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_REQUIRED,
            'Number of videos to be checked',
        );
        $this->addOption(
            'referencedOnly',
            null,
            InputOption::VALUE_OPTIONAL,
            'Whether to only fetch records that are referenced on visible pages and content elements (true/false)',
            false
        );
        $this->addOption(
            'referenceRoot',
            null,
            InputOption::VALUE_OPTIONAL,
            'Pagetree root where to search references. Defaults to 0 (all root nodes)',
            0
        );
    }

    /**
     * @param LocalizationUtility|null $localizationUtility
     * @param VideoService|null $videoService
     */
    public function __construct(
        LocalizationUtility $localizationUtility = null,
        VideoService        $videoService = null
    )
    {
        $this->localizationUtility = $localizationUtility;
        $this->videoService = $videoService;
        parent::__construct();
    }

    /**
     * Executes validator
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     * @return int
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $extension = $input->getOption('extension');

        $allowedExtensions = array_keys($GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['onlineMediaHelpers'] ?? []);
        if (in_array(strtolower($extension), $allowedExtensions, true)) {
            if ($input->getOption('referencedOnly')) {
                $io->info(
                    $this->localizationUtility::translate('validation.startReferencedOnly', 'video_validator')
                );
            } else {
                $io->info(
                    $this->localizationUtility::translate('validation.start', 'video_validator')
                );
            }
            $this->videoService->setIo($io);
            $this->videoService->setExtension($extension);
            $this->videoService->setLimit((int)$input->getOption('limit'));
            $this->videoService->setReferencedOnly((bool)$input->getOption('referencedOnly'));
            $this->videoService->setReferenceRoot((int)$input->getOption('referenceRoot'));
            $this->videoService->validate();
            $io->info(
                $this->localizationUtility::translate('validation.end', 'video_validator')
            );
        } else {
            $io->warning(
                $this->localizationUtility::translate('validation.extension.noSupport', 'video_validator')
            );
        }

        return Command::SUCCESS;
    }
}
