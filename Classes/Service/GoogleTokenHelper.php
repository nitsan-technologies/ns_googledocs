<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Service;

/**
 * Validation and error explanation for Google OAuth credentials.
 */
final class GoogleTokenHelper
{
    public const INVALID_REFRESH_TOKEN_MESSAGE = 'The value entered as "Refresh Token" looks like a short-lived OAuth access token '
        . '(it starts with "ya29."). Access tokens expire after about one hour and then fail with "invalid_grant". '
        . 'Please paste the refresh token instead — it usually starts with "1//" (e.g. in the OAuth Playground, '
        . 'use "Exchange authorization code for tokens" and copy the "Refresh token" field).';

    public const INVALID_GRANT_MESSAGE = 'Google rejected the stored refresh token (invalid_grant). The token has expired, '
        . 'was revoked, or an access token (starting with "ya29.") was saved instead of a refresh token (usually starting '
        . 'with "1//"). Generate a new refresh token and save it under Global Settings. Note: refresh tokens of OAuth apps '
        . 'in "Testing" publishing status expire after 7 days.';

    /**
     * @return string|null Error message, or null when the value is acceptable.
     */
    public static function validateRefreshToken(string $token): ?string
    {
        $token = trim($token);
        if ($token === '') {
            return null;
        }
        if (str_starts_with($token, 'ya29.')) {
            return self::INVALID_REFRESH_TOKEN_MESSAGE;
        }
        if (preg_match('/\s/', $token)) {
            return 'The refresh token must not contain spaces or line breaks.';
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
