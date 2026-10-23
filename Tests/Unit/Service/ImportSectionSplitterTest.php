<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Tests\Unit\Service;

use NITSAN\NsGoogledocs\Service\ImportSectionSplitter;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ImportSectionSplitterTest extends TestCase
{
    private ImportSectionSplitter $subject;

    protected function setUp(): void
    {
        parent::setUp();
        $this->subject = new ImportSectionSplitter();
    }

    #[Test]
    public function htmlWithoutImagesIsReturnedUnchanged(): void
    {
        $html = '<h3>Title</h3><p>First paragraph.</p><p>Second paragraph.</p>';
        $chunks = $this->subject->split($html);

        self::assertCount(1, $chunks);
        self::assertSame('text', $chunks[0]['type']);
        self::assertSame($html, $chunks[0]['html']);
    }

    #[Test]
    public function emptyHtmlYieldsASingleEmptyTextChunk(): void
    {
        $chunks = $this->subject->split('   ');

        self::assertCount(1, $chunks);
        self::assertSame('text', $chunks[0]['type']);
        self::assertSame('', $chunks[0]['html']);
    }

    #[Test]
    public function splitsTextImageTextInDocumentOrder(): void
    {
        $html = '<p>Intro</p><p><img src="https://example.com/a.png" alt="A"></p><p>Outro</p>';
        $chunks = $this->subject->split($html);

        self::assertCount(3, $chunks);
        self::assertSame('text', $chunks[0]['type']);
        self::assertStringContainsString('Intro', $chunks[0]['html']);
        self::assertStringNotContainsString('<img', $chunks[0]['html']);

        self::assertSame('image', $chunks[1]['type']);
        self::assertStringContainsString('a.png', $chunks[1]['html']);

        self::assertSame('text', $chunks[2]['type']);
        self::assertStringContainsString('Outro', $chunks[2]['html']);
        self::assertStringNotContainsString('<img', $chunks[2]['html']);
    }

    #[Test]
    public function imageOnlyParagraphBecomesAnImageChunk(): void
    {
        $html = '<p><span><img src="https://example.com/only.png"></span></p>';
        $chunks = $this->subject->split($html);

        self::assertCount(1, $chunks);
        self::assertSame('image', $chunks[0]['type']);
        self::assertStringContainsString('only.png', $chunks[0]['html']);
        self::assertStringNotContainsString('<p>', $chunks[0]['html']);
    }

    #[Test]
    public function mixedParagraphSplitsAroundTheImage(): void
    {
        $html = '<p>Hello <img src="https://example.com/inline.png"> world</p>';
        $chunks = $this->subject->split($html);

        self::assertCount(3, $chunks);
        self::assertSame('text', $chunks[0]['type']);
        self::assertStringContainsString('Hello', $chunks[0]['html']);
        self::assertStringNotContainsString('<img', $chunks[0]['html']);

        self::assertSame('image', $chunks[1]['type']);
        self::assertStringContainsString('inline.png', $chunks[1]['html']);

        self::assertSame('text', $chunks[2]['type']);
        self::assertStringContainsString('world', $chunks[2]['html']);
        self::assertStringNotContainsString('<img', $chunks[2]['html']);
    }

    #[Test]
    public function consecutiveImagesMergeIntoOneImageChunk(): void
    {
        $html = '<p><img src="https://example.com/a.png"></p><p><img src="https://example.com/b.png"></p>';
        $chunks = $this->subject->split($html);

        self::assertCount(1, $chunks);
        self::assertSame('image', $chunks[0]['type']);
        self::assertStringContainsString('a.png', $chunks[0]['html']);
        self::assertStringContainsString('b.png', $chunks[0]['html']);
    }

    #[Test]
    public function imagesInsideTablesStayInlineSoTheTableIsNotBroken(): void
    {
        $html = '<p>Before</p><table><tr><td><img src="https://example.com/cell.png"></td><td>Cell</td></tr></table><p>After</p>';
        $chunks = $this->subject->split($html);

        self::assertCount(3, $chunks);
        self::assertSame('text', $chunks[0]['type']);
        self::assertStringContainsString('Before', $chunks[0]['html']);

        self::assertSame('text', $chunks[1]['type']);
        self::assertStringContainsString('<table', $chunks[1]['html']);
        self::assertStringContainsString('cell.png', $chunks[1]['html']);

        self::assertSame('text', $chunks[2]['type']);
        self::assertStringContainsString('After', $chunks[2]['html']);
    }

    #[Test]
    public function emptyParagraphsAroundImagesDoNotCreateEmptyTextChunks(): void
    {
        $html = '<p></p><p><img src="https://example.com/a.png"></p><p>&nbsp;</p>';
        $chunks = $this->subject->split($html);

        self::assertCount(1, $chunks);
        self::assertSame('image', $chunks[0]['type']);
    }
}
