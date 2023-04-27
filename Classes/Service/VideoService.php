<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Service;

use Ayacoo\VideoValidator\Domain\Dto\ValidatorDemand;
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
    final public const STATUS_SUCCESS = 200;

    final public const STATUS_SKIP = 410;

    final public const STATUS_ERROR = 404;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FileRepository           $fileRepository,
        private readonly ResourceFactory          $resourceFactory,
        private readonly LocalizationUtility      $localizationUtility,
        private ?SymfonyStyle                     $io = null,
    )
    {
    }

    public function getIo(): ?SymfonyStyle
    {
        return $this->io;
    }

    public function setIo(?SymfonyStyle $io): void
    {
        $this->io = $io;
    }

    public function validate(ValidatorDemand $validatorDemand)
    {
        $validator = $this->getValidator($validatorDemand);

        $videos = $this->fileRepository->getVideosByExtension(
            $validatorDemand,
            0,
        );
        $numberOfVideos = count($videos);

        if ($numberOfVideos < 1) {
            $this->io->warning(
                sprintf(
                    $this->localizationUtility::translate(
                        'videoService.noVideoValidation', 'video_validator'
                    ),
                    $validatorDemand->getExtension()
                )
            );
        } elseif ($validator === null) {
            $this->io->error(
                sprintf(
                    $this->localizationUtility::translate(
                        'videoService.noValidatorFound', 'video_validator'
                    ),
                    $validatorDemand->getExtension()
                )
            );
        } else {
            $this->io->progressStart($numberOfVideos);
            foreach ($videos as $video) {
                $this->io->newLine(2);

                $file = $this->resourceFactory->getFileObject($video['uid']);
                $mediaId = $validator->getOnlineMediaId($file);

                $title = $file->getProperty('title') ?? '';
                $message = $validatorDemand->getExtension() . ' Video ' . $title;
                if (empty($mediaId)) {
                    $this->io->warning(
                        $message . $this->localizationUtility::translate(
                            'videoService.status.noMediaId',
                            'video_validator'
                        )
                    );
                    $properties['validation_status'] = self::STATUS_ERROR;
                } elseif (isset($video['_hasAnyValidReference']) && $video['_hasAnyValidReference'] === false) {
                    $this->io->warning(
                        $message . $this->localizationUtility::translate(
                            'videoService.status.skip',
                            'video_validator'
                        )
                    );
                    $properties['validation_status'] = self::STATUS_SKIP;
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
                        ['Title: ' . $file->getProperty('title') ?? 'No title'],
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
     * There is direct support for the core media extensions YouTube and Vimeo. Other media extensions can be overwritten
     * via event. More about this in the README.md
     *
     * @param ValidatorDemand $validatorDemand
     * @return AbstractVideoValidatorInterface|null
     */
    protected function getValidator(ValidatorDemand $validatorDemand): ?AbstractVideoValidatorInterface
    {
        $extension = strtolower($validatorDemand->getExtension());
        $validator = match ($extension) {
            'youtube' => GeneralUtility::makeInstance(YoutubeValidator::class, $extension),
            'vimeo' => GeneralUtility::makeInstance(VimeoValidator::class, $extension)
        };

        $modifyValidatorEvent = $this->eventDispatcher->dispatch(
            new ModifyValidatorEvent($validator, $extension)
        );
        return $modifyValidatorEvent->getValidator();
    }
}
