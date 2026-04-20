<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Controller\Backend;

use Ayacoo\VideoValidator\Domain\Repository\FileRepository;
use Ayacoo\VideoValidator\Event\ModifyVideoOverviewExtensionsEvent;
use Ayacoo\VideoValidator\Service\VideoService;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Routing\Exception\RouteNotFoundException;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Core\Pagination\ArrayPaginator;
use TYPO3\CMS\Core\Pagination\SimplePagination;
use TYPO3\CMS\Core\Resource\Exception\FileDoesNotExistException;
use TYPO3\CMS\Core\Resource\ResourceFactory;

class VideoOverviewController
{
    private const ITEMS_PER_PAGE = 25;

    /** @var string[] Built-in extensions; others can be added via ModifyVideoOverviewExtensionsEvent */
    private const DEFAULT_EXTENSIONS = ['youtube', 'vimeo'];

    /** @var array<string, string> Known icon identifiers per media extension */
    private const EXTENSION_ICONS = [
        'youtube' => 'mimetypes-media-video-youtube',
        'vimeo' => 'mimetypes-media-video-vimeo',
    ];

    /**
     * @param ModuleTemplateFactory $moduleTemplateFactory
     * @param UriBuilder $uriBuilder
     * @param EventDispatcherInterface $eventDispatcher
     * @param FileRepository $fileRepository
     * @param ResourceFactory $resourceFactory
     */
    public function __construct(
        private readonly ModuleTemplateFactory $moduleTemplateFactory,
        private readonly UriBuilder $uriBuilder,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly FileRepository $fileRepository,
        private readonly ResourceFactory $resourceFactory,
        private readonly VideoService $videoService,
    ) {
    }

    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $queryParams = $request->getQueryParams();
        $extensionFilter = strtolower((string)($queryParams['extension'] ?? ''));
        $search = trim((string)($queryParams['search'] ?? ''));
        $currentPage = max(1, (int)($queryParams['page'] ?? 1));
        $rawStatus = (int)($queryParams['status'] ?? -1);
        $statusFilter = in_array(
            $rawStatus,
            [
                -1,
                VideoService::STATUS_SUCCESS,
                VideoService::STATUS_ERROR,
                VideoService::STATUS_SKIP
            ],
            true
        ) ? $rawStatus : -1;

        ['extensions' => $supportedExtensions, 'iconMap' => $iconMap] = $this->resolveSupportedExtensions();

        if (!in_array($extensionFilter, $supportedExtensions, true)) {
            $extensionFilter = '';
        }

        $extensions = $extensionFilter !== '' ? [$extensionFilter] : $supportedExtensions;

        $storageRestrictions = $this->resolveStorageRestrictions();
        $allRawVideos = $this->fileRepository->findVideosForModule(
            $extensions,
            $search,
            $statusFilter,
            $storageRestrictions
        );

        $paginator = new ArrayPaginator($allRawVideos, $currentPage, self::ITEMS_PER_PAGE);
        $pagination = new SimplePagination($paginator);

        $videos = $this->enrichVideos((array)$paginator->getPaginatedItems(), $iconMap);

        $moduleBaseUri = (string)$this->uriBuilder->buildUriFromRoute('file_videovalidator');
        $paginationUris = $this->buildPaginationUris(
            $extensionFilter,
            $search,
            $statusFilter,
            (int)$paginator->getCurrentPageNumber(),
            (int)$pagination->getPreviousPageNumber(),
            (int)$pagination->getNextPageNumber(),
            (int)$pagination->getLastPageNumber(),
        );

        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $moduleTemplate->assignMultiple([
            'videos' => $videos,
            'paginator' => $paginator,
            'pagination' => $pagination,
            'totalCount' => count($allRawVideos),
            'itemsPerPage' => self::ITEMS_PER_PAGE,
            'extensionFilter' => $extensionFilter,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'availableExtensions' => $supportedExtensions,
            'statusError' => VideoService::STATUS_ERROR,
            'moduleBaseUri' => $moduleBaseUri,
            'paginationUris' => $paginationUris,
        ]);

