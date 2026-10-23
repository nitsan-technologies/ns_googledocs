<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Service;

/**
 * Maps import dialog CTypes to types the target page actually allows.
 *
 * header → text and image → textpic only when those types are missing from TCA
 * or listed in TCEFORM.tt_content.CType.removeItems (e.g. theme_camino).
 */
final class ImportContentElementTypeResolver
{
    public const TYPES = ['header', 'text', 'textpic', 'image'];

    /**
     * @param callable(string): bool $isAvailable
     */
    public static function normalize(string $cType, callable $isAvailable): string
    {
        if (!in_array($cType, self::TYPES, true)) {
            $cType = 'textpic';
        }
        if ($isAvailable($cType)) {
            return $cType;
        }
        $fallback = self::fallback($cType);
        if ($isAvailable($fallback)) {
            return $fallback;
        }
        foreach (['textpic', 'text'] as $candidate) {
            if ($isAvailable($candidate)) {
                return $candidate;
            }
        }
        return $fallback;
    }

    public static function fallback(string $cType): string
    {
        return match ($cType) {
            'header' => 'text',
            'image' => 'textpic',
            default => in_array($cType, self::TYPES, true) ? $cType : 'textpic',
        };
    }

    /**
     * @param list<string> $removed
     */
    public static function isAllowed(string $cType, bool $existsInTca, array $removed): bool
    {
        if (!in_array($cType, self::TYPES, true) || !$existsInTca) {
            return false;
        }
        return !in_array($cType, $removed, true);
    }

    /**
     * @return list<string>
     */
    public static function parseRemoveItems(mixed $removeItems): array
    {
        if (!is_scalar($removeItems)) {
            return [];
        }
        $values = [];
        foreach (explode(',', (string)$removeItems) as $part) {
            $part = trim($part);
            if ($part !== '') {
                $values[] = $part;
            }
        }
        return array_values(array_unique($values));
    }

    /**
     * @param array<string, string> $labels
     * @param callable(string): bool $isAvailable
     * @return array<string, string>
     */
    public static function filterOptions(array $labels, callable $isAvailable): array
    {
        $filtered = [];
        foreach ($labels as $cType => $label) {
            if ($isAvailable((string)$cType)) {
                $filtered[(string)$cType] = $label;
            }
        }
        return $filtered !== [] ? $filtered : [
            'text' => $labels['text'] ?? 'Text Element',
            'textpic' => $labels['textpic'] ?? 'Text & Image',
        ];
    }
}
