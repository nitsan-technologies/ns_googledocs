<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Tests\Unit\Service;

use NITSAN\NsGoogledocs\Service\RemoteSnippetValidator;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class RemoteSnippetValidatorTest extends TestCase
{
    #[Test]
    public function rejectsRedirectPage(): void
    {
        $html = '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN"><html><head><title>301 Moved Permanently</title>'
            . '</head><body><h1>Moved Permanently</h1></body></html>';
        self::assertSame('', RemoteSnippetValidator::filter($html));
        self::assertSame('', RemoteSnippetValidator::filter('<h1>404 Not Found</h1>'));
        self::assertSame('', RemoteSnippetValidator::filter('  '));
    }

    #[Test]
    public function acceptsSnippet(): void
    {
        $html = '<div class="ns-googledocs-support-block"><p>Need help with Google Docs import?</p></div>';
        self::assertSame($html, RemoteSnippetValidator::filter($html));
    }
}
