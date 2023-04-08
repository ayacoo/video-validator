<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Command;

use Ayacoo\VideoValidator\Domain\Dto\ValidatorDemand;
use Ayacoo\VideoValidator\Domain\Repository\FileRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class CountCommand extends Command
{
    protected function configure(): void
    {
        $this->setDescription('Counts all videos of a media extension, e.g. YouTube');
        $this->addOption(
            'extension',
            null,
            InputOption::VALUE_REQUIRED,
            'Media Extension (e.g. YouTube)',
            'YouTube'
        );
    }

    public function __construct(
        protected LocalizationUtility $localizationUtility,
        protected FileRepository      $fileRepository
    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $extension = $input->getOption('extension');

        $allowedExtensions = array_keys($GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['onlineMediaHelpers'] ?? []);
        if (in_array(strtolower($extension), $allowedExtensions, true)) {
            $validatorDemand = new ValidatorDemand();
            $validatorDemand->setExtension($extension);
            $numberOfVideos = count($this->fileRepository->getVideosByExtension($validatorDemand, time()));
            $io->info(
                $this->localizationUtility::translate('count.numberOfVideos', 'video_validator') .
                ' ' . $extension . ': ' . $numberOfVideos
            );
        } else {
            $io->warning(
                $this->localizationUtility::translate('validation.extension.noSupport', 'video_validator')
            );
        }

        return Command::SUCCESS;
    }
}
