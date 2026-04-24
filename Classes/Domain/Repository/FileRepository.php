<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Domain\Repository;

use Ayacoo\VideoValidator\Domain\Dto\ValidatorDemand;
use Doctrine\DBAL\Exception;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Platform\PlatformInformation;
use TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileRepository
{
    private const SYS_FILE_TABLE = 'sys_file';
    private const SYS_FILE_REFERENCE_TABLE = 'sys_file_reference';
    private const PAGES_TABLE = 'pages';
    private int $maxBindParameters = 999;

    public function __construct(
        protected SiteFinder $siteFinder
    ) {
    }

    public function getVideosByExtension(ValidatorDemand $validatorDemand, int $validationDate = 0): array
    {
        $referencedOnly = $validatorDemand->isReferencedOnly();

        $queryBuilder = $this->getQueryBuilder(self::SYS_FILE_TABLE);
        $whereConstraints = $this->getDefaultWhereConstraints($queryBuilder, $validatorDemand->getExtension());
        $whereConstraints[] = $queryBuilder->expr()->lte(
            'validation_date',
            $queryBuilder->createNamedParameter($validationDate, Connection::PARAM_INT)
        );

        $pidList = [];
        $videos = [];
        if ($referencedOnly) {
            $pidList = $this->getPidList($validatorDemand->getReferenceRoot());
        }
        $pidListChunks = array_chunk($pidList, $this->maxBindParameters, true);

        if (count($pidListChunks) > 0) {
            foreach ($pidListChunks as $pidListChunk) {
                $videos = $this->getVideosByExtensionChunk(
                    $queryBuilder,
                    $validatorDemand,
                    $referencedOnly,
                    $pidListChunk,
                    $whereConstraints
                );
            }
        } else {
            $videos = $this->getVideosByExtensionChunk(
                $queryBuilder,
                $validatorDemand,
                $referencedOnly,
                [],
                $whereConstraints
            );
        }

        // Fallback: If all videos have already been checked once,
        // the videos that were last checked at least 7 days ago will be retrieved.
        if (count($videos) > 0 && $validationDate === 0) {
            $validationDate = time() - (86400 * 7);
            return $this->getVideosByExtension($validatorDemand, $validationDate);
        }

        return $videos;
    }

    public function getVideosForReport(ValidatorDemand $validatorDemand, int $validationStatus = 200): array
    {
        $queryBuilder = $this->getQueryBuilder(self::SYS_FILE_TABLE);

        $referencedOnly = $validatorDemand->isReferencedOnly();

        $whereConstraints = $this->getDefaultWhereConstraints($queryBuilder, $validatorDemand->getExtension());
        $whereConstraints[] = $queryBuilder->expr()->eq(
            'validation_status',
            $queryBuilder->createNamedParameter($validationStatus, Connection::PARAM_INT)
        );
        $whereConstraints[] = $queryBuilder->expr()->gt(
            'validation_date',
            $queryBuilder->createNamedParameter(
                time() - (86400 * $validatorDemand->getDays()),
                Connection::PARAM_INT
            )
        );

        $pidList = [];
        $videos = [];
        if ($referencedOnly) {
            $pidList = $this->getPidList($validatorDemand->getReferenceRoot());
        }
        $pidListChunks = array_chunk($pidList, $this->maxBindParameters, true);

        if (count($pidListChunks) > 0) {
            foreach ($pidListChunks as $pidListChunk) {
                $videos = array_merge(
                    $videos,
                    $this->getVideosForReportChunk($queryBuilder, $referencedOnly, $pidListChunk, $whereConstraints)
                );
            }
        } else {
            $videos = $this->getVideosForReportChunk($queryBuilder, $referencedOnly, [], $whereConstraints);
        }

        return $videos;
    }

    public function resetValidationState(string $extension): void
    {
        $queryBuilder = $this->getQueryBuilder(self::SYS_FILE_TABLE);

        $queryBuilder
            ->update(self::SYS_FILE_TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'extension',
                    $queryBuilder->createNamedParameter($extension, Connection::PARAM_STR)
                ),
            );
        $queryBuilder->set('validation_date', 0);
        $queryBuilder->set('validation_status', 0);
        $queryBuilder->executeStatement();
    }

    /**
     * Fetch videos (YouTube/Vimeo) for the backend overview module.
     * Joins sys_file_metadata so the resulting rows carry the metadata uid for edit links.
     *
     * @param string[] $extensions lowercase sys_file.extension values to include
     * @param string $search case-insensitive title substring
     * @param array<int, string[]> $storageRestrictions storageUid => mount paths; empty = no restriction (admin)
     * @return array<int, array<string, mixed>>
     * @throws Exception
     */
    public function findVideosForModule(
        array $extensions,
        string $search,
        int $statusFilter = -1,
        array $storageRestrictions = []
    ): array {
        $queryBuilder = $this->getQueryBuilder(self::SYS_FILE_TABLE);

        $statement = $queryBuilder
            ->select(
                self::SYS_FILE_TABLE . '.uid',
                self::SYS_FILE_TABLE . '.extension',
                self::SYS_FILE_TABLE . '.identifier',
                self::SYS_FILE_TABLE . '.validation_status',
                self::SYS_FILE_TABLE . '.validation_date',
                'sys_file_metadata.uid AS metadata_uid',
                'sys_file_metadata.title AS title',
            )
            ->from(self::SYS_FILE_TABLE)
            ->leftJoin(
                self::SYS_FILE_TABLE,
                'sys_file_metadata',
                'sys_file_metadata',
                $queryBuilder->expr()->eq(
                    'sys_file_metadata.file',
                    $queryBuilder->quoteIdentifier(self::SYS_FILE_TABLE . '.uid')
                )
            )
            ->where(
                ...$this->buildModuleConstraints(
                    $queryBuilder,
                    $extensions,
                    $search,
                    $statusFilter,
                    $storageRestrictions
                )
            )
            ->orderBy(self::SYS_FILE_TABLE . '.uid', 'DESC');

        return $statement->executeQuery()->fetchAllAssociative();
    }

    /**
     * @param string[] $extensions
     * @throws Exception
     */
    public function countVideosForModule(array $extensions, string $search): int
    {
        $queryBuilder = $this->getQueryBuilder(self::SYS_FILE_TABLE);

        $statement = $queryBuilder
            ->count(self::SYS_FILE_TABLE . '.uid')
            ->from(self::SYS_FILE_TABLE)
            ->leftJoin(
                self::SYS_FILE_TABLE,
                'sys_file_metadata',
                'sys_file_metadata',
                $queryBuilder->expr()->eq(
                    'sys_file_metadata.file',
                    $queryBuilder->quoteIdentifier(self::SYS_FILE_TABLE . '.uid')
                )
            )
            ->where(...$this->buildModuleConstraints($queryBuilder, $extensions, $search));

        return (int)$statement->executeQuery()->fetchOne();
    }

    /**
     * @param string[] $extensions
     * @param array<int, string[]> $storageRestrictions storageUid => mount paths; empty = no restriction
     * @return array<int, \TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression|string>
     */
    protected function buildModuleConstraints(
        QueryBuilder $queryBuilder,
        array $extensions,
        string $search,
        int $statusFilter = -1,
        array $storageRestrictions = [],
    ): array {
        $constraints = [];

        $normalizedExtensions = array_values(array_filter(array_map('strtolower', $extensions)));
        if ($normalizedExtensions !== []) {
            $constraints[] = $queryBuilder->expr()->in(
                self::SYS_FILE_TABLE . '.extension',
                $queryBuilder->createNamedParameter($normalizedExtensions, Connection::PARAM_STR_ARRAY)
            );
        }

        $constraints[] = $queryBuilder->expr()->eq(
            self::SYS_FILE_TABLE . '.missing',
            $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
        );

        if ($statusFilter >= 0) {
            $constraints[] = $queryBuilder->expr()->eq(
                self::SYS_FILE_TABLE . '.validation_status',
                $queryBuilder->createNamedParameter($statusFilter, Connection::PARAM_INT)
            );
        }

        $search = trim($search);
        if ($search !== '') {
            $constraints[] = $queryBuilder->expr()->like(
                'sys_file_metadata.title',
                $queryBuilder->createNamedParameter('%' . $queryBuilder->escapeLikeWildcards($search) . '%')
            );
        }

        if ($storageRestrictions !== []) {
            $constraints[] = $this->buildStorageConstraint($queryBuilder, $storageRestrictions);
        }

        return $constraints;
    }

    /**
     * Builds an OR constraint covering all accessible storages and their file mount paths.
     * A path of "/" means the user has access to the entire storage (no path filter needed).
     *
     * @param array<int, string[]> $storageRestrictions storageUid => mount paths
     */
    protected function buildStorageConstraint(
        QueryBuilder $queryBuilder,
        array $storageRestrictions
    ): CompositeExpression {
        $storageConstraints = [];

        foreach ($storageRestrictions as $storageUid => $paths) {
            $storageEq = $queryBuilder->expr()->eq(
                self::SYS_FILE_TABLE . '.storage',
                $queryBuilder->createNamedParameter($storageUid, Connection::PARAM_INT)
            );

            $hasRootMount = in_array('/', $paths, true);
            if ($hasRootMount || $paths === []) {
                // Full storage access — no path restriction needed
                $storageConstraints[] = $storageEq;
                continue;
            }

            $pathConstraints = [];
            foreach ($paths as $path) {
                $pathConstraints[] = $queryBuilder->expr()->like(
                    self::SYS_FILE_TABLE . '.identifier',
                    $queryBuilder->createNamedParameter($queryBuilder->escapeLikeWildcards($path) . '%')
                );
            }

            $storageConstraints[] = $queryBuilder->expr()->and(
                $storageEq,
                $queryBuilder->expr()->or(...$pathConstraints)
            );
        }

        return $queryBuilder->expr()->or(...$storageConstraints);
    }

    public function updatePropertiesByFile(int $fileUid, array $properties = []): void
    {
        $queryBuilder = $this->getQueryBuilder(self::SYS_FILE_TABLE);

        $queryBuilder
            ->update(self::SYS_FILE_TABLE)
            ->where(
                $queryBuilder->expr()->eq(
                    'uid',
                    $queryBuilder->createNamedParameter($fileUid, Connection::PARAM_INT)
                ),
            );
        foreach ($properties as $key => $value) {
            $queryBuilder->set($key, $value);
        }
        $queryBuilder->executeStatement();
    }

    protected function getQueryBuilder(string $tableName = ''): QueryBuilder
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable($tableName);

        $this->maxBindParameters = PlatformInformation::getMaxBindParameters($connection->getDatabasePlatform());

        return $connection->createQueryBuilder();
    }

    protected function getDefaultWhereConstraints(QueryBuilder $queryBuilder, string $extension): array
    {
        $whereConstraints = [];
        $whereConstraints[] = $queryBuilder->expr()->eq(
            'extension',
            $queryBuilder->createNamedParameter(strtolower($extension), Connection::PARAM_STR)
        );
        $whereConstraints[] = $queryBuilder->expr()->eq(
            'missing',
            $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
        );
        return $whereConstraints;
    }

    protected function getStatementForRepository(
        QueryBuilder &$queryBuilder,
        int $limit,
        bool $referencedOnly,
        array $pidList
    ): QueryBuilder {
        if ($referencedOnly) {
            // Narrator: Sadly SQL Joins cannot be performed on the sys_file_reference.tablenames column
            // as a dynamic join. Thus later on, we need to iterate the resultset and perform a distinct query on the
            // referenced tablename to check whether the target record is active.
            $statement = $queryBuilder
                ->select(self::SYS_FILE_TABLE . '.*')
                ->from(self::SYS_FILE_TABLE)
                ->join(
                    self::SYS_FILE_TABLE,
                    self::SYS_FILE_REFERENCE_TABLE,
                    'sr',
                    '(sr.uid_local = ' . self::SYS_FILE_TABLE . '.uid AND sr.table_local = "' .
                    self::SYS_FILE_TABLE . '" AND sr.deleted=0 AND sr.hidden=0)'
                )
                ->join(
                    'sr',
                    self::PAGES_TABLE,
                    'p',
                    '(p.uid = sr.pid AND p.deleted=0 AND p.hidden=0 AND p.uid IN (' .
                    implode(',', $pidList) . '))'
                )
                ->groupBy(self::SYS_FILE_TABLE . '.uid');
        } else {
            $statement = $queryBuilder
                ->select(self::SYS_FILE_TABLE . '.*')
                ->from(self::SYS_FILE_TABLE);
        }

        if ($limit > 0) {
            $statement->setMaxResults($limit);
        }

        return $statement;
    }

    protected function parseVideosForReferences(array &$videos, array $pidList): void
    {
        // See above. We need to iterate each referenced file and check the referenced content-element to check
        // its active state.
        // First query: Resolve all table names to fetch. Note that we can get multiple results for one sys_file record,
        // i.e. a video could be referenced in 5 different content elements.
        // The video shall only be checked if at least one content element is enabled.
        foreach ($videos as $videoId => $video) {
            $subQueryBuilder = $this->getQueryBuilder(self::SYS_FILE_REFERENCE_TABLE);

            $subConstraints = [];
            $subConstraints[] = $subQueryBuilder->expr()->eq(
                'uid_local',
                $subQueryBuilder->createNamedParameter($video['uid'], Connection::PARAM_INT)
            );
            $subConstraints[] = $subQueryBuilder->expr()->in(
                'pid',
                $pidList
            );

            $subStatement = $subQueryBuilder
                ->select('*')
                ->from(self::SYS_FILE_REFERENCE_TABLE)
                ->where(...$subConstraints);
            $contentElements = $subStatement->executeQuery()->fetchAllAssociative() ?? [];

            $hasAnyValidReference = false;
            foreach ($contentElements as $contentElement) {
                $ceQueryBuilder = $this->getQueryBuilder($contentElement['tablenames']);

                $ceConstraints = [];
                $ceConstraints[] = $ceQueryBuilder->expr()->eq(
                    'uid',
                    $ceQueryBuilder->createNamedParameter($contentElement['uid_foreign'], Connection::PARAM_INT)
                );
                $ceConstraints[] = $ceQueryBuilder->expr()->in(
                    'pid',
                    $pidList
                );

                $ceStatement = $ceQueryBuilder
                    ->select('*')
                    ->from($contentElement['tablenames'])
                    ->where(...$ceConstraints);

                $contentElementReferences = $ceStatement->executeQuery()->fetchAllAssociative() ?? [];
                if (count($contentElementReferences) > 0) {
                    $hasAnyValidReference = true;
                    // On first hit of a video reference we don't need to check any others.
                    break;
                }
            }

            // This video has not been referenced in any active content element. It will not be checked.
            $videos[$videoId]['_hasAnyValidReference'] = $hasAnyValidReference;
        }
    }

    protected function getPidList(int $root = 0): array
    {
        $rootPages = [];
        if ($root === 0) {
            $sites = $this->siteFinder->getAllSites() ?? [];
            foreach ($sites as $site) {
                $rootPages[] = $site->getRootPageId();
            }
        } else {
            $rootPages[] = $root;
        }

        $pidListArray = [];
        foreach ($rootPages as $rootPage) {
            $pidListArray = array_merge($pidListArray, $this->getPageTreeIds($rootPage, 99, 0));
        }

        return $pidListArray;
    }

    /**
     * Copy from AdministrationRepository with default restrictions
     *
     * @param int $id Start page id
     * @param int $depth Depth to traverse down the page tree.
     * @param int $begin Determines at which level in the tree to start collecting uid's.
     *                   Zero means 'start right away', 1 = 'next level and out'
     * @return array Returns the list of pages
     *
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getPageTreeIds(int $id, int $depth, int $begin): array
    {
        if ($id <= 0 || $depth <= 0) {
            return [];
        }
        $queryBuilder = $this->getQueryBuilder('pages');
        $result = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq(
                    'pid',
                    $queryBuilder->createNamedParameter($id, Connection::PARAM_INT)
                )
            )
            ->executeQuery();
        $rows = $result->fetchAllAssociative();

        $pageIds = [];
        foreach ($rows as $row) {
            if ($begin <= 0) {
                $pageIds[] = (int)$row['uid'];
            }
            if ($depth > 1) {
                $pageIds = array_merge(
                    $pageIds,
                    $this->getPageTreeIds((int)$row['uid'], $depth - 1, $begin - 1)
                );
            }
        }
        return $pageIds;
    }

    protected function getVideosForReportChunk(
        QueryBuilder $queryBuilder,
        bool $referencedOnly,
        array $pidListChunk,
        array $whereConstraints
    ): array {
        $statement = $this->getStatementForRepository($queryBuilder, 0, $referencedOnly, $pidListChunk);
        if (count($whereConstraints) > 0) {
            $statement->where(
                ...$whereConstraints
            );
        }
        $videos = $statement->executeQuery()->fetchAllAssociative();
        if ($referencedOnly && count($videos) > 0) {
            $this->parseVideosForReferences($videos, $pidListChunk);
        }

        return $videos;
    }

    protected function getVideosByExtensionChunk(
        QueryBuilder $queryBuilder,
        ValidatorDemand $validatorDemand,
        bool $referencedOnly,
        array $pidListChunk,
        array $whereConstraints
    ): array {
        $statement = $this->getStatementForRepository(
            $queryBuilder,
            $validatorDemand->getLimit(),
            $referencedOnly,
            $pidListChunk
        );
        if (count($whereConstraints) > 0) {
            $statement->where(
                ...$whereConstraints
            );
        }
        $videos = $statement->executeQuery()->fetchAllAssociative();
        if ($referencedOnly && count($videos) > 0) {
            $this->parseVideosForReferences($videos, $pidListChunk);
        }

        return $videos;
    }
}
