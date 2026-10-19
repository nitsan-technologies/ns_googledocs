<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Domain\Model;

/***
 *
 * This file is part of the "[NITSAN] Googledocs" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020
 *
 ***/

use TYPO3\CMS\Extbase\DomainObject\AbstractEntity;

/**
 * UserInfo
 */
class UserInfo extends AbstractEntity
{
    protected string $clientId = '';

    protected string $clientSecret = '';

    protected string $refreshToken = '';

    protected string $importType = '';

    protected int $googleFilesRequest = 10;

    public function getClientId(): string
    {
        return $this->clientId;
    }

    public function setClientId(string $clientId): void
    {
        $this->clientId = $clientId;
    }

    public function getClientSecret(): string
    {
        return $this->clientSecret;
    }

    public function setClientSecret(string $clientSecret): void
    {
        $this->clientSecret = $clientSecret;
    }

    public function getRefreshToken(): string
    {
        return $this->refreshToken;
    }

    public function setRefreshToken(string $refreshToken): void
    {
        $this->refreshToken = $refreshToken;
    }

    public function getImportType(): string
    {
        return $this->importType;
    }

    public function setImportType(string $importType): void
    {
        $this->importType = $importType;
    }

    public function getGoogleFilesRequest(): int
    {
        return $this->googleFilesRequest;
    }

    public function setGoogleFilesRequest(int $googleFilesRequest): void
    {
        $this->googleFilesRequest = $googleFilesRequest;
    }
}
