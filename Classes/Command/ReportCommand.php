<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Command;

use Ayacoo\VideoValidator\Domain\Repository\FileRepository;
use Ayacoo\VideoValidator\Service\VideoService;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Mime\Address;
use TYPO3\CMS\Core\Mail\FluidEmail;
use TYPO3\CMS\Core\Mail\Mailer;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class ReportCommand extends Command
{
    private ?ResourceFactory $resourceFactory;

    private ?FileRepository $fileRepository;

    private ?LocalizationUtility $localizationUtility;

    protected function configure(): void
    {
        $this->setDescription('Send video report');
        $this->addArgument(
            'days',
            InputArgument::REQUIRED,
            'Number of days',
        );
        $this->addArgument(
            'recipients',
            InputArgument::REQUIRED,
            'Comma separated list of email recipients',
        );
        $this->addArgument(
            'extension',
            InputArgument::REQUIRED,
            'Name of the video extension',
        );
        $this->addArgument(
            'referencedOnly',
            InputArgument::OPTIONAL,
            'Whether to only fetch records that are referenced on visible pages and content elements (true/false)',
        );
    }

    /**
     * @param ResourceFactory|null $resourceFactory
     * @param FileRepository|null $fileRepository
     * @param LocalizationUtility|null $localizationUtility
     */
    public function __construct(
        ResourceFactory     $resourceFactory = null,
        FileRepository      $fileRepository = null,
        LocalizationUtility $localizationUtility = null
    )
    {
        $this->resourceFactory = $resourceFactory;
        $this->fileRepository = $fileRepository;
        $this->localizationUtility = $localizationUtility;
        parent::__construct();
    }

    /**
     * Generates video report
     *
     * @param InputInterface $input
     * @param OutputInterface $output
     *
     * @return int
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $days = (int)trim($input->getArgument('days')) ?? 7;
        $recipients = GeneralUtility::trimExplode(',', trim($input->getArgument('recipients')));
        $extension = trim($input->getArgument('extension'));
        $referencedOnly = (bool)$input->getArgument('referencedOnly');

        $sender = $GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress'] ?? '';
        if (empty($sender)) {
            $io->warning(
                $this->localizationUtility::translate('report.validMailAddress', 'video_validator')
            );
            return 0;
        }

        $allowedExtensions = array_keys($GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['onlineMediaHelpers'] ?? []);
        if (in_array(strtolower($extension), $allowedExtensions, true)) {
            $this->sendMail($extension, $days, $recipients, $referencedOnly);
            $io->info(
                $this->localizationUtility::translate('report.status.success', 'video_validator')
            );
        } else {
            $io->warning(
                $this->localizationUtility::translate('report.status.disallowedExtension', 'video_validator')
            );
        }

        return 0;
    }

    /**
     * @param string $extension
     * @param int $days
     * @param bool $referencedOnly
     *
     * @param array $recipients
     */
    protected function sendMail(string $extension, int $days, array $recipients, bool $referencedOnly = false): void
    {
        $invalidVideos = $this->getVideosByStatus($extension, $days, VideoService::STATUS_ERROR, $referencedOnly);
        $validVideos = $this->getVideosByStatus($extension, $days, VideoService::STATUS_SUCCESS, $referencedOnly);

        $subject = 'TYPO3 ' . $extension . ' validation report';
        $email = GeneralUtility::makeInstance(FluidEmail::class);
        $email
            ->from(new Address($GLOBALS['TYPO3_CONF_VARS']['MAIL']['defaultMailFromAddress']))
            ->format('html')
            ->setTemplate('VideoReport')
            ->subject($subject)
            ->assign('headline', $subject)
            ->assign('days', $days)
            ->assign('referencedOnly', $referencedOnly)
            ->assign('numberOfVideos', count($invalidVideos) + count($validVideos))
            ->assign('invalidVideos', $invalidVideos)
            ->assign('validVideos', $validVideos);
        // Fix for scheduler
        if ($GLOBALS['TYPO3_REQUEST'] ?? '' instanceof ServerRequestInterface) {
            $email->setRequest($GLOBALS['TYPO3_REQUEST']);
        }
        foreach ($recipients as $recipient) {
            $email->to($recipient);
            GeneralUtility::makeInstance(Mailer::class)->send($email);
        }
    }

    /**
     * @param string $extension
     * @param int $days
     * @param int $status
     * @param bool $referencedOnly
     *
     * @return array
     */
    protected function getVideosByStatus(string $extension, int $days, int $status, bool $referencedOnly): array
    {
        $videos = [];
        $videoStorage = $this->fileRepository->getVideosForReport($extension, $days, $status, $referencedOnly);
        foreach ($videoStorage as $video) {
            $file = $this->resourceFactory->getFileObject($video['uid']);
            if ($file) {
                $videos[] = $file;
            }
        }

        return $videos;
    }
}
