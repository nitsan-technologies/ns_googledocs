<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Service;

/**
 * Splits section HTML at images so each image can become its own FAL-backed
 * content element while surrounding copy stays in document order.
 *
 * HTML without images is returned unchanged (no DOM round-trip).
 */
final class ImportSectionSplitter
{
    /**
     * Blocks that must not be torn apart when they contain an image.
     * Those images stay inline (previous behaviour) rather than breaking tables/lists.
     */
    private const ATOMIC_TAGS = ['table', 'pre', 'svg', 'ul', 'ol', 'canvas', 'video', 'audio', 'iframe', 'object'];

    /**
     * @return list<array{type: 'text'|'image', html: string}>
     */
    public function split(string $html): array
    {
        $html = trim($html);
        if ($html === '') {
            return [['type' => 'text', 'html' => '']];
        }
        if (!preg_match('/<img\b/i', $html)) {
            return [['type' => 'text', 'html' => $html]];
        }

        $dom = new \DOMDocument();
        $wrapped = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
        try {
            $loaded = @$dom->loadHTML($wrapped, LIBXML_NOERROR | LIBXML_NOWARNING);
        } catch (\ValueError) {
            return [['type' => 'text', 'html' => $html]];
        }
        if ($loaded === false) {
            return [['type' => 'text', 'html' => $html]];
        }
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body instanceof \DOMElement) {
            return [['type' => 'text', 'html' => $html]];
        }

        $chunks = $this->splitNodes($dom, iterator_to_array($body->childNodes), null);
        $chunks = $this->coalesce($chunks);
        if ($chunks === []) {
            return [['type' => 'text', 'html' => $html]];
        }
        return $chunks;
    }

    /**
     * @param list<\DOMNode> $nodes
     * @return list<array{type: 'text'|'image', html: string}>
     */
    private function splitNodes(\DOMDocument $dom, array $nodes, ?\DOMElement $wrap): array
    {
        $chunks = [];
        $buffer = [];

        foreach ($nodes as $node) {
            if ($node instanceof \DOMElement && strtolower($node->tagName) === 'img') {
                $this->flushText($dom, $buffer, $wrap, $chunks);
                $chunks[] = ['type' => 'image', 'html' => (string)$dom->saveHTML($node)];
                continue;
            }

            if ($node instanceof \DOMElement && $this->containsImage($node)) {
                if ($this->isAtomicBlock($node)) {
                    $this->flushText($dom, $buffer, $wrap, $chunks);
                    $html = (string)$dom->saveHTML($node);
                    if (!$this->isEffectivelyEmptyText($html)) {
                        $chunks[] = ['type' => 'text', 'html' => $html];
                    }
                    continue;
                }
                if ($this->isImageOnlyElement($node)) {
                    $this->flushText($dom, $buffer, $wrap, $chunks);
                    foreach ($node->getElementsByTagName('img') as $img) {
                        $chunks[] = ['type' => 'image', 'html' => (string)$dom->saveHTML($img)];
                    }
                    continue;
                }
                $this->flushText($dom, $buffer, $wrap, $chunks);
                $nextWrap = $wrap instanceof \DOMElement ? $wrap : $node;
                foreach ($this->splitNodes($dom, iterator_to_array($node->childNodes), $nextWrap) as $inner) {
                    $chunks[] = $inner;
                }
                continue;
            }

            $buffer[] = $node;
        }

        $this->flushText($dom, $buffer, $wrap, $chunks);
        return $chunks;
    }

    /**
     * @param list<\DOMNode> $buffer
     * @param list<array{type: 'text'|'image', html: string}> $chunks
     */
    private function flushText(\DOMDocument $dom, array &$buffer, ?\DOMElement $wrap, array &$chunks): void
    {
        if ($buffer === []) {
            return;
        }
        $html = $this->serializeNodes($dom, $buffer);
        $buffer = [];
        if ($wrap instanceof \DOMElement && strtolower($wrap->tagName) !== 'body') {
            $html = $this->wrapInnerHtml($wrap, $html);
        }
        $html = trim(GoogleDocsSectionParser::stripXmlArtifacts($html));
        if ($html === '' || $this->isEffectivelyEmptyText($html)) {
            return;
        }
        $chunks[] = ['type' => 'text', 'html' => $html];
    }

    /**
     * @param list<\DOMNode> $nodes
     */
    private function serializeNodes(\DOMDocument $dom, array $nodes): string
    {
        $html = '';
        foreach ($nodes as $node) {
            $html .= (string)$dom->saveHTML($node);
        }
        return $html;
    }

    private function wrapInnerHtml(\DOMElement $parent, string $innerHtml): string
    {
        $tag = strtolower($parent->tagName);
        if ($tag === '' || $tag === 'body') {
            return $innerHtml;
        }
        return '<' . $tag . '>' . $innerHtml . '</' . $tag . '>';
    }

    private function containsImage(\DOMElement $element): bool
    {
        return $element->getElementsByTagName('img')->length > 0;
    }

    private function isAtomicBlock(\DOMElement $element): bool
    {
        return in_array(strtolower($element->tagName), self::ATOMIC_TAGS, true);
    }

    private function isImageOnlyElement(\DOMElement $element): bool
    {
        $text = $this->normalizeText($element->textContent ?? '');
        return $text === '' && $this->containsImage($element);
    }

    /**
     * @param list<array{type: 'text'|'image', html: string}> $chunks
     * @return list<array{type: 'text'|'image', html: string}>
     */
    private function coalesce(array $chunks): array
    {
        $out = [];
        foreach ($chunks as $chunk) {
            $html = trim($chunk['html']);
            if ($chunk['type'] === 'text') {
                if ($html === '' || $this->isEffectivelyEmptyText($html)) {
                    continue;
                }
                $out[] = ['type' => 'text', 'html' => $html];
                continue;
            }
            if ($html === '') {
                continue;
            }
            $last = array_key_last($out);
            if ($last !== null && $out[$last]['type'] === 'image') {
                $out[$last]['html'] .= $html;
                continue;
            }
            $out[] = ['type' => 'image', 'html' => $html];
        }
        return $out;
    }

    private function isEffectivelyEmptyText(string $html): bool
    {
        if (preg_match('/<img\b/i', $html)) {
            return false;
        }
        return $this->normalizeText(strip_tags($html)) === '';
    }

    private function normalizeText(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/[\p{Cf}\p{Z}\s]+/u', '', $text) ?? $text;
        return $text;
    }
}
