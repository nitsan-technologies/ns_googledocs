<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Service;

/**
 * Splits Google Docs HTML into sections by Heading 1 / Heading 2 (Reformation / v14).
 *
 * Google Docs exports often include empty H1/H2 nodes and zero-width spaces; those are ignored
 * so the import dialog does not show duplicate blank rows.
 *
 * @see https://docs.t3planet.de/en/latest/ExtNsGoogleDocs/Reformation/Index
 */
final class GoogleDocsSectionParser
{
    /**
     * @return list<array{index: int, title: string, headingLevel: int, html: string, hasImages: bool}>
     */
    public function parse(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return [];
        }

        $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
        $dom = new \DOMDocument();
        try {
            @$dom->loadHTML($wrapped, LIBXML_NOERROR | LIBXML_NOWARNING);
        } catch (\ValueError) {
            return [];
        }
        $bodies = $dom->getElementsByTagName('body');
        $body = $bodies->item(0);
        if (!$body instanceof \DOMElement) {
            return [];
        }

        $sections = [];
        $currentTitle = '';
        $currentLevel = 1;
        $currentNodes = [];
        $started = false;

        foreach ($this->flattenBlockNodes($body) as $node) {
            if ($node instanceof \DOMElement && in_array(strtolower($node->tagName), ['h1', 'h2'], true)) {
                $headingTitle = $this->extractHeadingTitle($node);
                // Google Docs often emits empty heading tags — do not open a new section for them.
                if ($headingTitle === '') {
                    continue;
                }
                if ($started || $this->nodesHaveContent($currentNodes)) {
                    $section = $this->buildSection($dom, $currentTitle, $currentLevel, $currentNodes);
                    if ($this->sectionIsWorthKeeping($section)) {
                        $sections[] = $section;
                    }
                }
                $currentTitle = $headingTitle;
                $currentLevel = (int)substr(strtolower($node->tagName), 1);
                // Google often nests body copy inside the heading element as extra spans.
                // Keep that leftover content in the section body instead of dropping it.
                $currentNodes = $this->collectHeadingLeftoverNodes($node, $headingTitle);
                $started = true;
                continue;
            }

            if (!$started && $this->isIgnorableEmptyNode($node)) {
                continue;
            }

            if (!$started) {
                // Content before the first real H1/H2 becomes an untitled lead section.
                $started = true;
                $currentTitle = '';
                $currentLevel = 1;
            }
            $currentNodes[] = $node;
        }

        if ($started || $this->nodesHaveContent($currentNodes)) {
            $section = $this->buildSection($dom, $currentTitle, $currentLevel, $currentNodes);
            if ($this->sectionIsWorthKeeping($section)) {
                $sections[] = $section;
            }
        }

        $indexed = [];
        foreach ($sections as $i => $section) {
            $section['index'] = $i;
            $indexed[] = $section;
        }

