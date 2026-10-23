<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Service;

/**
 * Maps Extbase plugin POST/query data into a typed argument bag.
 * Plugin-namespace arrays are preferred; one-level googleDocsID unwrap covers
 * the known PHP collision where a scalar id becomes an array.
 */
final class ImportRequestMapper
{
    /**
     * @param array<string, mixed>|null $parsedBody
     * @param array<string, mixed> $queryParams
     * @param array<string, mixed> $extbaseArguments
     * @return array{
     *     googleDocsID: string,
     *     pageId: int,
     *     id: int,
     *     colPos: int,
     *     sections: array<int|string, mixed>,
     *     googleDocsName: string,
     *     GoogleDocsClientId: string,
     *     GoogleDocsSecretKey: string,
     *     GoogleDocsRefreshToken: string,
     *     importType: list<string>
     * }
     */
    public static function map(?array $parsedBody, array $queryParams = [], array $extbaseArguments = []): array
    {
        $bag = self::selectPluginBag($parsedBody, $extbaseArguments);
        $pageId = (int)($queryParams['id'] ?? 0);
        if ($pageId <= 0) {
            $pageId = (int)($bag['pageId'] ?? $bag['id'] ?? 0);
        }
        if ($pageId <= 0 && is_array($parsedBody)) {
            $pageId = (int)($parsedBody['id'] ?? $parsedBody['pageId'] ?? 0);
        }

        $importType = $bag['importType'] ?? [];
        if (!is_array($importType)) {
            $importType = $importType !== '' && $importType !== null ? [(string)$importType] : [];
        }
        $importType = array_values(array_filter(
            array_map(static fn(mixed $value): string => trim((string)$value), $importType),
            static fn(string $value): bool => $value !== ''
        ));

        $sections = $bag['sections'] ?? [];
        if (!is_array($sections)) {
            $sections = [];
        }

        return [
            'googleDocsID' => self::extractGoogleDocsId($bag),
            'pageId' => $pageId,
            'id' => $pageId,
            'colPos' => (int)($bag['colPos'] ?? 0),
            'sections' => $sections,
            'googleDocsName' => trim((string)($bag['googleDocsName'] ?? '')),
            'GoogleDocsClientId' => trim((string)($bag['GoogleDocsClientId'] ?? '')),
            'GoogleDocsSecretKey' => trim((string)($bag['GoogleDocsSecretKey'] ?? '')),
            'GoogleDocsRefreshToken' => trim((string)($bag['GoogleDocsRefreshToken'] ?? '')),
            'importType' => $importType,
        ];
    }

    /**
     * @param array<string, mixed>|null $parsedBody
     * @param array<string, mixed> $extbaseArguments
     * @return array<string, mixed>
     */
    private static function selectPluginBag(?array $parsedBody, array $extbaseArguments): array
    {
        $candidates = [];
        if (is_array($parsedBody)) {
            foreach ($parsedBody as $key => $value) {
                if (is_array($value) && self::looksLikePluginBag($value)) {
                    $candidates[] = $value;
                }
            }
            if (self::looksLikePluginBag($parsedBody)) {
                $candidates[] = $parsedBody;
            }
        }
        if ($extbaseArguments !== [] && self::looksLikePluginBag($extbaseArguments)) {
            $candidates[] = $extbaseArguments;
        } elseif ($extbaseArguments !== []) {
            $candidates[] = $extbaseArguments;
        }

        $best = [];
        foreach ($candidates as $candidate) {
            if (self::extractGoogleDocsId($candidate) !== '') {
                return $candidate;
            }
            if ($best === [] || (isset($candidate['sections']) && !isset($best['sections']))) {
                $best = $candidate;
            }
        }

        return $best;
    }

    /**
     * @param array<string, mixed> $value
     */
    private static function looksLikePluginBag(array $value): bool
    {
        return array_key_exists('GoogleDocsClientId', $value)
            || array_key_exists('googleDocsID', $value)
            || array_key_exists('googleDocsId', $value)
            || array_key_exists('GoogleDocsSecretKey', $value)
            || array_key_exists('GoogleDocsRefreshToken', $value)
            || array_key_exists('importType', $value)
            || array_key_exists('colPos', $value)
            || array_key_exists('sections', $value)
            || array_key_exists('pageId', $value)
            || array_key_exists('googleDocsName', $value);
    }

    /**
     * @param array<string, mixed> $arguments
     */
    public static function extractGoogleDocsId(array $arguments): string
    {
        foreach (['googleDocsID', 'googleDocsId'] as $key) {
            if (!array_key_exists($key, $arguments)) {
                continue;
            }
            $id = self::scalarId($arguments[$key]);
            if ($id !== '') {
                return $id;
            }
        }
        return '';
    }

    private static function scalarId(mixed $value): string
    {
        if (is_string($value) || is_numeric($value)) {
            return trim((string)$value);
        }
        if (!is_array($value)) {
            return '';
        }
        foreach (['id', 'value', 'googleDocsID', 'googleDocsId'] as $nestedKey) {
            if (isset($value[$nestedKey]) && (is_string($value[$nestedKey]) || is_numeric($value[$nestedKey]))) {
                $id = trim((string)$value[$nestedKey]);
                if ($id !== '') {
                    return $id;
                }
            }
        }
        foreach ($value as $item) {
            if (is_string($item) || is_numeric($item)) {
                $id = trim((string)$item);
                if ($id !== '') {
                    return $id;
                }
            }
        }
        return '';
    }
}
