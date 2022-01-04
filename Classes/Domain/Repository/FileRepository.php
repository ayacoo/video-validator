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

    /**
     * @param string $extension
     * @param int $validationDate
     * @param int $limit
     * @return array
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function getVideosByExtension(string $extension, int $validationDate = 0, int $limit = 10): array
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

        $statement = $queryBuilder
            ->select('*')
            ->from(self::SYS_FILE_TABLE)
            ->setMaxResults($limit);

        if (!empty($whereConstraints)) {
            $statement->where(
                ...$whereConstraints
            );
        }
        $videos = $statement->execute()->fetchAllAssociative() ?? [];

        // Fallback: If all videos have already been checked once,
        // the videos that were last checked at least 7 days ago will be retrieved.
        if (empty($videos) && $validationDate === 0) {
            $validationDate = time() - (86400 * 7);
            return $this->getVideosByExtension($extension, $validationDate, $limit);
        }

        return $videos;
    }

    /**
     * @param string $extension
     * @param int $days
     * @param int $validationStatus
     * @return array
     * @throws \Doctrine\DBAL\Driver\Exception
     */
    public function getVideosForReport(string $extension, int $days = 7, int $validationStatus = 200): array
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
