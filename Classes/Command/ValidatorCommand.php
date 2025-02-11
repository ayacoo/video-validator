<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Command;

use Ayacoo\VideoValidator\Domain\Dto\ValidatorDemand;
use Ayacoo\VideoValidator\Service\VideoService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ValidatorCommand extends Command
{
    protected function configure(): void
    {
        $this->setDescription('Video validation of a defined media extension (e.g. YouTube)');
        $this->addOption(
            'extension',
            null,
            InputOption::VALUE_REQUIRED,
            'Media Extension (e.g. YouTube)',
            'YouTube'
        );
        $this->addOption(
            'limit',
            null,
            InputOption::VALUE_REQUIRED,
            'Number of videos to be checked',
            10
        );
        $this->addOption(
            'referencedOnly',
            null,
            InputOption::VALUE_OPTIONAL,
            'Whether to only fetch records that are referenced on visible pages and content elements (1/0)',
            0
        );
        $this->addOption(
            'referenceRoot',
            null,
            InputOption::VALUE_OPTIONAL,
            'Pagetree root where to search references. Defaults to 0 (all root nodes)',
            0
        );
    }

    public function __construct(
        protected LocalizationUtility $localizationUtility,
        protected VideoService $videoService
    ) {
        parent::__construct();
    }
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $extension = $input->getOption('extension');

        $allowedExtensions = array_keys($GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['onlineMediaHelpers'] ?? []);
        if (in_array(strtolower($extension), $allowedExtensions, true)) {
            if ((bool)$input->getOption('referencedOnly')) {
                $io->info(
                    $this->localizationUtility::translate('validation.startReferencedOnly', 'video_validator')
                );
            } else {
                $io->info(
                    $this->localizationUtility::translate('validation.start', 'video_validator')
                );
            }

            $validatorDemand = new ValidatorDemand();
            $validatorDemand->setExtension($extension);
            $validatorDemand->setLimit((int)$input->getOption('limit'));
            $validatorDemand->setReferencedOnly((bool)$input->getOption('referencedOnly'));
            $validatorDemand->setReferenceRoot((int)$input->getOption('referenceRoot'));

            $this->videoService->setIo($io);
            $this->videoService->validate($validatorDemand);

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
