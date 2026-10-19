<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Tests\Unit\Service;

use NITSAN\NsGoogledocs\Service\GoogleDocsSectionParser;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class GoogleDocsSectionParserTest extends TestCase
{
    private GoogleDocsSectionParser $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new GoogleDocsSectionParser();
    }

    #[Test]
    public function splitsByHeadingOneAndTwo(): void
    {
        $html = '<h1>Intro</h1><p>First</p><h2>Details</h2><p>Second</p><img src="https://example.com/a.png" />';
        $sections = $this->subject->parse($html);

        self::assertCount(2, $sections);
        self::assertSame('Intro', $sections[0]['title']);
        self::assertSame(1, $sections[0]['headingLevel']);
        self::assertStringContainsString('First', $sections[0]['html']);
        self::assertFalse($sections[0]['hasImages']);

        self::assertSame('Details', $sections[1]['title']);
        self::assertSame(2, $sections[1]['headingLevel']);
        self::assertTrue($sections[1]['hasImages']);
    }

    #[Test]
    public function contentBeforeFirstHeadingBecomesUntitledSection(): void
    {
        $html = '<p>Lead-in</p><h1>Main</h1><p>Body</p>';
        $sections = $this->subject->parse($html);

        self::assertCount(2, $sections);
        self::assertSame('', $sections[0]['title']);
        self::assertStringContainsString('Lead-in', $sections[0]['html']);
        self::assertSame('Main', $sections[1]['title']);
    }

    #[Test]
    public function ignoresEmptyHeadingsFromGoogleDocsExport(): void
    {
        $html = '<h1>' . "\u{200B}" . '</h1><h1></h1><h1>T3Planet Support System</h1><p>Body copy</p>';
        $sections = $this->subject->parse($html);

        self::assertCount(1, $sections);
        self::assertSame('T3Planet Support System', $sections[0]['title']);
        self::assertStringContainsString('Body copy', $sections[0]['html']);
    }

    #[Test]
    public function ignoresWhitespaceOnlyLeadIn(): void
    {
        $html = '<p>' . "\u{00A0}" . '</p><h1>Only Real Section</h1><p>Text</p>';
        $sections = $this->subject->parse($html);

        self::assertCount(1, $sections);
        self::assertSame('Only Real Section', $sections[0]['title']);
    }

    #[Test]
    public function usesFirstInlineRunAsHeadingTitleWhenBodyIsNestedInHeading(): void
    {
        $html = '<h1><span>Heading1</span><span>It is a long established fact that a reader will be distracted.</span></h1>'
            . '<h2><span>Heading 2</span><span>More body copy here.</span></h2>';
        $sections = $this->subject->parse($html);

        self::assertCount(2, $sections);
        self::assertSame('Heading1', $sections[0]['title']);
        self::assertSame('Heading 2', $sections[1]['title']);
        self::assertStringContainsString('long established', $sections[0]['html']);
        self::assertStringContainsString('More body copy', $sections[1]['html']);
    }

    #[Test]
    public function splitsTitleAndBodyOnBrInsideHeadingLikeGoogleDocs(): void
    {
        $html = '<h1><span>Heading1<br>It is a long established fact that a reader will be distracted.</span></h1>'
            . '<h2><span>Heading 2<br>More body copy here.</span></h2>';
        $sections = $this->subject->parse($html);

        self::assertCount(2, $sections);
        self::assertSame('Heading1', $sections[0]['title']);
        self::assertSame('Heading 2', $sections[1]['title']);
        self::assertStringContainsString('long established', $sections[0]['html']);
        self::assertStringContainsString('More body copy', $sections[1]['html']);
    }
}
