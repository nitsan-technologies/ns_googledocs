<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Tests\Functional;

use TYPO3\CMS\Core\Localization\LanguageServiceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\TestingFramework\Core\Functional\FunctionalTestCase;

abstract class AbstractFunctionalTestCase extends FunctionalTestCase
{
    protected array $coreExtensionsToLoad = [
        'frontend',
        'fluid_styled_content',
        'extensionmanager',
        'install',
    ];

    protected array $testExtensionsToLoad = [
        'nitsan/ns-license',
        'ns_googledocs',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->importCSVDataSet(__DIR__ . '/Domain/Repository/Fixtures/pages.csv');
        $this->setUpBackendUser(1);
        $GLOBALS['LANG'] = GeneralUtility::makeInstance(LanguageServiceFactory::class)
            ->createFromUserPreferences($GLOBALS['BE_USER']);
    }
}
