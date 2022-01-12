<?php

declare(strict_types=1);

namespace Ayacoo\VideoValidator\Domain\Repository;

use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class FileRepository
{
    private const SYS_FILE_TABLE = 'sys_file';
    private const SYS_FILE_REFERENCE_TABLE = 'sys_file_reference';
    private const PAGES_TABLE = 'pages';

    /**
     * @param string $extension
     * @param int $validationDate
     * @param int $limit
     * @param bool $referencedOnly
     * @return array
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function getVideosByExtension(string $extension, int $validationDate = 0, int $limit = 10, bool $referencedOnly = false): array
    {
        $queryBuilder = $this->getQueryBuilder(self::SYS_FILE_TABLE);

        $whereConstraints = [];
        $whereConstraints[] = $queryBuilder->expr()->eq(
            'extension',
            $queryBuilder->createNamedParameter(strtolower($extension), Connection::PARAM_STR)
        );
        $whereConstraints[] = $queryBuilder->expr()->eq(
            'missing',
            $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
        );
        $whereConstraints[] = $queryBuilder->expr()->lte(
            'validation_date',
            $queryBuilder->createNamedParameter($validationDate, Connection::PARAM_INT)
        );


        if ($referencedOnly) {
            // TODO: Add \TYPO3\CMS\Core\Database::getTreeList($id, $depth) for a page uid listing here
            $pidList = '1,33';

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
                    '(sr.uid_local = ' . self::SYS_FILE_TABLE . '.uid AND sr.table_local = "sys_file" AND sr.deleted=0 AND sr.hidden=0)'
                )
                ->join(
                    'sr',
                    self::PAGES_TABLE,
                    'p',
                    '(p.uid = sr.pid AND p.deleted=0 AND p.hidden=0 AND p.uid IN (' . $pidList . '))'
                )
                ->groupBy(self::SYS_FILE_TABLE . '.uid')
                ->setMaxResults($limit);
        } else {
            $statement = $queryBuilder
                ->select(self::SYS_FILE_TABLE . '.*')
                ->from(self::SYS_FILE_TABLE)
                ->setMaxResults($limit);
        }

        if (!empty($whereConstraints)) {
            $statement->where(
                ...$whereConstraints
            );
        }
        $videos = $statement->execute()->fetchAllAssociative() ?? [];

        if ($referencedOnly && count($videos) > 0) {
            // See above. We need to iterate each referenced file and check the referenced content-element to check its active state.
            // First query: Resolve all table names to fetch. Note that we can get multiple results for one sys_file record, i.e.
            //  a video could be referenced in 5 different content elements. The video shall only be checked if at least one
            //  content element is enabled.

            foreach($videos AS $videoId => $video) {
                echo "Got a video #" . $videoId . " UID #" . $video['uid'] . ".\n";

                $subQueryBuilder = $this->getQueryBuilder(self::SYS_FILE_REFERENCE_TABLE);

                $subConstraints = [];
                $subConstraints[] = $subQueryBuilder->expr()->eq(
                    'uid_local',
                    $subQueryBuilder->createNamedParameter($video['uid'], \PDO::PARAM_INT)
                );
                $subConstraints[] = $subQueryBuilder->expr()->eq(
                    'deleted',
                    $subQueryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
                );
                $subConstraints[] = $subQueryBuilder->expr()->eq(
                    'hidden',
                    $subQueryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
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
                echo "Checking " . count($contentElements) . " Content Elements\n";
                foreach($contentElements AS $contentElement) {
                    $ceQueryBuilder = $this->getQueryBuilder($contentElement['tablenames']);

                    $ceConstraints = [];
                    $ceConstraints[] = $ceQueryBuilder->expr()->eq(
                        'uid',
                        $ceQueryBuilder->createNamedParameter($contentElement['uid'], \PDO::PARAM_INT)
                    );
                    $ceConstraints[] = $ceQueryBuilder->expr()->eq(
                        'deleted',
                        $ceQueryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
                    );
                    $ceConstraints[] = $ceQueryBuilder->expr()->eq(
                        'hidden',
                        $ceQueryBuilder->createNamedParameter(0, \PDO::PARAM_INT)
                    );
                    $subConstraints[] = $ceQueryBuilder->expr()->in(
                        'pid',
                        $pidList
                    );

                    echo "Fetching content elements from " . $contentElement['tablenames'] . " ...\n";
                    $ceStatement = $ceQueryBuilder
                        ->select('*')
                        ->from($contentElement['tablenames'])
                        ->where(...$ceConstraints);

                    $contentElementReferences = $ceStatement->execute()->fetchAllAssociative() ?? [];
                    if (count($contentElementReferences) > 0) {
                        echo "Got " . count($contentElementReferences) . " active elements.\n";
                        $hasAnyValidReference = true;
                        // On first hit of a video reference we don't need to check any others.
                        break;
                    } else {
                        echo "Did not get any active elements from here.";
                    }
                }

                if (!$hasAnyValidReference) {
                    // This video has not been referenced in any active content element. It will not be checked.
                    echo "Removing video #$videoId - has no valid references.\n";
                    unset($videos[$videoId]);
                }
            }
        }

        print_r($videos);
        die('done');

        // Fallback: If all videos have already been checked once,
        // the videos that were last checked at least 7 days ago will be retrieved.
        if (empty($videos) && $validationDate === 0) {
            $validationDate = time() - (86400 * 7);
            return $this->getVideosByExtension($extension, $validationDate, $limit, $referencedOnly);
        }

        return $videos;
    }

    /**
     * @param string $extension
     * @param int $days
     * @param int $validationStatus
     * @param bool $referencedOnly
     * @return array
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function getVideosForReport(string $extension, int $days = 7, int $validationStatus = 200, bool $referencedOnly = false): array
    {
        $queryBuilder = $this->getQueryBuilder(self::SYS_FILE_TABLE);

        $whereConstraints = [];
        $whereConstraints[] = $queryBuilder->expr()->eq(
            'extension',
            $queryBuilder->createNamedParameter(strtolower($extension), Connection::PARAM_STR)
        );
        $whereConstraints[] = $queryBuilder->expr()->eq(
            'missing',
            $queryBuilder->createNamedParameter(0, Connection::PARAM_INT)
        );
        $whereConstraints[] = $queryBuilder->expr()->eq(
            'validation_status',
            $queryBuilder->createNamedParameter($validationStatus, Connection::PARAM_INT)
        );
        $whereConstraints[] = $queryBuilder->expr()->gt(
            'validation_date',
            $queryBuilder->createNamedParameter(time() - (86400 * $days), Connection::PARAM_INT)
        );

        $statement = $queryBuilder
            ->select('*')
            ->from(self::SYS_FILE_TABLE);

        if (!empty($whereConstraints)) {
            $statement->where(
                ...$whereConstraints
            );
        }

        if ($referencedOnly) {
            // TODO; refactor Code above
        }

        return $statement->execute()->fetchAllAssociative() ?? [];
    }

    /**
     * @param int $fileUid
     * @param array $properties
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
     */
    protected function getQueryBuilder(string $tableName = ''): QueryBuilder
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($tableName)
            ->createQueryBuilder();
    }

    /**
     * @param string $extension
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
}
