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
        self::assertSame('Lead-in', $sections[0]['fallbackTitle']);
        self::assertSame('', $sections[1]['fallbackTitle']);
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

    #[Test]
    public function ignoresHeadingsContainingOnlyInvisibleCharactersOrBreaks(): void
    {
        $html = '<h1>&nbsp;</h1>'
            . '<h2><span>' . "\u{200B}\u{2060}\u{FEFF}" . '</span></h2>'
            . '<h1><br></h1>'
            . '<h2>' . "\u{3000}\u{202F}\t " . '</h2>'
            . '<h1>Real heading</h1><p>Body</p>'
            . '<h2>' . "\u{00A0}" . '</h2><p>More body</p>';
        $sections = $this->subject->parse($html);

        self::assertCount(1, $sections);
        self::assertSame('Real heading', $sections[0]['title']);
        self::assertStringContainsString('Body', $sections[0]['html']);
        self::assertStringContainsString('More body', $sections[0]['html']);
    }

    #[Test]
    public function neverReturnsBlankTitleForSectionWithTextBody(): void
    {
        $html = '<h1>' . "\u{200B}" . '</h1>'
            . '<p>This lead paragraph is long enough to be shortened for the import dialog title column.</p>'
            . '<h1>Next</h1><p>Text</p>';
        $sections = $this->subject->parse($html);

        self::assertCount(2, $sections);
        self::assertSame('', $sections[0]['title']);
        self::assertSame('This lead paragraph is long enough to be shortened for the…', $sections[0]['fallbackTitle']);
        foreach ($sections as $section) {
            self::assertNotSame('', trim($section['title'] . $section['fallbackTitle']));
        }
    }

    #[Test]
    public function keepsImageOfImageOnlyHeadingAsSectionBody(): void
    {
        $html = '<h1><span><img src="https://example.com/a.png"></span></h1><h2>Next</h2><p>Text</p>';
        $sections = $this->subject->parse($html);

        self::assertCount(2, $sections);
        self::assertSame('', $sections[0]['title']);
        self::assertTrue($sections[0]['hasImages']);
        self::assertStringNotContainsString('<h1', $sections[0]['html']);
        self::assertSame('Next', $sections[1]['title']);
    }

    #[Test]
    public function splitsSubHeadingWithBrIntoHeadingAndParagraph(): void
    {
        $html = '<h1>Heading 2</h1><p>Intro text.</p><p></p><p><span></span></p>'
            . '<h3><span>Heading 3<br>It is a long established fact.</span></h3>';
        $sections = $this->subject->parse($html);

        self::assertCount(1, $sections);
        $body = $sections[0]['html'];
        self::assertStringContainsString('<h3>Heading 3</h3>', $body);
        self::assertMatchesRegularExpression('#<p>(<span>)?It is a long established fact\.#', $body);
        self::assertStringNotContainsString('<p></p>', $body);
        self::assertStringNotContainsString('Heading 3It is', strip_tags($body));
        self::assertStringContainsString("Heading 3\n", strip_tags($body));
    }

    #[Test]
    public function separatesBlockElementsAndLineBreaksWithNewlines(): void
    {
        $html = '<h1>List</h1><h4>Sub</h4><p>Line one<br>Line two</p><ul><li>One</li><li>Two</li></ul>';
        $sections = $this->subject->parse($html);

        self::assertCount(1, $sections);
        $text = strip_tags($sections[0]['html']);
        self::assertStringContainsString("Sub\n", $text);
        self::assertStringContainsString("Line one\nLine two", $text);
        self::assertStringContainsString("One\nTwo", $text);
        self::assertStringContainsString('<h4>Sub</h4>', $sections[0]['html']);
    }
}
