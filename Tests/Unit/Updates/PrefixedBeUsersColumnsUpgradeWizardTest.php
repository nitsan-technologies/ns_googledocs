<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Tests\Unit\Updates;

use NITSAN\NsGoogledocs\Updates\PrefixedBeUsersColumnsUpgradeWizard;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class PrefixedBeUsersColumnsUpgradeWizardTest extends TestCase
{
    #[Test]
    public function prerequisitesAreASingleExistingClass(): void
    {
        $wizard = new PrefixedBeUsersColumnsUpgradeWizard();
        $prerequisites = $wizard->getPrerequisites();
        self::assertCount(1, $prerequisites);
        self::assertTrue(class_exists($prerequisites[0]));
    }

    #[Test]
    public function sourceContainsNoSprintfSqlAndNoUnreachableReturn(): void
    {
        $source = (string)file_get_contents(
            dirname(__DIR__, 3) . '/Classes/Updates/PrefixedBeUsersColumnsUpgradeWizard.php'
        );
        self::assertStringNotContainsString('sprintf(', $source);
        self::assertStringNotContainsString('UPDATE be_users SET', $source);
        self::assertDoesNotMatchRegularExpression('/return \\[DatabaseUpdatedPrerequisite::class];\\s+return \\[\\];/', $source);
    }
}
