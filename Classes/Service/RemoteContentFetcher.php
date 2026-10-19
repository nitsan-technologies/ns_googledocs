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

    public function isAllowedHttpsUrl(string $url): bool
    {
        return RemoteUrlGuard::isAllowedHttpsUrl($url);
    }

    public function isAllowedHttpUrl(string $url): bool
    {
        return RemoteUrlGuard::isAllowedHttpUrl($url);
    }
}