        return $indexed;
    }

    /**
     * Google Docs HTML may wrap blocks in nested divs; walk in document order for block-level nodes.
     *
     * @return list<\DOMNode>
     */
    private function flattenBlockNodes(\DOMElement $body): array
    {
        $nodes = [];
        foreach (iterator_to_array($body->childNodes) as $child) {
            $this->collectBlockNodes($child, $nodes);
        }
        return $nodes;
    }

    /**
     * @param list<\DOMNode> $nodes
     */
    private function collectBlockNodes(\DOMNode $node, array &$nodes): void
    {
        if ($node instanceof \DOMElement && strtolower($node->tagName) === 'div') {
            foreach (iterator_to_array($node->childNodes) as $child) {
                $this->collectBlockNodes($child, $nodes);
            }
            return;
        }
        $nodes[] = $node;
    }

    /**
     * @param list<\DOMNode> $nodes
     * @return array{index: int, title: string, headingLevel: int, html: string, hasImages: bool}
     */
    private function buildSection(\DOMDocument $dom, string $title, int $headingLevel, array $nodes): array
    {
        $fragment = $dom->createDocumentFragment();
        foreach ($nodes as $node) {
            $fragment->appendChild($node->cloneNode(true));
        }
        $tmp = $dom->createElement('div');
        if ($fragment->hasChildNodes()) {
            $tmp->appendChild($fragment);
        }
        $html = '';
        foreach (iterator_to_array($tmp->childNodes) as $child) {
            $html .= $dom->saveHTML($child);
        }
        $html = trim($html);
        $hasImages = (bool)preg_match('/<img\b/i', $html);

        return [
            'index' => 0,
            'title' => $title,
            'headingLevel' => $headingLevel,
            'html' => $html,
            'hasImages' => $hasImages,
        ];
    }

    /**
     * @param array{index: int, title: string, headingLevel: int, html: string, hasImages: bool} $section
     */
    private function sectionIsWorthKeeping(array $section): bool
    {
        if ($section['title'] !== '') {
            return true;
        }
        if ($section['hasImages']) {
            return true;
        }
        return $this->normalizeText(strip_tags($section['html'])) !== '';
    }

    /**
     * @param list<\DOMNode> $nodes
     */
    private function nodesHaveContent(array $nodes): bool
    {
        foreach ($nodes as $node) {
            if ($this->isIgnorableEmptyNode($node)) {
                continue;
            }
            if ($this->normalizeText($node->textContent ?? '') !== '') {
                return true;
            }
            if ($node instanceof \DOMElement && $node->getElementsByTagName('img')->length > 0) {
                return true;
            }
        }
        return false;
    }

    private function isIgnorableEmptyNode(\DOMNode $node): bool
    {
        if ($node instanceof \DOMText) {
            return $this->normalizeText($node->textContent) === '';
        }
        if ($node instanceof \DOMComment) {
            return true;
        }
        if ($node instanceof \DOMElement) {
            $hasImg = $node->getElementsByTagName('img')->length > 0;
            return $this->normalizeText($node->textContent) === '' && !$hasImg;
        }
        return true;
    }

    /**
     * Google Docs often puts "Title<br>body…" inside one heading span.
     * Title is the text before the first <br>; leftover becomes section body.
     */
    private function extractHeadingTitle(\DOMElement $heading): string
    {
        [$title] = $this->splitHeadingInnerHtml($heading);
        return $title;
    }

    /**
     * @return list<\DOMNode>
     */
    private function collectHeadingLeftoverNodes(\DOMElement $heading, string $title): array
    {
        [, $bodyHtml] = $this->splitHeadingInnerHtml($heading);
        unset($title);
        $bodyHtml = trim($bodyHtml);
        if ($bodyHtml === '') {
            return [];
        }

        $dom = $heading->ownerDocument;
        if (!$dom instanceof \DOMDocument) {
            return [];
        }

        $tmp = new \DOMDocument();
        try {
            @$tmp->loadHTML(
                '<!DOCTYPE html><html><body><div id="ns-gd-heading-leftover">' . $bodyHtml . '</div></body></html>',
                LIBXML_NOERROR | LIBXML_NOWARNING
            );
        } catch (\ValueError) {
            $p = $dom->createElement('p');
            $p->appendChild($dom->createTextNode(strip_tags($bodyHtml)));
            return [$p];
        }

        $container = $tmp->getElementById('ns-gd-heading-leftover');
        if (!$container instanceof \DOMElement) {
            return [];
        }

        $wrapper = $dom->createElement('p');
        foreach (iterator_to_array($container->childNodes) as $child) {
            $wrapper->appendChild($dom->importNode($child, true));
        }
        return [$wrapper];
    }

    /**
     * @return array{0: string, 1: string} [title, bodyHtml]
     */
    private function splitHeadingInnerHtml(\DOMElement $heading): array
    {
        $dom = $heading->ownerDocument;
        $inner = '';
        if ($dom instanceof \DOMDocument) {
            foreach (iterator_to_array($heading->childNodes) as $child) {
                $inner .= $dom->saveHTML($child);
            }
        }

        $parts = preg_split('/<br\s*\/?>/i', $inner, 2);
        $titleHtml = (string)($parts[0] ?? '');
        $bodyHtml = (string)($parts[1] ?? '');
        $title = $this->normalizeText(html_entity_decode(strip_tags($titleHtml), ENT_QUOTES, 'UTF-8'));
        if ($title === '') {
            $title = $this->normalizeText($heading->textContent ?? '');
        }
        return [$title, $bodyHtml];
    }

    private function normalizeText(string $text): string
    {
        // Strip NBSP / zero-width / BOM that Google Docs often leaves in "empty" headings.
        $text = str_replace(["\u{00A0}", "\u{200B}", "\u{200C}", "\u{200D}", "\u{FEFF}"], ' ', $text);
        return trim($text);
    }
}
