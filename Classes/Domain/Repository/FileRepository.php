<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Domain\Repository;

use Ayacoo\VideoValidator\Domain\Dto\ValidatorDemand;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Platform\PlatformInformation;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileRepository
{
    private const SYS_FILE_TABLE = 'sys_file';
    private const SYS_FILE_REFERENCE_TABLE = 'sys_file_reference';
    private const PAGES_TABLE = 'pages';
    private ?SiteFinder $siteFinder;
    private int $maxBindParameters = 999;

    /**
     * @param SiteFinder|null $siteFinder
     */
    public function __construct(SiteFinder $siteFinder = null)
    {
        $this->siteFinder = $siteFinder;
    }

    /**
     * @param ValidatorDemand $validatorDemand
     * @param int $validationDate
     * @return array
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
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
                $videos = $this->getVideosByExtensionChunk($queryBuilder, $validatorDemand, $referencedOnly, $pidListChunk, $whereConstraints);
            }
        } else {
            $videos = $this->getVideosByExtensionChunk($queryBuilder, $validatorDemand, $referencedOnly, [], $whereConstraints);
        }

        // Fallback: If all videos have already been checked once,
        // the videos that were last checked at least 7 days ago will be retrieved.
        if (empty($videos) && $validationDate === 0) {
            $validationDate = time() - (86400 * 7);
            return $this->getVideosByExtension($validatorDemand, $validationDate);
        }

        return $videos;
    }

    /**
     * @param ValidatorDemand $validatorDemand
     * @param int $validationStatus
     * @return array
     * @throws \Doctrine\DBAL\Driver\Exception
     * @throws \Doctrine\DBAL\Exception
     */
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
                $videos = array_merge($videos, $this->getVideosForReportChunk($queryBuilder, $referencedOnly, $pidListChunk, $whereConstraints));
            }
        } else {
            $videos = $this->getVideosForReportChunk($queryBuilder, $referencedOnly, [], $whereConstraints);
        }

        return $videos;
    }

    /**
     * @param string $extension
     * @throws \Doctrine\DBAL\Exception
     */
    public function resetValidationState(string $extension)
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
        $queryBuilder->execute();
    }

    /**
     * @param int $fileUid
     * @param array $properties
     * @throws \Doctrine\DBAL\Exception
     */
    public function updatePropertiesByFile(int $fileUid, array $properties = [])
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
        $queryBuilder->execute();
    }

    /**
     * @param string $tableName
     * @return QueryBuilder
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getQueryBuilder(string $tableName = ''): QueryBuilder
    {
        $connectionPool = GeneralUtility::makeInstance(ConnectionPool::class);
        $connection = $connectionPool->getConnectionForTable($tableName);

        $this->maxBindParameters = PlatformInformation::getMaxBindParameters($connection->getDatabasePlatform());

        return $connection->createQueryBuilder();
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param string $extension
     * @return array
     */
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

    /**
     * @param QueryBuilder $queryBuilder
     * @param int $limit
     * @param bool $referencedOnly
     * @param array $pidList
     * @return QueryBuilder
     */
    protected function getStatementForRepository(
        QueryBuilder &$queryBuilder,
        int          $limit,
        bool         $referencedOnly,
        array        $pidList
    ): QueryBuilder
    {
        if ($referencedOnly) {
            // Narrator: Sadly SQL Joins cannot be performed on the sys_file_reference.tablenames column as a dynamic join.
            // Thus later on, we need to iterate the resultset and perform a distinct query on the referenced tablename to check whether
            // the target record is active.
            $statement = $queryBuilder
                ->select(self::SYS_FILE_TABLE . '.*')
                ->from(self::SYS_FILE_TABLE)
                ->join(
                    self::SYS_FILE_TABLE,
                    self::SYS_FILE_REFERENCE_TABLE,
                    'sr',
                    '(sr.uid_local = ' . self::SYS_FILE_TABLE . '.uid AND sr.table_local = "' . self::SYS_FILE_TABLE . '" AND sr.deleted=0 AND sr.hidden=0)'
                )
                ->join(
                    'sr',
                    self::PAGES_TABLE,
                    'p',
                    '(p.uid = sr.pid AND p.deleted=0 AND p.hidden=0 AND p.uid IN (' . implode(',', $pidList) . '))'
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

    /**
     * @param array $videos
     * @param array $pidList
     * @return void
     * @throws \Doctrine\DBAL\Exception
     */
    protected function parseVideosForReferences(array &$videos, array $pidList)
    {
        // See above. We need to iterate each referenced file and check the referenced content-element to check its active state.
        // First query: Resolve all table names to fetch. Note that we can get multiple results for one sys_file record, i.e.
        //  a video could be referenced in 5 different content elements. The video shall only be checked if at least one
        //  content element is enabled.
        foreach ($videos as $videoId => $video) {
            $subQueryBuilder = $this->getQueryBuilder(self::SYS_FILE_REFERENCE_TABLE);

            $subConstraints = [];
            $subConstraints[] = $subQueryBuilder->expr()->eq(
                'uid_local',
                $subQueryBuilder->createNamedParameter($video['uid'], \PDO::PARAM_INT)
            );
            $subConstraints[] = $subQueryBuilder->expr()->in(
                'pid',
                $pidList
            );

            $subStatement = $subQueryBuilder
                ->select('*')
                ->from(self::SYS_FILE_REFERENCE_TABLE)
                ->where(...$subConstraints);
            $contentElements = $subStatement->execute()->fetchAllAssociative() ?? [];

            $hasAnyValidReference = false;
            foreach ($contentElements as $contentElement) {
                $ceQueryBuilder = $this->getQueryBuilder($contentElement['tablenames']);

                $ceConstraints = [];
                $ceConstraints[] = $ceQueryBuilder->expr()->eq(
                    'uid',
                    $ceQueryBuilder->createNamedParameter($contentElement['uid_foreign'], \PDO::PARAM_INT)
                );
                $ceConstraints[] = $ceQueryBuilder->expr()->in(
                    'pid',
                    $pidList
                );

                $ceStatement = $ceQueryBuilder
                    ->select('*')
                    ->from($contentElement['tablenames'])
                    ->where(...$ceConstraints);

                $contentElementReferences = $ceStatement->execute()->fetchAllAssociative() ?? [];
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

    /**
     * @param int $root
     * @return array
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getPidList(int $root = 0): array
    {
        $roots = [];
        if ($root === 0) {
            $sites = $this->siteFinder->getAllSites() ?? [];
            foreach ($sites as $site) {
                $roots[] = $site->getRootPageId();
            }
        } else {
            $roots[] = $root;
        }

        $pidListArray = [];
        foreach ($roots as $root) {
            $pidListArray = array_merge($pidListArray, $this->getPageTreeIds($root, 99, 0));
        }

        return $pidListArray;
    }

    /**
     * Copy from AdministrationRepository with default restrictions
     *
     * @param int $id Start page id
     * @param int $depth Depth to traverse down the page tree.
     * @param int $begin Determines at which level in the tree to start collecting uid's. Zero means 'start right away', 1 = 'next level and out'
     * @return array Returns the list of pages
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getPageTreeIds(int $id, int $depth, int $begin): array
    {
        if (!$id || $depth <= 0) {
            return [];
        }
        $queryBuilder = $this->getQueryBuilder('pages');
        $result = $queryBuilder
            ->select('uid')
            ->from('pages')
            ->where(
                $queryBuilder->expr()->eq('pid', $queryBuilder->createNamedParameter($id, \PDO::PARAM_INT))
            )
            ->execute();

        $pageIds = [];
        while ($row = $result->fetchAssociative()) {
            if ($begin <= 0) {
                $pageIds[] = (int)$row['uid'];
            }
            if ($depth > 1) {
                $pageIds = array_merge($pageIds, $this->getPageTreeIds((int)$row['uid'], $depth - 1, $begin - 1));
            }
        }
        return $pageIds;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param bool $referencedOnly
     * @param array $pidListChunk
     * @param array $whereConstraints
     * @return array
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getVideosForReportChunk(
        QueryBuilder $queryBuilder,
        bool         $referencedOnly,
        array        $pidListChunk,
        array        $whereConstraints): array
    {
        $statement = $this->getStatementForRepository($queryBuilder, 0, $referencedOnly, $pidListChunk);
        if (!empty($whereConstraints)) {
            $statement->where(
                ...$whereConstraints
            );
        }
        $videos = $statement->execute()->fetchAllAssociative();
        if ($referencedOnly && count($videos) > 0) {
            $this->parseVideosForReferences($videos, $pidListChunk);
        }

        return $videos;
    }

    /**
     * @param QueryBuilder $queryBuilder
     * @param ValidatorDemand $validatorDemand
     * @param bool $referencedOnly
     * @param array $pidListChunk
     * @param array $whereConstraints
     * @return array
     * @throws \Doctrine\DBAL\Exception
     */
    protected function getVideosByExtensionChunk(
        QueryBuilder    $queryBuilder,
        ValidatorDemand $validatorDemand,
        bool            $referencedOnly,
        array           $pidListChunk,
        array           $whereConstraints): array
    {
        $statement = $this->getStatementForRepository(
            $queryBuilder,
            $validatorDemand->getLimit(),
            $referencedOnly,
            $pidListChunk
        );
        if (!empty($whereConstraints)) {
            $statement->where(
                ...$whereConstraints
            );
        }
        $videos = $statement->execute()->fetchAllAssociative();
        if ($referencedOnly && count($videos) > 0) {
            $this->parseVideosForReferences($videos, $pidListChunk);
        }

        return $videos;
    }
}
