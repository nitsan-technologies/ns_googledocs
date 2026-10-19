<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Updates;

use Doctrine\DBAL\Exception as DbalException;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
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
                $connection->executeStatement(
                    sprintf(
                        'UPDATE be_users SET %s = %s WHERE (%s = \'\' OR %s IS NULL) AND %s <> \'\' AND %s IS NOT NULL',
                        $prefixed,
                        $legacy,
                        $prefixed,
                        $prefixed,
                        $legacy,
                        $legacy
                    )
                );
            } catch (DbalException) {
                return false;
            }
        }

        return true;
    }

    public function getPrerequisites(): array
    {
        // Prefer the real EXT:install class on v12/v13: the v14 name is only an alias there
        // and would bypass DI, breaking the prerequisite constructor.
        if (class_exists(\TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite::class, false)
            || !class_exists(DatabaseUpdatedPrerequisite::class)
        ) {
            if (class_exists(\TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite::class)) {
                return [\TYPO3\CMS\Install\Updates\DatabaseUpdatedPrerequisite::class];
            }
            return [];
        }
        return [DatabaseUpdatedPrerequisite::class];
        return [];
    }

    private function hasRowsNeedingMigration(Connection $connection, string $legacy, string $prefixed): bool
    {
        try {
            $count = (int)$connection->executeQuery(
                sprintf(
                    'SELECT COUNT(*) FROM be_users WHERE (%s = \'\' OR %s IS NULL) AND %s <> \'\' AND %s IS NOT NULL',
                    $prefixed,
                    $prefixed,
                    $legacy,
                    $legacy
                )
            )->fetchOne();
            return $count > 0;
        } catch (DbalException) {
            return false;
        }
    }

    private function getConnection(): Connection
    {
        return GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('be_users');
    }
}
