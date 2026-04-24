<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Service;

use Ayacoo\VideoValidator\Domain\Dto\ValidatorDemand;
use Ayacoo\VideoValidator\Domain\Repository\FileRepository;
use Ayacoo\VideoValidator\Event\ModifyValidatorEvent;
use Ayacoo\VideoValidator\Event\ModifyVideoValidateEvent;
use Ayacoo\VideoValidator\Service\Validator\AbstractVideoValidatorInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

class VideoService
{
    final public const STATUS_SUCCESS = 200;

    final public const STATUS_SKIP = 410;

    final public const STATUS_ERROR = 404;

    public function __construct(
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FileRepository $fileRepository,
        private readonly ResourceFactory $resourceFactory,
        private readonly LocalizationUtility $localizationUtility,
        #[AutowireIterator('video_validator.validator', indexAttribute: 'extension')]
        private readonly iterable $validator,
        private ?SymfonyStyle $io = null,
    ) {
    }

    public function getIo(): ?SymfonyStyle
    {
        return $this->io;
    }

    public function setIo(?SymfonyStyle $io): void
    {
        $this->io = $io;
    }

    /**
     * Returns whether a validator is registered for the given file extension.
     * Takes ModifyValidatorEvent into account, so extensions that plug in
     * validators dynamically are also detected.
     */
    public function hasValidator(string $extension): bool
    {
        $demand = new ValidatorDemand();
        $demand->setExtension($extension);
        return $this->getValidator($demand) !== null;
    }

    /**
     * Validates a single file by its uid and persists the result.
     * Used by the backend "Refresh status" action. Reuses the existing validator lookup,
     * file repository, and ModifyVideoValidateEvent dispatch — no CLI I/O.
     *
     * @param int $fileUid
     * @return int One of STATUS_SUCCESS, STATUS_ERROR
     * @throws FileDoesNotExistException if the file does not exist
     */
    public function validateFile(int $fileUid): int
    {
        $file = $this->resourceFactory->getFileObject($fileUid);

        $demand = new ValidatorDemand();
        $demand->setExtension(strtolower($file->getExtension()));
        $validator = $this->getValidator($demand);
        if ($validator === null) {
            throw new \RuntimeException(
                sprintf('No validator registered for extension "%s"', $file->getExtension())
            );
        }

        $mediaId = $validator->getOnlineMediaId($file) ?? '';
        if ($mediaId === '' || !$validator->isVideoOnline($mediaId)) {
            $status = self::STATUS_ERROR;
        } else {
            $status = self::STATUS_SUCCESS;
        }

        $properties = [
            'validation_status' => $status,
            'validation_date' => time(),
        ];
        $this->fileRepository->updatePropertiesByFile($fileUid, $properties);
        $this->eventDispatcher->dispatch(new ModifyVideoValidateEvent($file, $properties));

        return $status;
    }

    public function validate(ValidatorDemand $validatorDemand): void
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
                        'videoService.noVideoValidation',
                        'video_validator'
                    ),
                    $validatorDemand->getExtension()
                )
            );
        } elseif ($validator === null) {
            $this->io->error(
                sprintf(
                    $this->localizationUtility::translate(
                        'videoService.noValidatorFound',
                        'video_validator'
                    ),
                    $validatorDemand->getExtension()
                )
            );
        } else {
            $this->io->progressStart($numberOfVideos);
            foreach ($videos as $video) {
                $properties = [];
                $this->io->newLine(2);

                $file = $this->resourceFactory->getFileObject($video['uid']);
                $mediaId = $validator->getOnlineMediaId($file) ?? '';

                $title = $file->getProperty('title') ?? '';
                $message = $validatorDemand->getExtension() . ' Video ' . $title;
                if ($mediaId === '') {
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
                    $this->io->error(
                        $message . $this->localizationUtility::translate(
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
                        ['URL: ' . $validator->buildUrl($mediaId)],
                    ]
                );

                $this->fileRepository->updatePropertiesByFile($video['uid'], $properties);

                // You want a special action after validation? Have a look at the documentation!
                $event = new ModifyVideoValidateEvent($file, $properties);
                $this->eventDispatcher->dispatch($event);

                $this->io->progressAdvance(1);
            }
            $this->io->progressFinish();
        }
    }

    /**
     * There is direct support for the core media extensions YouTube and Vimeo.
     * Other media extensions can be overwritten via event. More about this in the README.md
     *
     * @param ValidatorDemand $validatorDemand
     * @return AbstractVideoValidatorInterface|null
     */
    protected function getValidator(ValidatorDemand $validatorDemand): ?AbstractVideoValidatorInterface
    {
        $extension = strtolower($validatorDemand->getExtension());
        $validator = null;
        foreach ($this->validator as $validatorKey => $currentValidator) {
            if ($validatorKey === $extension) {
                $validator = $currentValidator;
                break;
            }
        }

        $modifyValidatorEvent = $this->eventDispatcher->dispatch(
            new ModifyValidatorEvent($validator, $extension)
        );
        return $modifyValidatorEvent->getValidator();
    }
}
