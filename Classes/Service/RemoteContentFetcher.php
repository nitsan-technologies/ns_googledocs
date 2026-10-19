<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Service;

use TYPO3\CMS\Core\Http\RequestFactory;

/**
 * HTTP helper with timeouts for remote API and image downloads.
 */
final class RemoteContentFetcher
{
    private const DEFAULT_TIMEOUT = 10;
    private const MAX_BODY_BYTES = 10485760;

    public function __construct(
        private readonly RequestFactory $requestFactory
    ) {}

    public function fetchText(string $url, int $timeout = self::DEFAULT_TIMEOUT): string
    {
        if (!RemoteUrlGuard::isAllowedHttpUrl($url)) {
            return '';
        }

        try {
            $response = $this->requestFactory->request($url, 'GET', [
                'timeout' => $timeout,
                'allow_redirects' => true,
            ]);
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return '';
            }
            $body = (string)$response->getBody();
            if (strlen($body) > self::MAX_BODY_BYTES) {
                return '';
            }
            return $body;
        } catch (\Throwable) {
            return '';
        }
    }

    /**
     * Download a remote binary resource to a local path. Only https:// URLs are allowed.
     */
    public function downloadToFile(string $url, string $targetPath, int $timeout = self::DEFAULT_TIMEOUT): bool
    {
        if (!RemoteUrlGuard::isAllowedHttpsUrl($url)) {
            return false;
        }

        try {
            $response = $this->requestFactory->request($url, 'GET', [
                'timeout' => $timeout,
                'allow_redirects' => true,
            ]);
            if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                return false;
            }
            $body = (string)$response->getBody();
            if ($body === '' || strlen($body) > self::MAX_BODY_BYTES) {
                return false;
            }
            return (bool)file_put_contents($targetPath, $body);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Materialize an <img src> value (https:// or data:image/…;base64,…) to a local file.
     * Google Docs HTML export often embeds images as data URIs, not remote URLs.
     *
     * @return array{path: string, mime: string}|null
     */
    public function materializeImageSource(string $src, string $targetPathWithoutExtension, int $timeout = self::DEFAULT_TIMEOUT): ?array
    {
        $src = html_entity_decode(trim($src), ENT_QUOTES, 'UTF-8');
        if ($src === '') {
            return null;
        }

        $binary = null;
        $mime = '';

        if (preg_match('#^data:image/([a-z0-9+.-]+);base64,(.+)$#is', $src, $matches)) {
            $subtype = strtolower($matches[1]);
            $binary = base64_decode($matches[2], true);
            if ($binary === false || $binary === '') {
                return null;
            }
            $mime = 'image/' . $subtype;
        } elseif (RemoteUrlGuard::isAllowedHttpsUrl($src)) {
            try {
                $response = $this->requestFactory->request($src, 'GET', [
                    'timeout' => $timeout,
                    'allow_redirects' => true,
                ]);
                if ($response->getStatusCode() < 200 || $response->getStatusCode() >= 300) {
                    return null;
                }
                $binary = (string)$response->getBody();
                $mime = (string)($response->getHeaderLine('Content-Type') ?: '');
            } catch (\Throwable) {
                return null;
            }
        } else {
            return null;
        }

        if ($binary === null || $binary === '' || strlen($binary) > self::MAX_BODY_BYTES) {
            return null;
        }

        if ($mime === '' || !str_starts_with(strtolower($mime), 'image/')) {
            $finfo = new \finfo(FILEINFO_MIME_TYPE);
            $detected = $finfo->buffer($binary);
            $mime = is_string($detected) ? $detected : 'image/jpeg';
        }
        // Strip charset parameters: "image/jpeg; charset=binary"
        $mime = strtolower(trim(explode(';', $mime, 2)[0]));

        $extension = match ($mime) {
            'image/png', 'image/x-png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'image/bmp', 'image/x-ms-bmp' => 'bmp',
            default => 'jpg',
        };
        $targetPath = $targetPathWithoutExtension . '.' . $extension;
        if (file_put_contents($targetPath, $binary) === false) {
            return null;
        }

        return ['path' => $targetPath, 'mime' => $mime];
    }

    public function isAllowedHttpsUrl(string $url): bool
    {
        return RemoteUrlGuard::isAllowedHttpsUrl($url);
    }

    public function isAllowedHttpUrl(string $url): bool
    {
        return RemoteUrlGuard::isAllowedHttpUrl($url);
    }
}
