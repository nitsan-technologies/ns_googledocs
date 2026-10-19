<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Service;

/**
 * Decides on which page types (doktype) a Google Doc may be imported.
 *
 * Only standard pages (doktype 1) are allowed by default. Sysfolders (254), spacers (199),
 * shortcuts (4), external links (3), mount points (7), backend user sections (6) and the
 * recycler (255) never render tt_content in the frontend, so importing there is blocked.
 * Integrators can extend the allowlist via the extension setting "allowedImportDoktypes"
 * (comma-separated, e.g. "1,116" for custom page types).
 */
final class ImportPageTypeGuard
{
    public const DEFAULT_ALLOWED = [1];

    /**
     * Doktypes that can never hold importable content, even when listed in the allowlist.
     */
    public const ALWAYS_BLOCKED = [3, 4, 6, 7, 199, 254, 255];

    /**
     * @param list<int> $allowedDoktypes
     */
    public static function isAllowed(int $doktype, array $allowedDoktypes = self::DEFAULT_ALLOWED): bool
    {
        if ($doktype <= 0 || in_array($doktype, self::ALWAYS_BLOCKED, true)) {
            return false;
        }
        return in_array($doktype, $allowedDoktypes, true);
    }

    /**
     * @return list<int>
     */
    public static function parseAllowlist(mixed $configured): array
    {
        $values = [];
        foreach (explode(',', is_scalar($configured) ? (string)$configured : '') as $part) {
            $part = trim($part);
            if ($part !== '' && ctype_digit($part) && (int)$part > 0) {
                $values[] = (int)$part;
            }
        }
        $values = array_values(array_unique($values));
        return $values !== [] ? $values : self::DEFAULT_ALLOWED;
    }
}
