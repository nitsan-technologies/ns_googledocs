<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Updates;

use Doctrine\DBAL\Exception as DbalException;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Upgrades\DatabaseUpdatedPrerequisite;
use TYPO3\CMS\Core\Upgrades\UpgradeWizardInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;

// TYPO3 v12/v13 compatibility: these classes live in EXT:install until v14.
if (!interface_exists(UpgradeWizardInterface::class)
    && interface_exists(\TYPO3\CMS\Install\Updates\UpgradeWizardInterface::class)
) {
    class_alias(\TYPO3\CMS\Install\Updates\UpgradeWizardInterface::class, UpgradeWizardInterface::class);
}
if (!class_exists(DatabaseUpdatedPrerequisite::class)
    && class_exists(\TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite::class)
) {
    class_alias(\TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite::class, DatabaseUpdatedPrerequisite::class);
}

/**
 * Copies legacy unprefixed be_users credential columns into tx_nsgoogledocs_* fields.
 */
final class PrefixedBeUsersColumnsUpgradeWizard implements UpgradeWizardInterface
{
    private const FIELD_MAP = [
        'client_id' => 'tx_nsgoogledocs_client_id',
        'client_secret' => 'tx_nsgoogledocs_client_secret',
        'refresh_token' => 'tx_nsgoogledocs_refresh_token',
        'import_type' => 'tx_nsgoogledocs_import_type',
    ];

    public function getTitle(): string
    {
        return 'Migrate ns_googledocs be_users columns to prefixed names';
    }

    public function getDescription(): string
    {
        return 'Copies client_id, client_secret, refresh_token and import_type into'
            . ' tx_nsgoogledocs_* columns on be_users when the legacy columns still hold data.';
    }

    public function updateNecessary(): bool
    {
        $connection = $this->getConnection();
        $schemaManager = $connection->createSchemaManager();
        if (!$schemaManager->tablesExist(['be_users'])) {
            return false;
        }
        $columns = $schemaManager->listTableColumns('be_users');
        foreach (self::FIELD_MAP as $legacy => $prefixed) {
            if (!isset($columns[$legacy], $columns[$prefixed])) {
                continue;
            }
            if ($this->hasRowsNeedingMigration($connection, $legacy, $prefixed)) {
                return true;
            }
        }
        return false;
    }

    public function executeUpdate(): bool
    {
        $connection = $this->getConnection();
        $schemaManager = $connection->createSchemaManager();
        $columns = $schemaManager->listTableColumns('be_users');

        foreach (self::FIELD_MAP as $legacy => $prefixed) {
            if (!isset($columns[$legacy], $columns[$prefixed])) {
                continue;
            }
            try {
                $this->copyLegacyColumn($connection, $legacy, $prefixed);
            } catch (DbalException $exception) {
                GeneralUtility::makeInstance(LogManager::class)
                    ->getLogger(self::class)
                    ->warning('ns_googledocs upgrade wizard failed for {legacy}: {message}', [
                        'legacy' => $legacy,
                        'message' => $exception->getMessage(),
                    ]);
                return false;
            }
        }

        return true;
    }

    public function getPrerequisites(): array
    {
        // On v12/v13 the Core class name is only an alias; returning it skips DI.
        if (class_exists(\TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite::class, false)) {
            return [\TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite::class];
        }
        if (class_exists(DatabaseUpdatedPrerequisite::class)) {
            return [DatabaseUpdatedPrerequisite::class];
        }
        return [];
    }

    private function hasRowsNeedingMigration(Connection $connection, string $legacy, string $prefixed): bool
    {
        try {
            $queryBuilder = $this->createUnrestrictedQueryBuilder($connection);
            $count = (int)$queryBuilder
                ->count('*')
                ->from('be_users')
                ->where(...$this->needsMigrationPredicates($queryBuilder, $legacy, $prefixed))
                ->executeQuery()
                ->fetchOne();
            return $count > 0;
        } catch (DbalException) {
            return false;
        }
    }

    private function copyLegacyColumn(Connection $connection, string $legacy, string $prefixed): void
    {
        $queryBuilder = $this->createUnrestrictedQueryBuilder($connection);
        $queryBuilder
            ->update('be_users')
            ->set($prefixed, $queryBuilder->quoteIdentifier($legacy), false)
            ->where(...$this->needsMigrationPredicates($queryBuilder, $legacy, $prefixed))
            ->executeStatement();
    }

    /**
     * @return list<string|\TYPO3\CMS\Core\Database\Query\Expression\CompositeExpression>
     */
    private function needsMigrationPredicates(QueryBuilder $queryBuilder, string $legacy, string $prefixed): array
    {
        $expr = $queryBuilder->expr();
        $prefixedEmpty = $expr->eq($prefixed, $queryBuilder->createNamedParameter(''));
        $prefixedNull = $expr->isNull($prefixed);
        $emptyPrefixed = method_exists($expr, 'or')
            ? $expr->or($prefixedEmpty, $prefixedNull)
            : $expr->orX($prefixedEmpty, $prefixedNull);

        return [
            $emptyPrefixed,
            $expr->neq($legacy, $queryBuilder->createNamedParameter('')),
            $expr->isNotNull($legacy),
        ];
    }

    private function createUnrestrictedQueryBuilder(Connection $connection): QueryBuilder
    {
        $queryBuilder = $connection->createQueryBuilder();
        $queryBuilder->getRestrictions()->removeAll();
        return $queryBuilder;
    }

    private function getConnection(): Connection
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('be_users');
    }
}
