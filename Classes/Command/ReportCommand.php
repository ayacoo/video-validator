<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Command;

use Ayacoo\VideoValidator\Domain\Dto\ValidatorDemand;
use Ayacoo\VideoValidator\Domain\Repository\FileRepository;
use Ayacoo\VideoValidator\Event\ModifyReportServiceEvent;
use Ayacoo\VideoValidator\Service\Report\EmailReportService;
use Ayacoo\VideoValidator\Service\VideoService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ReportCommand extends Command
{
    private ?ResourceFactory $resourceFactory;

    private ?FileRepository $fileRepository;

    private ?LocalizationUtility $localizationUtility;

    private ?EventDispatcherInterface $eventDispatcher;

    protected function configure(): void
    {
        $this->setDescription('Make a video report');
        $this->addOption(
            'recipients',
            null,
            InputOption::VALUE_REQUIRED,
            'Comma separated list of email recipients',
            ''
        );
        $this->addOption(
            'extension',
            null,
            InputOption::VALUE_REQUIRED,
            'Name of the video extension',
        );
        $this->addOption(
            'days',
            null,
            InputOption::VALUE_OPTIONAL,
            'Number of days',
            7
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
     * @param ResourceFactory|null $resourceFactory
     * @param FileRepository|null $fileRepository
     * @param LocalizationUtility|null $localizationUtility
     */
    public function __construct(
        ResourceFactory          $resourceFactory = null,
        FileRepository           $fileRepository = null,
        LocalizationUtility      $localizationUtility = null,
        EventDispatcherInterface $eventDispatcher = null
    )
    {
        $this->resourceFactory = $resourceFactory;
        $this->fileRepository = $fileRepository;
        $this->localizationUtility = $localizationUtility;
        $this->eventDispatcher = $eventDispatcher;
        parent::__construct();
    }

    /**
     * Generates video report
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     * @throws FileDoesNotExistException
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $validatorDemand = new ValidatorDemand();
        $validatorDemand->setDays((int)$input->getOption('days'));
        $validatorDemand->setRecipients(
            GeneralUtility::trimExplode(',', trim($input->getOption('recipients')))
        );
        $validatorDemand->setExtension(trim($input->getOption('extension')));
        $validatorDemand->setReferencedOnly((bool)$input->getOption('referencedOnly'));
        $validatorDemand->setReferenceRoot((int)$input->getOption('referenceRoot'));

        $sender = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] ?? '';
        if (empty($sender)) {
            $io->warning(
                $this->localizationUtility::translate('report.validMailAddress', 'video_validator')
            );
            return 0;
        }

        $allowedExtensions = array_keys($GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['onlineMediaHelpers'] ?? []);
        if (in_array(strtolower($validatorDemand->getExtension()), $allowedExtensions, true)) {
            $invalidVideos = $this->getVideosByStatus($validatorDemand, VideoService::STATUS_ERROR);
            $validVideos = $this->getVideosByStatus($validatorDemand, VideoService::STATUS_SUCCESS);
            if (count($invalidVideos) > 0 || count($validVideos) > 0) {
                $emailReportService = GeneralUtility::makeInstance(EmailReportService::class);

                // You don't want to send a mail but generate a report? Have a look at the documentation!
                $modifyReportServiceEvent = $this->eventDispatcher->dispatch(
                    new ModifyReportServiceEvent(['EmailReportService' => $emailReportService])
                );

                $reportServices = $modifyReportServiceEvent->getReportServices();
                foreach ($reportServices as $reportService) {
                    $reportService->setSettings([
                        'extension' => $validatorDemand->getExtension(),
                        'days' => $validatorDemand->getDays(),
                        'recipients' => $validatorDemand->getRecipients(),
                        'referencedOnly' => $validatorDemand->isReferencedOnly(),
                        'referenceRoot' => $validatorDemand->getReferenceRoot()
                    ]);
                    $reportService->setValidVideos($validVideos);
                    $reportService->setInvalidVideos($invalidVideos);
                    $reportService->makeReport();
                }

                $io->info(
                    $this->localizationUtility::translate('report.status.success', 'video_validator')
                );
            } else {
                $io->warning(
                    $this->localizationUtility::translate('report.status.noVideos', 'video_validator')
                );
            }
        } else {
            $io->warning(
                $this->localizationUtility::translate('report.status.disallowedExtension', 'video_validator')
            );
        }

        return Command::SUCCESS;
    }

    /**
     * @param ValidatorDemand $validatorDemand
     * @param int $status
     * @return array
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getVideosByStatus(ValidatorDemand $validatorDemand, int $status): array
    {
        $videos = [];
        $videoStorage = $this->fileRepository->getVideosForReport($validatorDemand, $status);
        foreach ($videoStorage as $video) {
            try {
                $file = $this->resourceFactory->getFileObject($video['uid']);
                $videos[] = $file;
            } catch (FileDoesNotExistException $e) {
            }
        }

        return $videos;
    }
}
