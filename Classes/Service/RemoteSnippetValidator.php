<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Service;

/**
 * Remote marketing blocks (setup wizard, support, footer) must be small HTML snippets.
 * Full documents and server error / redirect pages (e.g. "301 Moved Permanently") are rejected
 * so they are never injected into the backend module.
 */
final class RemoteSnippetValidator
{
    public static function isValid(string $html): bool
    {
        $html = trim($html);
        if ($html === '') {
            return false;
        }
        if (preg_match('/<!DOCTYPE|<html[\s>]|<head[\s>]|<title[\s>]/i', $html)) {
            return false;
        }
        if (preg_match('/\b(?:30[1-8]|40[0-9]|50[0-9])\s+(?:Moved Permanently|Found|See Other|Temporary Redirect|Permanent Redirect|Bad Request|Unauthorized|Forbidden|Not Found|Internal Server Error|Bad Gateway|Service Unavailable|Gateway Time-?out)\b/i', $html)) {
            return false;
        }
        return true;
    }

    public static function filter(string $html): string
    {
        return self::isValid($html) ? $html : '';
    }
}
