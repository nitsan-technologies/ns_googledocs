<?php

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
/**
 * UserInfo
 */
class UserInfo extends \TYPO3\CMS\Extbase\DomainObject\AbstractEntity
{
    /**
     * clientId
     *
     * @var string
     */
    protected $clientId = '';

    /**
     * clientSecret
     *
     * @var string
     */
    protected $clientSecret = '';

    /**
     * refreshToken
     *
     * @var string
     */
    protected $refreshToken = '';

    /**
     * importType
     *
     * @var string
     */
    protected $importType = 0;

    /**
     * googleFilesRequest
     *
     * @var int
     */
    protected $googleFilesRequest = 10;

    /**
     * Returns the clientId
     *
     * @return string $clientId
     */
    public function getClientId()
    {
        return $this->clientId;
    }

    /**
     * Sets the clientId
     *
     * @param string $clientId
     * @return void
     */
    public function setClientId($clientId)
    {
        $this->clientId = $clientId;
    }

    /**
     * Returns the clientSecret
     *
     * @return string $clientSecret
     */
    public function getClientSecret()
    {
        return $this->clientSecret;
    }

    /**
     * Sets the clientSecret
     *
     * @param string $clientSecret
     * @return void
     */
    public function setClientSecret($clientSecret)
    {
        $this->clientSecret = $clientSecret;
    }

    /**
     * Returns the refreshToken
     *
     * @return string $refreshToken
     */
    public function getRefreshToken()
    {
        return $this->refreshToken;
    }

    /**
     * Sets the refreshToken
     *
     * @param string $refreshToken
     * @return void
     */
    public function setRefreshToken($refreshToken)
    {
        $this->refreshToken = $refreshToken;
    }

    /**
     * Returns the importType
     *
     * @return string importType
     */
    public function getImportType()
    {
        return $this->importType;
    }

    /**
     * Sets the importType
     *
     * @param int $importType
     * @return void
     */
    public function setImportType($importType)
    {
        $this->importType = $importType;
    }

    /**
     * Returns the googleFilesRequest
     *
     * @return int $googleFilesRequest
     */
    public function getGoogleFilesRequest()
    {
        return $this->googleFilesRequest;
    }

    /**
     * Sets the googleFilesRequest
     *
     * @param int $googleFilesRequest
     * @return void
     */
    public function setGoogleFilesRequest($googleFilesRequest)
    {
        $this->googleFilesRequest = $googleFilesRequest;
    }
}
