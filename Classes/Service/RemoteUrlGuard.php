<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Service;

/**
 * Pure URL allow-list helpers (no TYPO3 runtime required).
 */
final class RemoteUrlGuard
{
    public static function isAllowedHttpsUrl(string $url): bool
    {
        if ($url === '' || !preg_match('#^https://#i', $url)) {
            return false;
        }
        $parts = parse_url($url);
        return isset($parts['host']) && is_string($parts['host']) && $parts['host'] !== '';
    }

    public static function isAllowedHttpUrl(string $url): bool
    {
        if ($url === '' || !preg_match('#^https?://#i', $url)) {
            return false;
        }
        $parts = parse_url($url);
        return isset($parts['host']) && is_string($parts['host']) && $parts['host'] !== '';
    }
}
