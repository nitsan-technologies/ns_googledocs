<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Tests\Functional\Updates;

use NITSAN\NsGoogledocs\Updates\PrefixedBeUsersColumnsUpgradeWizard;
use PHPUnit\Framework\Attributes\Test;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

final class PrefixedBeUsersColumnsUpgradeWizardTest extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'install',
    ];

    protected array $testExtensionsToLoad = [
        'ns_license',
        'ns_googledocs',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpBackendUser(1);
        $this->ensureLegacyColumns();
    }

    #[Test]
    public function executeUpdateCopiesLegacyValuesWhenPrefixedColumnsAreEmpty(): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('be_users');
        $connection->update(
            'be_users',
            [
                'client_id' => 'legacy-client',
                'client_secret' => 'legacy-secret',
                'refresh_token' => 'legacy-refresh',
                'import_type' => '1',
                'tx_nsgoogledocs_client_id' => '',
                'tx_nsgoogledocs_client_secret' => '',
                'tx_nsgoogledocs_refresh_token' => '',
                'tx_nsgoogledocs_import_type' => '',
            ],
            ['uid' => 1]
        );

        $wizard = new PrefixedBeUsersColumnsUpgradeWizard();
        self::assertTrue($wizard->updateNecessary());
        self::assertTrue($wizard->executeUpdate());

        $row = $connection->select(
            [
                'tx_nsgoogledocs_client_id',
                'tx_nsgoogledocs_client_secret',
                'tx_nsgoogledocs_refresh_token',
                'tx_nsgoogledocs_import_type',
            ],
            'be_users',
            ['uid' => 1]
        )->fetchAssociative();

        self::assertIsArray($row);
        self::assertSame('legacy-client', $row['tx_nsgoogledocs_client_id']);
        self::assertSame('legacy-secret', $row['tx_nsgoogledocs_client_secret']);
        self::assertSame('legacy-refresh', $row['tx_nsgoogledocs_refresh_token']);
        self::assertSame('1', $row['tx_nsgoogledocs_import_type']);
    }

    #[Test]
    public function executeUpdateDoesNotOverwriteExistingPrefixedValues(): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('be_users');
        $connection->update(
            'be_users',
            [
                'client_id' => 'legacy-client',
                'tx_nsgoogledocs_client_id' => 'keep-me',
            ],
            ['uid' => 1]
        );

        $wizard = new PrefixedBeUsersColumnsUpgradeWizard();
        self::assertTrue($wizard->executeUpdate());

        $row = $connection->select(['tx_nsgoogledocs_client_id'], 'be_users', ['uid' => 1])->fetchAssociative();
        self::assertIsArray($row);
        self::assertSame('keep-me', $row['tx_nsgoogledocs_client_id']);
    }

    private function ensureLegacyColumns(): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)->getConnectionForTable('be_users');
        $columns = $connection->createSchemaManager()->listTableColumns('be_users');
        foreach (['client_id', 'client_secret', 'refresh_token', 'import_type'] as $column) {
            if (isset($columns[$column])) {
                continue;
            }
            $quoted = $connection->quoteIdentifier($column);
            $connection->executeStatement(
                'ALTER TABLE ' . $connection->quoteIdentifier('be_users')
                . ' ADD ' . $quoted . " VARCHAR(255) DEFAULT '' NOT NULL"
            );
        }
    }
}
