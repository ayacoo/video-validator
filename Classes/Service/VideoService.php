<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Service;

use Ayacoo\VideoValidator\Domain\Repository\FileRepository;
use Ayacoo\VideoValidator\Event\ModifyValidatorEvent;
use Ayacoo\VideoValidator\Service\Validator\AbstractVideoValidatorInterface;
use Ayacoo\VideoValidator\Service\Validator\VimeoValidator;
use Ayacoo\VideoValidator\Service\Validator\YoutubeValidator;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class VideoService
{
    public const STATUS_SUCCESS = 200;

    public const STATUS_ERROR = 404;

    private ?SymfonyStyle $io;

    private ?EventDispatcherInterface $eventDispatcher;

    private ?FileRepository $fileRepository;

    private ?ResourceFactory $resourceFactory;

    private ?LocalizationUtility $localizationUtility;

    private string $extension = '';

    private int $limit = 10;

    private bool $referencedOnly = false;

    /**
     * @param SymfonyStyle|null $symfonyStyle
     * @param EventDispatcherInterface|null $eventDispatcher
     * @param FileRepository|null $fileRepository
     * @param ResourceFactory|null $resourceFactory
     * @param LocalizationUtility|null $localizationUtility
     */
    public function __construct(
        SymfonyStyle             $symfonyStyle = null,
        EventDispatcherInterface $eventDispatcher = null,
        FileRepository           $fileRepository = null,
        ResourceFactory          $resourceFactory = null,
        LocalizationUtility      $localizationUtility = null
    )
    {
        $this->io = $symfonyStyle;
        $this->eventDispatcher = $eventDispatcher;
        $this->fileRepository = $fileRepository;
        $this->resourceFactory = $resourceFactory;
        $this->localizationUtility = $localizationUtility;
    }

    /**
     * @return string
     */
    public function getExtension(): string
    {
        return $this->extension;
    }

    /**
     * @param string $extension
     */
    public function setExtension(string $extension): void
    {
        $this->extension = $extension;
    }

    /**
     * @return int
     */
    public function getLimit(): int
    {
        return $this->limit;
    }

    /**
     * @param int $limit
     */
    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    /**
     * @return bool
     */
    public function getReferencedOnly(): bool
    {
        return $this->referencedOnly;
    }

    /**
     * @param bool $referencedOnly
     */
    public function setReferencedOnly(bool $referencedOnly): void
    {
        $this->referencedOnly = $referencedOnly;
    }

    /**
     * @return SymfonyStyle|null
     */
    public function getIo(): ?SymfonyStyle
    {
        return $this->io;
    }

    /**
     * @param SymfonyStyle|null $io
     */
    public function setIo(?SymfonyStyle $io): void
    {
        $this->io = $io;
    }

    /**
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException
     */
    public function validate()
    {
        $validator = $this->getValidator();

        $videos = $this->fileRepository->getVideosByExtension($this->getExtension(), 0, $this->getLimit(), $this->getReferencedOnly());
        $numberOfVideos = count($videos);

        if ($numberOfVideos < 1) {
            $this->io->warning(
                sprintf(
                    $this->localizationUtility::translate('videoService.noVideoValidation', 'video_validator'),
                    $this->getExtension()
                )
            );
        } elseif ($validator === null) {
            $this->io->error(
                sprintf(
                    $this->localizationUtility::translate('videoService.noValidatorFound', 'video_validator'),
                    $this->getExtension()
                )
            );
        } else {
            $this->io->progressStart($numberOfVideos);
            foreach ($videos as $video) {
                $this->io->newLine(2);

                $file = $this->resourceFactory->getFileObject($video['uid']);
                $mediaId = $validator->getOnlineMediaId($file);

                $title = $file->getProperty('title') ?? '';
                $message = $this->getExtension() . ' Video ' . $title ;
                if (empty($mediaId)) {
                    $this->io->warning(
                        $message . $this->localizationUtility::translate(
                            'videoService.status.noMediaId',
                            'video_validator'
                        )
                    );
                    $properties['validation_status'] = self::STATUS_ERROR;
                } elseif ($validator->isVideoOnline($mediaId)) {
                    $this->io->success(
                        $message . $this->localizationUtility::translate(
                            'videoService.status.success',
                            'video_validator'
                        )
                    );
                    $properties['validation_status'] = self::STATUS_SUCCESS;
                } else {
                    $this->io->error($message . $this->localizationUtility::translate(
                            'videoService.status.error',
                            'video_validator'
                        )
                    );
                    $properties['validation_status'] = self::STATUS_ERROR;
                }
                $properties['validation_date'] = time();

                $this->io->table(
                    [],
                    [
                        ['File UID: ' . $video['uid']],
                        ['Title: ' .  $file->getProperty('title') ?? 'No title'],
                        ['Identifier: ' . $file->getIdentifier()],
                        ['URL: ' . $validator->buildUrl($mediaId)]
                    ]
                );

                $this->fileRepository->updatePropertiesByFile($video['uid'], $properties);
                $this->io->progressAdvance(1);
            }
            $this->io->progressFinish();
        }
    }

    /**
     * There is direct support for the core media extensions Youtube and Vimeo. Other media extensions can be overwritten
     * via event. More about this in the README.md
     *
     * @return AbstractVideoValidatorInterface|null
     */
    protected function getValidator(): ?AbstractVideoValidatorInterface
    {
        $validator = null;
        switch ($this->getExtension()) {
            case 'Youtube':
                $validator = GeneralUtility::makeInstance(YoutubeValidator::class, $this->getExtension());
                break;
            case 'Vimeo':
                $validator = GeneralUtility::makeInstance(VimeoValidator::class, $this->getExtension());
                break;
        }
        $modifyValidatorEvent = $this->eventDispatcher->dispatch(
            new ModifyValidatorEvent($validator, $this->getExtension())
        );
        return $modifyValidatorEvent->getValidator();
    }
}
