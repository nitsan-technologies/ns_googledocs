<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * Guards Deprecation #108345: composer.json must declare version + providesPackages
 * so Core skips ext_emconf.php on TYPO3 v14.2+ without changing runtime autoload.
 */
final class ComposerJsonManifestTest extends TestCase
{
    private const EXTENSION_ROOT = __DIR__ . '/../..';

    /**
     * @return array<string, mixed>
     */
    private function composerManifest(): array
    {
        $path = self::EXTENSION_ROOT . '/composer.json';
        self::assertFileExists($path);
        $decoded = json_decode((string)file_get_contents($path), true);
        self::assertIsArray($decoded);
        self::assertSame(JSON_ERROR_NONE, json_last_error());
        return $decoded;
    }

    #[Test]
    public function composerJsonIsComposerOnlyCapableForTypo3V14(): void
    {
        $manifest = json_decode((string)file_get_contents(self::EXTENSION_ROOT . '/composer.json'));
        self::assertInstanceOf(\stdClass::class, $manifest);

        $hasProvidesPackages = isset($manifest->extra->{'typo3/cms'}->Package->providesPackages);
        $hasVersion = ($manifest->version ?? null) !== null
            || isset($manifest->extra->{'typo3/cms'}->version);

        self::assertTrue($hasProvidesPackages);
        self::assertTrue($hasVersion);
        self::assertSame('14.0.0', $manifest->extra->{'typo3/cms'}->version);
        self::assertSame('ns_googledocs', $manifest->extra->{'typo3/cms'}->{'extension-key'});
    }

    #[Test]
    public function providesPackagesListsOnlyBundledGoogleApiClient(): void
    {
        $provides = $this->composerManifest()['extra']['typo3/cms']['Package']['providesPackages'] ?? null;
        self::assertIsArray($provides);
        self::assertArrayHasKey('google/apiclient', $provides);
        self::assertSame('', $provides['google/apiclient']);
        self::assertArrayNotHasKey('nitsan/ns-license', $provides);
        self::assertArrayNotHasKey('php', $provides);
        self::assertArrayNotHasKey('typo3/cms-core', $provides);
        self::assertCount(1, $provides);
    }

    #[Test]
    public function emptyProvidesPathDoesNotRegisterAClassLoadingInclude(): void
    {
        $provides = $this->composerManifest()['extra']['typo3/cms']['Package']['providesPackages'];
        foreach ($provides as $relativePath) {
            self::assertSame('', $relativePath);
        }
    }

    #[Test]
    public function classicVendorAutoloadAndEmconfRemainInPlace(): void
    {
        self::assertFileExists(self::EXTENSION_ROOT . '/ext_emconf.php');
        self::assertFileExists(self::EXTENSION_ROOT . '/vendor/autoload.php');
        $autoload = (string)file_get_contents(self::EXTENSION_ROOT . '/vendor/autoload.php');
        self::assertStringContainsString('return ComposerAutoloaderInit', $autoload);
    }

    #[Test]
    public function googleApiclientServicesExtraIsUnchanged(): void
    {
        $extra = $this->composerManifest()['extra'];
        self::assertSame(['Drive'], $extra['google/apiclient-services']);
    }
}
