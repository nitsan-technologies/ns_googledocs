<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Service;

/**
 * Validation and error explanation for Google OAuth credentials.
 */
final class GoogleTokenHelper
{
    public const INVALID_GRANT_MESSAGE = 'Google rejected the stored token (invalid_grant). It may have expired or been '
        . 'revoked. Generate a new token and save it under Global Settings. Note: OAuth apps in "Testing" publishing '
        . 'status expire refresh tokens after 7 days; access tokens (starting with "ya29.") expire after about one hour.';

    /**
     * True when the value is a Google OAuth access token (usable until it expires).
     */
    public static function isAccessToken(string $token): bool
    {
        return str_starts_with(trim($token), 'ya29.');
    }

    /**
     * @return string|null Error message, or null when the value is acceptable.
     */
    public static function validateRefreshToken(string $token): ?string
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        if (preg_match('/\s/', $token)) {
            return 'The token must not contain spaces or line breaks.';
        }
        return null;
    }

    public static function isInvalidGrant(string $message): bool
    {
        return stripos($message, 'invalid_grant') !== false;
    }

    /**
     * Turn raw Google API errors into an actionable message.
     */
    public static function explain(string $message): string
    {
        if (self::isInvalidGrant($message)) {
            return self::INVALID_GRANT_MESSAGE;
        }
        if (stripos($message, 'UNAUTHENTICATED') !== false || stripos($message, 'invalid authentication credentials') !== false
            || preg_match('/"code"\s*:\s*401/', $message)
        ) {
            return 'Google did not accept the access credentials (401). Check Client ID, Client Secret and the refresh token '
                . 'under Global Settings. ' . self::INVALID_GRANT_MESSAGE;
        }
        return $message;
    }
}
