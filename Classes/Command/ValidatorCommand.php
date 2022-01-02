<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Command;

use Ayacoo\VideoValidator\Service\VideoService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
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
        $this->addArgument(
            'extension',
            InputArgument::REQUIRED,
            'e.g. Youtube',
        );
        $this->addArgument(
            'limit',
            InputArgument::REQUIRED,
            'Number of videos to be checked',
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
        $extension = $input->getArgument('extension');

        $allowedExtensions = array_keys($GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['onlineMediaHelpers'] ?? []);
        if (in_array(strtolower($extension), $allowedExtensions, true)) {
            $io->info(
                $this->localizationUtility::translate('validation.start', 'video_validator')
            );
            $this->videoService->setIo($io);
            $this->videoService->setExtension($extension);
            $this->videoService->setLimit((int)$input->getArgument('limit'));
            $this->videoService->validate();
            $io->info(
                $this->localizationUtility::translate('validation.end', 'video_validator')
            );
        } else {
            $io->warning(
                $this->localizationUtility::translate('validation.extension.noSupport', 'video_validator')
            );
        }

        return 0;
    }
}
