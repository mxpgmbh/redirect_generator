<?php
declare(strict_types=1);

namespace GeorgRinger\RedirectGenerator\Repository;

use GeorgRinger\RedirectGenerator\Domain\Model\Dto\Configuration;
use GeorgRinger\RedirectGenerator\Domain\Model\Dto\UrlInfo;
use GeorgRinger\RedirectGenerator\Exception\ConflictingDuplicateException;
use GeorgRinger\RedirectGenerator\Exception\NonConflictingDuplicateException;
use GeorgRinger\RedirectGenerator\Exception\OverwrittenDuplicateException;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;

class RedirectRepository
{
    public const CUSTOM_CREATION_TYPE = 6332;

    private const TABLE = 'sys_redirect';

    public function getRedirect(string $url): ?array
    {
        $urlInfo = GeneralUtility::makeInstance(UrlInfo::class, $url);

        $queryBuilder = $this->getConnection()->createQueryBuilder();

        $row = $queryBuilder->select('*')
            ->from(self::TABLE)
            ->where(
                $queryBuilder->expr()->orX(
                    $queryBuilder->expr()->eq('source_host', $queryBuilder->createNamedParameter('*', \PDO::PARAM_STR)),
                    $queryBuilder->expr()->eq('source_host', $queryBuilder->createNamedParameter($urlInfo->getHost(), \PDO::PARAM_STR))
                ),
                $queryBuilder->expr()->eq('source_path', $queryBuilder->createNamedParameter($urlInfo->getPathWithQuery(), \PDO::PARAM_STR))
            )
            ->executeQuery()
            ->fetchAssociative();

        if ($row === false) {
            return null;
        }

        return $row;
    }

    /**
     * @param string $url
     * @param string $target
     * @param Configuration $configuration
     * @param bool $dryRun
     * @throws ConflictingDuplicateException,NonConflictingDuplicateException,OverwrittenDuplicateException
     */
    public function addRedirect(string $url, string $target, Configuration $configuration, bool $dryRun = false): void
    {
        $existingRow = $this->getRedirect($url);
        if (is_array($existingRow)) {
            if ($configuration->getOverwriteExisting()) {
                $this->updateExistingRow($existingRow, $target, $configuration, $dryRun);

                // This should be a return value, as this is normal control flow
                // and nothing exceptional.
                throw new OverwrittenDuplicateException(
                    \sprintf(
                        'Redirect for "%s" overwrites ID %s! Existing'
                            . ' target was "%s", new target is now "%s".'
                            ,
                        $url,
                        $existingRow['uid'],
                        $existingRow['target'],
                        $target
                    ),
                    1695904053
                );
            }

            if ($target !== $existingRow['target']) {
                throw new ConflictingDuplicateException(
                    \sprintf(
                        'Redirect for "%s" exists already with ID %s! Existing'
                            . ' target is "%s", new target would be "%s".'
                            ,
                        $url,
                        $existingRow['uid'],
                        $existingRow['target'],
                        $target
                    ),
                    1568487151
                );
            }

            throw new NonConflictingDuplicateException(
                \sprintf(
                    'Redirect for "%s" exists already with ID %s, but has the same target as the new redirect.',
                    $url,
                    $existingRow['uid'],
                ),
                1568487151
            );

        }

        if ($dryRun) {
            return;
        }

        $urlInfo = GeneralUtility::makeInstance(UrlInfo::class, $url);
        $connection = $this->getConnection();

        $data = [
            'creation_type' => self::CUSTOM_CREATION_TYPE,
            'createdon' => $GLOBALS['EXEC_TIME'],
            'updatedon' => $GLOBALS['EXEC_TIME'],
            'keep_query_parameters' => $configuration->getKeepQueryParameters() ? 1 : 0,
            'is_regexp' => $configuration->getRegexp() ? 1 : 0,
            'force_https' => $configuration->getForceHttps() ? 1 : 0,
            'target_statuscode' => $configuration->getTargetStatusCode(),
            'disable_hitcount' => $configuration->getDisableHitCount() ? 1 : 0,
            'respect_query_parameters' => $configuration->getRespectQueryParameters() ? 1 : 0,
            'source_host' => $urlInfo->getHost() ?: '*',
            'source_path' => $urlInfo->getPathWithQuery(),
            'target' => $target
        ];
        $connection->insert(self::TABLE, $data);
    }

    public function getAllRedirects(): array
    {
        $queryBuilder = $this->getConnection()->createQueryBuilder();

        return $queryBuilder
            ->select('*')
            ->from(self::TABLE)
            ->executeQuery()
            ->fetchAllAssociative();
    }

    private function getConnection(): Connection
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable(self::TABLE);
    }

    protected function updateExistingRow(
        array $existingRow,
        string $target,
        Configuration $configuration,
        bool $dryRun
    ): void {
        if ($dryRun) {
            return;
        }

        $connection = $this->getConnection();

        $data = [
            'updatedon' => $this->getExecTime(),
            'keep_query_parameters' => $configuration->getKeepQueryParameters() ? 1 : 0,
            'is_regexp' => $configuration->getRegexp() ? 1 : 0,
            'force_https' => $configuration->getForceHttps() ? 1 : 0,
            'target_statuscode' => $configuration->getTargetStatusCode(),
            'disable_hitcount' => $configuration->getDisableHitCount() ? 1 : 0,
            'respect_query_parameters' => $configuration->getRespectQueryParameters() ? 1 : 0,
            'target' => $target,
        ];

        $connection->update(self::TABLE, $data, ['uid' => $existingRow['uid']]);
    }

    protected function getExecTime(): int
    {
        return $GLOBALS['EXEC_TIME'];
    }
}