        return $moduleTemplate->renderResponse('Overview');
    }

    /**
     * Returns per-storage path restrictions for the current BE user.
     * Admins get an empty array (= no restriction).
     * Regular users get a map of storageUid => mount paths; path "/" means full storage access.
     *
     * @return array<int, string[]>
     */
    private function resolveStorageRestrictions(): array
    {
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if ($backendUser === null || $backendUser->isAdmin()) {
            return [];
        }

        $restrictions = [];
        foreach ($backendUser->getFileStorages() as $storage) {
            $paths = [];
            foreach ($storage->getFileMounts() as $mount) {
                $paths[] = (string)($mount['path'] ?? '/');
            }
            $restrictions[$storage->getUid()] = $paths ?: ['/'];
        }

        return $restrictions;
    }

    /**
     * @return array{extensions: string[], iconMap: array<string, string>}
     */
    private function resolveSupportedExtensions(): array
    {
        $allowedExtensions = array_keys(
            $GLOBALS['TYPO3_CONF_VARS']['SYS']['fal']['onlineMediaHelpers'] ?? []
        );

        $event = new ModifyVideoOverviewExtensionsEvent(
            self::DEFAULT_EXTENSIONS,
            $allowedExtensions,
            self::EXTENSION_ICONS,
        );

        /** @var ModifyVideoOverviewExtensionsEvent $event */
        $event = $this->eventDispatcher->dispatch($event);

        return [
            'extensions' => $event->getExtensions(),
            'iconMap' => $event->getIconMap(),
        ];
    }

    /**
     * @return array<string, string|bool>
     * @throws RouteNotFoundException
     */
    private function buildPaginationUris(
        string $extensionFilter,
        string $search,
        int $statusFilter,
        int $currentPage,
        int $previousPage,
        int $nextPage,
        int $lastPage,
    ): array {
        $build = function (int $page) use ($extensionFilter, $search, $statusFilter): string {
            return (string)$this->uriBuilder->buildUriFromRoute('file_videovalidator', [
                'extension' => $extensionFilter,
                'search' => $search,
                'status' => $statusFilter,
                'page' => $page,
            ]);
        };

        return [
            'first' => $build(1),
            'previous' => $previousPage > 0 ? $build($previousPage) : '',
            'next' => $nextPage > 0 ? $build($nextPage) : '',
            'last' => $build(max(1, $lastPage)),
            'hasPrevious' => $currentPage > 1,
            'hasNext' => $currentPage < max(1, $lastPage),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rawVideos
     * @param array<string, string>            $iconMap   extension => TYPO3 icon identifier
     * @return array<int, array<string, mixed>>
     */
    private function enrichVideos(array $rawVideos, array $iconMap = []): array
    {
        $hasValidatorCache = [];
        foreach ($rawVideos as $key => $row) {
            $extension = strtolower((string)($row['extension'] ?? ''));
            try {
                $file = $this->resourceFactory->getFileObject((int)$row['uid']);
                $publicUrl = $file->getPublicUrl();
            } catch (FileDoesNotExistException) {
                // ignore — file may have been removed meanwhile
                $publicUrl = '';
            }
            if (!array_key_exists($extension, $hasValidatorCache)) {
                $hasValidatorCache[$extension] = $extension !== ''
                    && $this->videoService->hasValidator($extension);
            }
            $rawVideos[$key]['public_url'] = $publicUrl;
            $rawVideos[$key]['is_invalid'] = (int)($row['validation_status'] ?? 0) === VideoService::STATUS_ERROR;
            $rawVideos[$key]['icon_identifier'] = $iconMap[$extension] ?? 'mimetypes-media-video';
            $rawVideos[$key]['has_validator'] = $hasValidatorCache[$extension];
        }

        return $rawVideos;
    }
}
