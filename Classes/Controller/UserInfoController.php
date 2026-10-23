<?php

declare(strict_types=1);

namespace NITSAN\NsGoogledocs\Controller;

/***
 *
 * This file is part of the "Googledocs" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020
 *
 ***/

use NITSAN\NsGoogledocs\Backend\PremiumStrip;
use NITSAN\NsGoogledocs\Domain\Repository\UserInfoRepository;
use NITSAN\NsGoogledocs\Service\GoogleDocsSectionParser;
use NITSAN\NsGoogledocs\Service\GoogleTokenHelper;
use NITSAN\NsGoogledocs\Service\ImportContentElementTypeResolver;
use NITSAN\NsGoogledocs\Service\ImportPageTypeGuard;
use NITSAN\NsGoogledocs\Service\ImportRequestMapper;
use NITSAN\NsGoogledocs\Service\ImportSectionSplitter;
use NITSAN\NsGoogledocs\Service\RemoteContentFetcher;
use NITSAN\NsGoogledocs\Service\RemoteHtmlSanitizer;
use NITSAN\NsGoogledocs\Service\RemoteSnippetValidator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility as BackendUtilityCore;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\Exception\ExistingTargetFolderException;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\ResourceStorage;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility;

/**
 * UserInfoController
 */
class UserInfoController extends ActionController
{
    protected ?UserInfoRepository $userInfoRepository = null;
    protected ?array $userData = null;
    protected mixed $client = null;
    protected mixed $service = null;
    protected array $globalSettings = [];
    protected ?array $apiContent = null;
    protected int $pageUid = 0;
    protected mixed $pageInformation = null;
    protected Typo3Version $currentVersion;
    protected ModuleTemplateFactory $moduleTemplateFactory;
    protected ResourceFactory $resourceFactory;
    protected StorageRepository $storageRepository;
    protected PremiumStrip $premiumStrip;
    protected PageRenderer $pageRenderer;
    protected RemoteHtmlSanitizer $remoteHtmlSanitizer;
    protected RemoteContentFetcher $remoteContentFetcher;
    protected GoogleDocsSectionParser $sectionParser;
    protected ImportSectionSplitter $sectionSplitter;
    protected bool $apiContentLoaded = false;
    protected string $refreshToken = '';
    protected string $tokenError = '';

    private const PREVIEW_SESSION_KEY = 'ns_googledocs_import_preview';

    /**
     * @var list<string>|null
     */
    private ?array $removedContentTypes = null;

    public function __construct(
        UserInfoRepository $userInfoRepository,
        ModuleTemplateFactory $moduleTemplateFactory,
        ResourceFactory $resourceFactory,
        StorageRepository $storageRepository,
        PremiumStrip $premiumStrip,
        PageRenderer $pageRenderer,
        RemoteHtmlSanitizer $remoteHtmlSanitizer,
        RemoteContentFetcher $remoteContentFetcher,
        GoogleDocsSectionParser $sectionParser,
        ImportSectionSplitter $sectionSplitter
    ) {
        $this->userInfoRepository = $userInfoRepository;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->resourceFactory = $resourceFactory;
        $this->storageRepository = $storageRepository;
        $this->premiumStrip = $premiumStrip;
        $this->pageRenderer = $pageRenderer;
        $this->remoteHtmlSanitizer = $remoteHtmlSanitizer;
        $this->remoteContentFetcher = $remoteContentFetcher;
        $this->sectionParser = $sectionParser;
        $this->sectionSplitter = $sectionSplitter;
    }

    /**
     * Prefer project Composer autoload (google/apiclient). Fall back to the
     * extension-local vendor/ for Classic installs.
     */
    protected function ensureGoogleClientAutoload(): void
    {
        if (class_exists(\Google\Client::class)) {
            return;
        }
        $extensionAutoload = ExtensionManagementUtility::extPath('ns_googledocs') . 'vendor/autoload.php';
        if (is_file($extensionAutoload)) {
            require_once $extensionAutoload;
        }
    }

    /**
     * Initialize Action
     */
    public function initializeAction(): void
    {
        $this->ensureGoogleClientAutoload();
        $this->currentVersion = GeneralUtility::makeInstance(Typo3Version::class);
        $this->globalSettings['siteRoot'] = Environment::getPublicPath() . '/';
        $this->userData = $this->getBackendUser()->user ?? [];
        $this->apiContent = [
            'dashboardSupportData' => '',
            'generalFooterData' => '',
        ];

        $clientId = $this->resolveUserCredential('client_id');
        $clientSecret = $this->resolveUserCredential('client_secret');
        $refreshToken = $this->resolveUserCredential('refresh_token');
        $importTypeRaw = $this->resolveUserCredential('import_type');

        if (($this->userData['uid'] ?? 0) > 0 && $clientId !== '') {
            try {
                $this->client = new \Google\Client();
                $this->client->setClientId($clientId);
                $this->client->setClientSecret($clientSecret);
                $this->client->setAccessType('offline');
                $this->refreshToken = $refreshToken;
                $this->refreshAccessToken();
                $this->service = new \Google\Service\Drive($this->client);
                $this->globalSettings['clientException'] = false;
            } catch (\Exception $e) {
                $this->tokenError = GoogleTokenHelper::explain($e->getMessage());
                $this->globalSettings['clientException'] = LocalizationUtility::translate('service.exception', 'ns_googledocs');
            }
            $this->globalSettings['clientStatus'] = true;
            $this->globalSettings['clientInfo']['client_id'] = $clientId;
            $this->globalSettings['clientInfo']['client_secret'] = $clientSecret;
            $this->globalSettings['clientInfo']['refresh_token'] = $refreshToken;
        } else {
            $this->globalSettings['clientStatus'] = false;
            $this->globalSettings['clientInfo']['client_id'] = '';
            $this->globalSettings['clientInfo']['client_secret'] = '';
            $this->globalSettings['clientInfo']['refresh_token'] = '';
        }

        $this->globalSettings['clientInfo']['uid'] = $this->userData['uid'] ?? 0;
        if ($importTypeRaw !== '') {
            $importType = explode(',', $importTypeRaw);
            $importTypeInfo = [];
            foreach ($importType as $type) {
                $importTypeInfo[$type] = LocalizationUtility::translate('import_type.' . $type, 'ns_googledocs');
            }
            $this->globalSettings['clientInfo']['import_type'] = $importTypeInfo;
        }
        $this->globalSettings['clientInfo']['name'] = ($this->userData['realName'] ?? '') !== ''
            ? $this->userData['realName']
            : ($this->userData['username'] ?? '');

        $mapped = $this->getPluginRequestArguments();
        $this->pageUid = (int)$mapped['pageId'];
        $this->pageInformation = BackendUtilityCore::readPageAccess(
            $this->pageUid,
            $this->getBackendUser()->getPagePermsClause(1)
        );
        parent::initializeAction();
    }

    /**
     * Apply the stored credential: a ya29.* value is used as an access token directly;
     * otherwise it is treated as a refresh token and exchanged via Google\Client::refreshToken().
     * Google\Client::refreshToken() does not throw on OAuth errors but returns
     * ['error' => 'invalid_grant', ...], so the result is inspected.
     */
    protected function refreshAccessToken(): bool
    {
        if ($this->client === null) {
            return false;
        }
        $this->tokenError = '';
        if ($this->refreshToken === '') {
            $this->tokenError = $this->translateLabel(
                'error.noRefreshToken',
                'No refresh token is configured. Add it under Global Settings.'
            );
            return false;
        }
        $formatError = GoogleTokenHelper::validateRefreshToken($this->refreshToken);
        if ($formatError !== null) {
            $this->tokenError = $formatError;
            return false;
        }

        // Access tokens can be pasted into the same field and used until they expire.
        if (GoogleTokenHelper::isAccessToken($this->refreshToken)) {
            try {
                $this->client->setAccessToken([
                    'access_token' => $this->refreshToken,
                    'expires_in' => 3600,
                    'created' => time(),
                ]);
            } catch (\Throwable $e) {
                $this->tokenError = GoogleTokenHelper::explain($e->getMessage());
                return false;
            }
            return true;
        }

        try {
            $token = $this->client->refreshToken($this->refreshToken);
        } catch (\Throwable $e) {
            $this->tokenError = GoogleTokenHelper::explain($e->getMessage());
            return false;
        }
        if (!is_array($token) || isset($token['error']) || empty($token['access_token'])) {
            $raw = is_array($token)
                ? trim((string)($token['error'] ?? '') . ' ' . (string)($token['error_description'] ?? ''))
                : '';
            $this->tokenError = GoogleTokenHelper::explain($raw !== '' ? $raw : 'invalid_grant');
            return false;
        }
        return true;
    }

    /**
     * Page type (doktype) of the current page allows imports.
     */
    protected function isImportAllowedOnCurrentPage(): bool
    {
        if (!is_array($this->pageInformation) || !isset($this->pageInformation['uid'])) {
            return false;
        }
        try {
            $configured = GeneralUtility::makeInstance(ExtensionConfiguration::class)
                ->get('ns_googledocs', 'allowedImportDoktypes');
        } catch (\Throwable) {
            $configured = '';
        }
        return ImportPageTypeGuard::isAllowed(
            (int)($this->pageInformation['doktype'] ?? 0),
            ImportPageTypeGuard::parseAllowlist($configured)
        );
    }

    protected function getPageTypeDeniedMessage(): string
    {
        return $this->translateLabel(
            'import_access_error_msg',
            'You do not have access to import Google docs on this page type, Go to Global Settings'
        );
    }

    /**
     * @param list<int|string>|array<int, int|string> $arguments
     */
    protected function translateLabel(string $key, string $fallback, array $arguments = []): string
    {
        try {
            $label = LocalizationUtility::translate($key, 'ns_googledocs', $arguments !== [] ? $arguments : null);
        } catch (\Throwable) {
            $label = null;
        }
        if (!is_string($label) || $label === '' || $label === $key) {
            return $arguments !== [] ? sprintf($fallback, ...$arguments) : $fallback;
        }
        return $label;
    }

    /**
     * Load and sanitize remote marketing HTML only for UI actions that need it.
     */
    protected function ensureApiContentLoaded(): void
    {
        if ($this->apiContentLoaded) {
            return;
        }
        $this->apiContentLoaded = true;

        $this->userInfoRepository->deleteOldApiData();
        $checkApiData = $this->userInfoRepository->checkApiData();
        if (!$checkApiData) {
            // composer.t3terminal.com answers with "301 Moved Permanently" to this host.
            $apiBase = 'https://composer.t3planet.cloud/API/ExtBackendModuleAPI.php?extKey=ns_googledocs&blockName=';

            $this->apiContent['dashboardSupportData'] = RemoteSnippetValidator::filter((string)$this->userInfoRepository->curlInitCall($apiBase . 'DashboardSupport'));
            $this->apiContent['generalFooterData'] = RemoteSnippetValidator::filter((string)$this->userInfoRepository->curlInitCall($apiBase . 'GeneralFooter'));

            $data = [
                'support_html' => $this->apiContent['dashboardSupportData'],
                'footer_html' => $this->apiContent['generalFooterData'],
                'extension_key' => 'ns_googledocs',
                'last_update' => date('Y-m-d'),
            ];
            if ($this->userInfoRepository->apiRowHasContent($data)) {
                $this->userInfoRepository->insertNewData($data);
            }
        } else {
            $this->apiContent['dashboardSupportData'] = (string)($checkApiData['support_html'] ?? '');
            $this->apiContent['generalFooterData'] = (string)($checkApiData['footer_html'] ?? '');
        }

        foreach (['dashboardSupportData', 'generalFooterData'] as $key) {
            // Cached rows may still hold error pages (e.g. "301 Moved Permanently") from older versions.
            $value = RemoteSnippetValidator::filter((string)($this->apiContent[$key] ?? ''));
            $this->apiContent[$key] = $value === '' ? '' : $this->remoteHtmlSanitizer->sanitize($value);
        }
    }

    /**
     * Resolve a credential field, preferring prefixed columns with legacy fallback.
     */
    protected function resolveUserCredential(string $field): string
    {
        $prefixed = 'tx_nsgoogledocs_' . $field;
        $value = (string)($this->userData[$prefixed] ?? '');
        if ($value !== '') {
            return $value;
        }
        return (string)($this->userData[$field] ?? '');
    }

    /**
     * First module visit without Google credentials should open Global Settings.
     * Must be returned from the action (initializeAction cannot redirect since v12).
     */
    protected function redirectToGlobalSettingsOnFirstVisit(): ?ResponseInterface
    {
        if (!empty($this->globalSettings['clientStatus'])) {
            return null;
        }
        $sessionKey = 'ns_googledocs';
        $sessionData = $this->getBackendUser()->getSessionData($sessionKey);
        if (is_array($sessionData) && !empty($sessionData['firstTimeLoad'])) {
            return null;
        }
        $this->getBackendUser()->setAndSaveSessionData($sessionKey, ['firstTimeLoad' => true]);
        return $this->redirect('globalSettings');
    }

    public function dashboardAction(): ResponseInterface
    {
        $firstVisitRedirect = $this->redirectToGlobalSettingsOnFirstVisit();
        if ($firstVisitRedirect !== null) {
            return $firstVisitRedirect;
        }
        $this->ensureApiContentLoaded();
        $assign = [
            'globalSettings' => $this->globalSettings,
            'activePage' => $this->actionMethodName,
            'pageInformation' => $this->pageInformation,
            'sidebarData' => $this->apiContent['dashboardSupportData'] ?? '',
            'bootstrapVariable' => 'data-bs',
            'version' => $this->currentVersion->getMajorVersion(),
        ];
        $moduleTemplate = $this->initializeModuleTemplate($this->request);
        $moduleTemplate->assignMultiple($assign);
        return $moduleTemplate->renderResponse('UserInfo/Dashboard');
    }

    public function importAction(): ResponseInterface
    {
        $firstVisitRedirect = $this->redirectToGlobalSettingsOnFirstVisit();
        if ($firstVisitRedirect !== null) {
            return $firstVisitRedirect;
        }
        $this->ensureApiContentLoaded();
        if (!$this->globalSettings['clientStatus']) {
            return $this->redirect('dashboard');
        }

        $docsFields = [];
        $pageColPos = [];
        $msgException = false;

        if (!is_array($this->pageInformation) || !isset($this->pageInformation['uid'])) {
            $assign = [
                'activePage' => $this->actionMethodName,
                'pageStatus' => false,
                'version' => $this->currentVersion->getMajorVersion(),
                'defaultElementType' => $this->normalizeContentElementType($this->getDefaultContentElementType()),
                'elementTypeOptionsJson' => '{}',
                'previewSectionsUri' => '',
                'pageColPos' => [],
            ];
        } else {
            $this->globalSettings['pageTitle'] = $this->pageInformation['title'] ?? '';
            $this->globalSettings['importStatus'] = false;
            // Only standard pages (configurable allowlist) — no sysfolder, spacer, shortcut, link, mount point, recycler …
            if ($this->isImportAllowedOnCurrentPage()) {
                $pageColPos = $this->getBackendLayoutColumns((int)$this->pageUid);
                $this->globalSettings['docsImportType'] = LocalizationUtility::translate('reports_import_type.1', 'ns_googledocs');
                $this->globalSettings['docsImportTypeID'] = 1;
                if (isset($this->globalSettings['clientInfo']['import_type'][1])) {
                    $this->globalSettings['importStatus'] = true;
                }
            }

            if ($this->globalSettings['importStatus'] && $this->service !== null) {
                $optParams = [
                    'pageSize' => 10,
                    'fields' => 'files(id, name, createdTime, modifiedTime, webViewLink, owners, mimeType, exportLinks)',
                    'q' => "'me' in owners and mimeType='application/vnd.google-apps.document'",
                ];
                try {
                    $results = $this->service->files->listFiles($optParams);
                    if (count($results->getFiles()) > 0) {
                        foreach ($results->getFiles() as $key => $file) {
                            if ($file->getMimeType() !== 'application/vnd.google-apps.document') {
                                continue;
                            }
                            $docsFields[$key]['id'] = $file->getId();
                            $docsFields[$key]['name'] = $file->getName();
                            $docsFields[$key]['lastModified'] = $file->getModifiedTime();
                            $owners = $file->getOwners();
                            $docsFields[$key]['owner'] = (!empty($owners) && isset($owners[0]))
                                ? $owners[0]->getDisplayName()
                                : '';
                            $exportLinks = $file->getExportLinks();
                            $docsFields[$key]['importURL'] = is_array($exportLinks)
                                ? ($exportLinks['text/html'] ?? '')
                                : '';
                            $docsFields[$key]['ViewURL'] = $file->getWebViewLink();
                        }
                    }
                    $msgException = false;
                } catch (\Throwable $e) {
                    $msgException = LocalizationUtility::translate('listFiles.exception', 'ns_googledocs');
                    if ($this->tokenError !== '') {
                        $msgException .= ' ' . $this->tokenError;
                    } elseif (GoogleTokenHelper::isInvalidGrant($e->getMessage()) || $e->getCode() === 401) {
                        $msgException .= ' ' . GoogleTokenHelper::explain($e->getMessage() . ($e->getCode() === 401 ? ' "code": 401' : ''));
                    }
                }
            }

            $assign = [
                'pageColPos' => $pageColPos,
                'docsFiles' => $docsFields,
                'activePage' => $this->actionMethodName,
                'globalSettings' => $this->globalSettings,
                'pageStatus' => true,
                'msgException' => $msgException,
                'bootstrapVariable' => 'data-bs',
                'pageId' => (int)($this->pageInformation['uid'] ?? $this->pageUid),
                'version' => $this->currentVersion->getMajorVersion(),
                'defaultElementType' => $this->normalizeContentElementType($this->getDefaultContentElementType()),
                'elementTypeOptionsJson' => json_encode(
                    $this->getElementTypeOptions(),
                    JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE
                ),
                'previewSectionsUri' => $this->uriBuilder
                    ->reset()
                    ->setCreateAbsoluteUri(true)
                    ->uriFor('previewSections', [], 'UserInfo'),
            ];
        }

        $moduleTemplate = $this->initializeModuleTemplate($this->request);
        $moduleTemplate->assignMultiple($assign);
        return $moduleTemplate->renderResponse('UserInfo/Import');
    }

    /**
     * Export the Doc, split by Heading 1/2, cache sections in BE session for the import step.
     */
    public function previewSectionsAction(): ResponseInterface
    {
        $request = $this->getPluginRequestArguments();
        $googleDocsId = (string)$request['googleDocsID'];
        if ($this->service === null || $googleDocsId === '') {
            return new JsonResponse([
                'status' => false,
                'error' => $this->tokenError !== '' ? $this->tokenError : $this->translateLabel(
                    'error.missingDocIdOrClient',
                    'Missing Google Docs id or client.'
                ),
            ]);
        }
        $pageDenied = $this->denyImportForPageType($request);
        if ($pageDenied !== null) {
            return $pageDenied;
        }

        try {
            $sections = $this->fetchAndParseSectionsWithRetry($googleDocsId);
            $defaultType = $this->normalizeContentElementType($this->getDefaultContentElementType());
            $payload = [];
            $untitledLabel = (string)(LocalizationUtility::translate('import_section_untitled', 'ns_googledocs') ?: 'Untitled section');
            foreach ($sections as $section) {
                $fallbackTitle = trim((string)($section['fallbackTitle'] ?? ''));
                $payload[] = [
                    'index' => $section['index'],
                    'title' => $section['title'],
                    // Shown as placeholder for sections without a real heading (lead-in / image-only),
                    // so the dialog never renders a blank row; the CE header stays empty unless edited.
                    'fallbackTitle' => $fallbackTitle !== '' ? $fallbackTitle : $untitledLabel,
                    'headingLevel' => $section['headingLevel'],
                    'hasImages' => $section['hasImages'],
                    'defaultType' => $defaultType,
                ];
            }

            return new JsonResponse([
                'status' => true,
                'sections' => $payload,
                'defaultElementType' => $defaultType,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'status' => false,
                'error' => $this->translateLabel(
                    'error.readSections',
                    'Could not read document sections: %s',
                    [$this->describeGoogleError($e)]
                ),
            ]);
        }
    }

    /**
     * Server-side page type check for the AJAX endpoints (preview + import).
     *
     * @param array<string, mixed> $request
     */
    protected function denyImportForPageType(array $request): ?JsonResponse
    {
        $pageID = $this->pageUid;
        if ($pageID <= 0) {
            $queryParams = $this->request->getQueryParams();
            $pageID = (int)($request['pageId'] ?? $request['id'] ?? $queryParams['id'] ?? 0);
        }
        if ($pageID <= 0) {
            // Preview without page context: import step enforces the page requirement.
            return null;
        }
        if ((int)($this->pageInformation['uid'] ?? 0) !== $pageID) {
            $this->pageUid = $pageID;
            $this->pageInformation = BackendUtilityCore::readPageAccess(
                $pageID,
                $this->getBackendUser()->getPagePermsClause(1)
            );
        }
        if (!is_array($this->pageInformation) || !isset($this->pageInformation['uid'])) {
            return new JsonResponse(['status' => false, 'error' => $this->translateLabel(
                'error.noPageAccess',
                'You do not have access to this page.'
            )]);
        }
        if (!$this->isImportAllowedOnCurrentPage()) {
            return new JsonResponse(['status' => false, 'error' => $this->getPageTypeDeniedMessage()]);
        }
        return null;
    }

    /**
     * Read and parse the Doc; on a failure (expired/missing access token, transient Google or
     * network error on the first request) refresh the access token once and retry.
     *
     * @return list<array{index: int, title: string, fallbackTitle?: string, headingLevel: int, html: string, hasImages: bool}>
     */
    protected function fetchAndParseSectionsWithRetry(string $googleDocsId): array
    {
        try {
            return $this->fetchAndParseSections($googleDocsId);
        } catch (\Throwable $first) {
            if (GoogleTokenHelper::isInvalidGrant($first->getMessage())) {
                throw $first;
            }
            if (!$this->refreshAccessToken()) {
                throw new \RuntimeException($this->tokenError, 1760854510, $first);
            }
            $this->service = new \Google\Service\Drive($this->client);
            return $this->fetchAndParseSections($googleDocsId);
        }
    }

    protected function describeGoogleError(\Throwable $e): string
    {
        if ($this->tokenError !== '') {
            return $this->tokenError;
        }
        $message = trim($e->getMessage());
        if ($e instanceof \Google\Service\Exception) {
            $errors = $e->getErrors();
            if (is_array($errors) && isset($errors[0]['message'])) {
                $message = (string)$errors[0]['message'];
            }
            $message = $this->translateLabel(
                'error.googleApi',
                'Google API error %d: %s',
                [(int)$e->getCode(), $message]
            );
        }
        if ($message === '') {
            $message = get_class($e);
        }
        return GoogleTokenHelper::explain($message);
    }

    public function docsImportAction(): ResponseInterface
    {
        $request = $this->getPluginRequestArguments();
        $queryParams = $this->request->getQueryParams();
        $pageID = $this->pageUid;
        if ($pageID <= 0) {
            $pageID = (int)($request['pageId'] ?? $request['id'] ?? $queryParams['id'] ?? 0);
            $this->pageUid = $pageID;
        }
        if ($pageID > 0 && (!is_array($this->pageInformation) || !isset($this->pageInformation['uid']))) {
            $this->pageInformation = BackendUtilityCore::readPageAccess(
                $pageID,
                $this->getBackendUser()->getPagePermsClause(1)
            );
        }

        $googleDocsFolderName = $this->sanitizeFolderName((string)($request['googleDocsName'] ?? ''), $pageID);
        $docFolder = 'ns_googledocs/pages/' . $googleDocsFolderName . '/';
        $this->globalSettings['googleDocsFolderName'] = $docFolder;

        $arr = ['status' => false, 'imported' => 0];
        $googleDocsId = (string)$request['googleDocsID'];

        if ($this->service === null) {
            return new JsonResponse([
                'status' => false,
                'error' => $this->tokenError !== '' ? $this->tokenError : $this->translateLabel(
                    'error.clientNotConfigured',
                    'Google client is not configured for this backend user.'
                ),
            ]);
        }
        if ($googleDocsId === '') {
            return new JsonResponse(['status' => false, 'error' => $this->translateLabel(
                'error.missingDocId',
                'Missing Google Docs file id.'
            )]);
        }
        if ($pageID <= 0) {
            return new JsonResponse(['status' => false, 'error' => $this->translateLabel(
                'error.missingPageId',
                'Missing page id. Select a page in the page tree and try again.'
            )]);
        }
        $pageDenied = $this->denyImportForPageType($request);
        if ($pageDenied !== null) {
            return $pageDenied;
        }

        try {
            $cachedSections = $this->fetchAndParseSectionsWithRetry($googleDocsId);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'status' => false,
                'error' => $this->translateLabel(
                    'error.readSections',
                    'Could not read document sections: %s',
                    [$this->describeGoogleError($e)]
                ),
            ]);
        }

        if ($cachedSections === []) {
            return new JsonResponse([
                'status' => false,
                'error' => $this->translateLabel(
                    'error.noImportableSections',
                    'No importable sections found in this Google Doc. Add Heading 1 / Heading 2 sections and try again.'
                ),
            ]);
        }

        $sectionChoices = $request['sections'] ?? [];
        if (!is_array($sectionChoices)) {
            $sectionChoices = [];
        }

        try {
            // DataHandler ignores "sorting" on new records (positive pid = top of page).
            // Chain inserts after the last CE of the target column to keep document order.
            $colPos = (int)($request['colPos'] ?? 0);
            $insertAfterUid = $this->userInfoRepository->findLastContentElementUidInColumn($pageID, $colPos);
            $imported = 0;
            foreach ($cachedSections as $section) {
                $index = (int)$section['index'];
                $choice = is_array($sectionChoices[$index] ?? null)
                    ? $sectionChoices[$index]
                    : (is_array($sectionChoices[(string)$index] ?? null) ? $sectionChoices[(string)$index] : []);
                $enabled = !isset($choice['enabled']) || (string)$choice['enabled'] === '1' || $choice['enabled'] === true;
                if (!$enabled) {
                    continue;
                }

                $cType = $this->normalizeContentElementType(
                    (string)($choice['CType'] ?? $this->getDefaultContentElementType())
                );
                $header = trim((string)($choice['header'] ?? $section['title'] ?? ''));
                $elements = $this->buildElementsFromSection($section, $cType, $header);
                foreach ($elements as $element) {
                    $result = $this->userInfoRepository->insertPageElement($pageID, $element, $request, $insertAfterUid);
                    if (!$result) {
                        $this->deleteImportedFiles(is_array($element['images'] ?? null) ? $element['images'] : []);
                        $errors = $this->userInfoRepository->getLastDataHandlerErrors();
                        return new JsonResponse([
                            'status' => false,
                            'imported' => $imported,
                            'error' => $errors !== []
                                ? implode('; ', $errors)
                                : $this->translateLabel(
                                    'error.importFailed',
                                    'The document was not imported successfully.'
                                ),
                        ]);
                    }
                    $imported++;
                    $newUid = $this->userInfoRepository->getLastInsertedUid();
                    $insertAfterUid = $newUid > 0
                        ? $newUid
                        : $this->userInfoRepository->findLastContentElementUidInColumn($pageID, $colPos);
                }
            }

            if ($imported === 0) {
                return new JsonResponse([
                    'status' => false,
                    'error' => $this->translateLabel(
                        'error.noSectionsSelected',
                        'No sections were selected for import.'
                    ),
                ]);
            }

            $arr['status'] = true;
            $arr['imported'] = $imported;
        } catch (\Throwable $e) {
            $arr['status'] = false;
            $arr['error'] = GoogleTokenHelper::explain($e->getMessage());
        }

        return new JsonResponse($arr);
    }

    /**
     * @return list<array{index: int, title: string, fallbackTitle?: string, headingLevel: int, html: string, hasImages: bool}>
     */
    protected function fetchAndParseSections(string $googleDocsId): array
    {
        // Drop any leftover preview blob from older versions (full HTML in be_sessions).
        $this->clearImportPreviewSession();
        $rawHtml = $this->readGoogleDocsExportHtml($googleDocsId);
        if (trim($rawHtml) === '') {
            throw new \RuntimeException('Google Docs export returned empty HTML for this file.', 1760854500);
        }
        $html = $this->normalizeGoogleDocsHtml($rawHtml);
        $stringbody = GoogleDocsSectionParser::stripXmlArtifacts(html_entity_decode($html, ENT_QUOTES, 'UTF-8'));
        $stringbody = preg_replace('/<a href="(.*?)q=(.*?)&sa(.*?)">/si', '<a href="$2">', $stringbody) ?? $stringbody;
        if (trim($stringbody) === '') {
            throw new \RuntimeException('Google Docs HTML was empty after normalization.', 1760854501);
        }
        $sections = $this->sectionParser->parse($stringbody);
        if ($sections === []) {
            $sections = [[
                'index' => 0,
                'title' => '',
                'fallbackTitle' => '',
                'headingLevel' => 1,
                'html' => $stringbody,
                'hasImages' => (bool)preg_match('/<img\b/i', $stringbody),
            ]];
        }
        return $sections;
    }

    /**
     * Older versions stored the full parsed Doc (including base64 images) in be_sessions.
     * Clear it whenever we re-read a Doc so leftover multi-MB session rows shrink.
     */
    protected function clearImportPreviewSession(): void
    {
        try {
            $backendUser = $GLOBALS['BE_USER'] ?? null;
            if ($backendUser instanceof BackendUserAuthentication) {
                $backendUser->setAndSaveSessionData(self::PREVIEW_SESSION_KEY, null);
            }
        } catch (\Throwable) {
        }
    }

    protected function readGoogleDocsExportHtml(string $googleDocsId): string
    {
        $response = $this->service->files->export($googleDocsId, 'text/html');
        if (is_string($response)) {
            return $response;
        }
        if ($response instanceof \Psr\Http\Message\ResponseInterface) {
            $body = $response->getBody();
            if ($body->isSeekable()) {
                $body->rewind();
            }
            $html = $body->getContents();
            if ($html === '' && $body->isSeekable()) {
                $body->rewind();
                $html = (string)$body;
            }
            return $html;
        }
        if (is_object($response) && method_exists($response, 'getBody')) {
            $body = $response->getBody();
            if (is_object($body) && method_exists($body, 'getContents')) {
                if (method_exists($body, 'isSeekable') && $body->isSeekable() && method_exists($body, 'rewind')) {
                    $body->rewind();
                }
                return (string)$body->getContents();
            }
            return (string)$body;
        }
        return (string)$response;
    }

    /**
     * PHP 8.4+ throws ValueError on DOMDocument::loadHTML('') — never call it with empty markup.
     */
    protected function createDomDocumentFromHtml(string $html): ?\DOMDocument
    {
        $html = trim($html);
        if ($html === '') {
            return null;
        }
        if (!str_contains(strtolower($html), '<body')) {
            $html = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>' . $html . '</body></html>';
        }
        $dom = new \DOMDocument();
        try {
            $loaded = @$dom->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        } catch (\ValueError) {
            return null;
        }
        return $loaded === false ? null : $dom;
    }

    /**
     * Keep the editor's CType when the target page allows it. Otherwise fall back
     * (header → text, image → textpic) so DataHandler still succeeds.
     */
    protected function normalizeContentElementType(string $cType): string
    {
        return ImportContentElementTypeResolver::normalize(
            $cType,
            fn(string $type): bool => $this->isCTypeAvailable($type)
        );
    }

    protected function isCTypeAvailable(string $cType): bool
    {
        return ImportContentElementTypeResolver::isAllowed(
            $cType,
            $this->cTypeExistsInTca($cType),
            $this->getRemovedContentTypes()
        );
    }

    protected function cTypeExistsInTca(string $cType): bool
    {
        if (class_exists(\TYPO3\CMS\Core\Schema\TcaSchemaFactory::class)) {
            try {
                $factory = GeneralUtility::makeInstance(\TYPO3\CMS\Core\Schema\TcaSchemaFactory::class);
                if ($factory->has('tt_content') && $factory->get('tt_content')->hasSubSchema($cType)) {
                    return true;
                }
            } catch (\Throwable) {
            }
        }
        foreach ($GLOBALS['TCA']['tt_content']['columns']['CType']['config']['items'] ?? [] as $item) {
            if (!is_array($item)) {
                continue;
            }
            $value = $item['value'] ?? $item[1] ?? null;
            if ((string)$value === $cType) {
                return true;
            }
        }
        return false;
    }

    /**
     * @return list<string>
     */
    protected function getRemovedContentTypes(): array
    {
        if ($this->removedContentTypes !== null) {
            return $this->removedContentTypes;
        }
        $pageId = (int)$this->pageUid;
        if ($pageId <= 0) {
            $this->removedContentTypes = ['header', 'image'];
            return $this->removedContentTypes;
        }
        try {
            $removeItems = BackendUtilityCore::getPagesTSconfig($pageId)['TCEFORM.']['tt_content.']['CType.']['removeItems'] ?? '';
            $this->removedContentTypes = ImportContentElementTypeResolver::parseRemoveItems($removeItems);
        } catch (\Throwable) {
            $this->removedContentTypes = ['header', 'image'];
        }
        return $this->removedContentTypes;
    }

    protected function resolveImageElementType(string $selectedCType): string
    {
        if ($selectedCType === 'image') {
            return $this->normalizeContentElementType('image');
        }
        return $this->normalizeContentElementType('textpic');
    }

    /**
     * One enabled section can become several CEs so images are FAL-tracked while
     * surrounding text stays in Google Doc order. HTML without images still
     * yields a single CE (same as before).
     *
     * @param array{index: int, title: string, fallbackTitle?: string, headingLevel: int, html: string, hasImages: bool} $section
     * @return list<array<string, mixed>>
     */
    protected function buildElementsFromSection(array $section, string $cType, string $header): array
    {
        $html = (string)($section['html'] ?? '');
        $headingLevel = (int)($section['headingLevel'] ?? 1);
        $headerLayout = $headingLevel > 0 ? $headingLevel : 1;

        if ($cType === 'header') {
            return [[
                'CType' => $cType,
                'header' => $header,
                'header_layout' => $headerLayout,
                'imageorient' => 0,
            ]];
        }

        $chunks = $this->sectionSplitter->split($html);
        $elements = [];
        $isFirst = true;
        foreach ($chunks as $chunk) {
            $elementHeader = $isFirst ? $header : '';
            $chunkHtml = (string)($chunk['html'] ?? '');
            if (($chunk['type'] ?? 'text') === 'image') {
                $images = $this->findImage($chunkHtml);
                if ($images === []) {
                    continue;
                }
                $elements[] = [
                    'CType' => $this->resolveImageElementType($cType),
                    'header' => $elementHeader,
                    'header_layout' => $headerLayout,
                    'imageorient' => 0,
                    'images' => $images,
                    'bodytext' => '',
                ];
                $isFirst = false;
                continue;
            }

            $parsed = $this->updateImageInBodytext($chunkHtml, 0);
            $elements[] = [
                'CType' => $cType === 'image' ? $this->normalizeContentElementType('text') : $cType,
                'header' => $elementHeader,
                'header_layout' => $headerLayout,
                'imageorient' => 0,
                'bodytext' => trim((string)($parsed['bodytext'] ?? '')),
                'images' => [],
            ];
            $isFirst = false;
        }

        if ($elements === []) {
            $parsed = $this->updateImageInBodytext($html, 0);
            $elements[] = [
                'CType' => $cType === 'image' ? $this->normalizeContentElementType('text') : $cType,
                'header' => $header,
                'header_layout' => $headerLayout,
                'imageorient' => 0,
                'bodytext' => trim((string)($parsed['bodytext'] ?? '')),
                'images' => [],
            ];
        }

        return $elements;
    }

    /**
     * @param list<int|string> $fileUids
     */
    protected function deleteImportedFiles(array $fileUids): void
    {
        foreach ($fileUids as $uid) {
            $uid = (int)$uid;
            if ($uid <= 0) {
                continue;
            }
            try {
                $this->resourceFactory->getFileObject($uid)->delete();
            } catch (\Throwable) {
                // File may already be gone; never fail the import error response because of cleanup.
            }
        }
    }

    protected function getDefaultContentElementType(): string
    {
        try {
            $type = (string)GeneralUtility::makeInstance(ExtensionConfiguration::class)
                ->get('ns_googledocs', 'googleDocsContentElementType');
        } catch (\Throwable) {
            $type = 'textpic';
        }
        return in_array($type, ['header', 'text', 'textpic', 'image'], true) ? $type : 'textpic';
    }

    /**
     * @return array<string, string>
     */
    protected function getElementTypeOptions(): array
    {
        $labels = [
            'header' => (string)(LocalizationUtility::translate('element_type.header', 'ns_googledocs') ?: 'Header'),
            'text' => (string)(LocalizationUtility::translate('element_type.text', 'ns_googledocs') ?: 'Text Element'),
            'textpic' => (string)(LocalizationUtility::translate('element_type.textpic', 'ns_googledocs') ?: 'Text & Image'),
            'image' => (string)(LocalizationUtility::translate('element_type.image', 'ns_googledocs') ?: 'Image Only'),
        ];
        return ImportContentElementTypeResolver::filterOptions(
            $labels,
            fn(string $type): bool => $this->isCTypeAvailable($type)
        );
    }

    /**
     * @return list<int>
     */
    public function findImage(string $content): array
    {
        $imagesUids = [];
        if (trim($content) === '') {
            return $imagesUids;
        }
        $storage = $this->storageRepository->getDefaultStorage();
        if ($storage === null) {
            return $imagesUids;
        }
        $targetFolderName = (string)($this->globalSettings['googleDocsFolderName'] ?? 'ns_googledocs/');
        $storageFolder = $this->ensureStorageFolder($storage, $targetFolderName);
        if ($storageFolder === null) {
            return $imagesUids;
        }
        $doc = $this->createDomDocumentFromHtml($content);
        if ($doc === null) {
            return $imagesUids;
        }
        $xml = simplexml_import_dom($doc);
        if ($xml === false) {
            return $imagesUids;
        }
        $images = $xml->xpath('//img');
        $tempDir = Environment::getVarPath() . '/transient';
        GeneralUtility::mkdir_deep($tempDir);
        foreach ($images as $img) {
            $tempPath = '';
            try {
                $src = (string)($img['src'] ?? '');
                // Google Docs HTML export commonly embeds images as data:image/…;base64,…
                // (not https://). materializeImageSource handles both forms.
                $materialized = $this->remoteContentFetcher->materializeImageSource(
                    $src,
                    $tempDir . '/ns_googledocs_' . bin2hex(random_bytes(8))
                );
                if ($materialized === null || !is_file($materialized['path'])) {
                    continue;
                }
                $tempPath = $materialized['path'];
                $imageMimeType = $this->findImageMimeType($materialized['mime']);
                $exif = @getimagesize($tempPath);
                if (is_array($exif) && !empty($exif['mime'])) {
                    $imageMimeType = $this->findImageMimeType((string)$exif['mime']);
                }
                $newFile = $storage->addFile(
                    $tempPath,
                    $storageFolder,
                    'ns_googledocs_' . random_int(10, 1000000) . '.' . $imageMimeType
                );
                $imagesUids[] = $newFile->getUid();
            } catch (\Throwable) {
                // Skip this image; remaining images and the text import still proceed.
            } finally {
                if ($tempPath !== '' && is_file($tempPath)) {
                    @unlink($tempPath);
                }
            }
        }
        return $imagesUids;
    }

    /**
     * Resolve/create the import folder on the default FAL storage (not a hard-coded fileadmin path).
     */
    protected function ensureStorageFolder(ResourceStorage $storage, string $folderPath): ?Folder
    {
        $folderPath = trim(str_replace('\\', '/', $folderPath), '/');
        if ($folderPath === '') {
            return $storage->getRootLevelFolder();
        }
        $candidates = [$folderPath . '/', '/' . $folderPath . '/'];
        foreach ($candidates as $identifier) {
            try {
                if ($storage->hasFolder($identifier)) {
                    return $storage->getFolder($identifier);
                }
            } catch (\Throwable) {
            }
        }
        try {
            return $storage->createFolder($folderPath);
        } catch (ExistingTargetFolderException) {
            foreach ($candidates as $identifier) {
                try {
                    return $storage->getFolder($identifier);
                } catch (\Throwable) {
                }
            }
        } catch (\Throwable) {
        }
        return null;
    }

    /**
     * @return array{header?: string, header_layout?: int, bodytext: string}
     */
    public function updateImageInBodytext(string $content, int $findHeader): array
    {
        $content = preg_replace("/<p[^>]*>(?:\s|&nbsp;)*<\/p>/", '', $content) ?? $content;
        if (trim($content) === '') {
            return ['bodytext' => ''];
        }
        $images = $this->findImage($content);
        $dom = $this->createDomDocumentFromHtml($content);
        if ($dom === null) {
            return ['bodytext' => $content];
        }
        $xpath = new \DOMXPath($dom);
        $srcs = $xpath->evaluate('//img');
        if ($srcs instanceof \DOMNodeList) {
            for ($i = 0; $i < $srcs->length; $i++) {
                $src = $srcs->item($i);
                if ($src instanceof \DOMElement && isset($images[$i])) {
                    $file = $this->resourceFactory->getFileObject($images[$i]);
                    $publicUrl = (string)($file->getPublicUrl() ?? '');
                    if ($publicUrl !== '' && !str_starts_with($publicUrl, '/') && !str_contains($publicUrl, '://')) {
                        $publicUrl = '/' . $publicUrl;
                    }
                    $src->removeAttribute('src');
                    $src->setAttribute('src', $publicUrl);
                    // Google Docs often exports width="0" height="0" (real size only in style).
                    // Those attributes make the FE render invisible images — replace with file size.
                    $this->normalizeImportedImageDimensions($src, $file);
                }
            }
        }
        $bodies = $dom->getElementsByTagName('body');
        $body = $bodies->item(0);
        $string = ['bodytext' => ''];
        if ($body instanceof \DOMElement && $findHeader && $body->childNodes->length > 0) {
            $first = $body->childNodes->item(0);
            if ($first instanceof \DOMElement) {
                $tagName = $first->tagName;
                if (in_array($tagName, ['h1', 'h2', 'h3', 'h4', 'h5', 'h6'], true)) {
                    $string['header_layout'] = (int)substr($tagName, 1);
                    $string['header'] = $first->textContent;
                    $body->removeChild($first);
                }
            }
        }
        $stringbody = $body instanceof \DOMNode ? $dom->saveHTML($body) : '';
        $stringbody = preg_replace('/<body>(.*?)<\/body>/si', '${1}', (string)$stringbody) ?? '';
        $string['bodytext'] = GoogleDocsSectionParser::stripXmlArtifacts(html_entity_decode($stringbody, ENT_QUOTES, 'UTF-8'));
        return $string;
    }

    /**
     * Google Docs HTML often ships &lt;img width="0" height="0"&gt; with the real size only in
     * style="width:…px;height:…px". After style stripping those zeros hide the image on the FE.
     */
    protected function normalizeImportedImageDimensions(\DOMElement $img, FileInterface $file): void
    {
        $width = (int)$img->getAttribute('width');
        $height = (int)$img->getAttribute('height');
        if ($width > 0 && $height > 0) {
            return;
        }

        $fileWidth = (int)($file->getProperty('width') ?? 0);
        $fileHeight = (int)($file->getProperty('height') ?? 0);
        if ($fileWidth <= 0 || $fileHeight <= 0) {
            $localPath = $file->getForLocalProcessing(false);
            if (is_string($localPath) && $localPath !== '' && is_file($localPath)) {
                $exif = @getimagesize($localPath);
                if (is_array($exif)) {
                    $fileWidth = (int)($exif[0] ?? 0);
                    $fileHeight = (int)($exif[1] ?? 0);
                }
            }
        }

        if ($fileWidth > 0 && $fileHeight > 0) {
            $img->setAttribute('width', (string)$fileWidth);
            $img->setAttribute('height', (string)$fileHeight);
            return;
        }

        // Last resort: drop zero dimensions so the browser uses intrinsic size.
        if ($width <= 0) {
            $img->removeAttribute('width');
        }
        if ($height <= 0) {
            $img->removeAttribute('height');
        }
    }

    public function findImageMimeType(string $mimeType): string
    {
        $filesType = [
            'image/apng' => 'apng',
            'image/bmp' => 'bmp',
            'image/gif' => 'gif',
            'image/x-icon' => 'ico',
            'image/jpeg' => 'jpeg',
            'image/png' => 'png',
            'image/svg+xml' => 'svg',
            'image/tiff' => 'tif',
            'image/webp' => 'webp',
        ];
        return $filesType[$mimeType] ?? 'jpg';
    }

    public function globalSettingsAction(): ResponseInterface
    {
        $this->ensureApiContentLoaded();
        $assign = [
            'globalSettings' => $this->globalSettings,
            'activePage' => $this->actionMethodName,
            'bootstrapVariable' => 'data-bs',
            'version' => $this->currentVersion->getMajorVersion(),
        ];
        $moduleTemplate = $this->initializeModuleTemplate($this->request);
        $moduleTemplate->assignMultiple($assign);
        return $moduleTemplate->renderResponse('UserInfo/GlobalSettings');
    }

    public function updateAction(): ResponseInterface
    {
        $request = $this->getPluginRequestArguments();
        $arr = ['status' => false];
        $parsedBody = $this->request->getParsedBody();

        if (is_array($parsedBody) && $parsedBody !== []) {
            $tokenError = GoogleTokenHelper::validateRefreshToken($request['GoogleDocsRefreshToken']);
            if ($tokenError !== null) {
                return new JsonResponse(['status' => false, 'error' => $tokenError]);
            }
            $result = $this->userInfoRepository->updateDocsConfig(
                (int)($this->globalSettings['clientInfo']['uid'] ?? 0),
                $request['GoogleDocsClientId'],
                $request['GoogleDocsSecretKey'],
                $request['GoogleDocsRefreshToken'],
                $request['importType']
            );
            $arr['status'] = (bool)$result;
        }

        return new JsonResponse($arr);
    }

    /**
     * @return list<array{label: string, colPos: int|string}>
     */
    protected function getBackendLayoutColumns(int $pageUid): array
    {
        $backendLayout = GeneralUtility::makeInstance(BackendLayoutView::class)
            ->getBackendLayoutForPage($pageUid);
        if ($backendLayout === null) {
            return [];
        }
        $usedColumns = $backendLayout->getUsedColumns();
        $isSingleColumn = count($usedColumns) === 1;
        $columns = [];
        foreach ($usedColumns as $colPos => $label) {
            $columns[] = [
                'label' => $this->resolveBackendLayoutColumnLabel((string)$label, $isSingleColumn),
                'colPos' => $colPos,
            ];
        }
        return $columns;
    }

    protected function resolveBackendLayoutColumnLabel(string $label, bool $isSingleColumn = false): string
    {
        if (str_starts_with($label, 'LLL:')) {
            $label = $this->getLanguageService()->sL($label) ?: $label;
        }

        $contentLabel = (string)(LocalizationUtility::translate('content_column', 'ns_googledocs') ?: 'Content');

        if ($isSingleColumn) {
            return $contentLabel;
        }

        if ($label === 'Normal' || $label === 'Full Width (Without Container)') {
            return $contentLabel;
        }

        return $label;
    }

    protected function initializeModuleTemplate(ServerRequestInterface $request): ModuleTemplate
    {
        $moduleTemplate = $this->moduleTemplateFactory->create($request);
        $this->pageRenderer->addCssFile('EXT:ns_googledocs/Resources/Public/Css/main.css');
        $this->pageRenderer->addInlineLanguageLabelFile(
            'EXT:ns_googledocs/Resources/Private/Language/locallang.xlf',
            'js.'
        );
        $this->pageRenderer->loadJavaScriptModule('@nitsan/ns-googledocs/Main.js');
        $premiumStrip = $this->premiumStrip->render();
        if ($premiumStrip !== '') {
            $this->pageRenderer->addCssFile(PremiumStrip::STYLESHEET);
        }
        $moduleTemplate->assign('premiumStrip', $premiumStrip);
        return $moduleTemplate;
    }

    /**
     * Build a filesystem-safe folder segment for imported media.
     */
    protected function sanitizeFolderName(string $name, int $pageId): string
    {
        $name = mb_strtolower(strip_tags($name), 'utf-8');
        if (class_exists(\Transliterator::class)) {
            $transliterator = \Transliterator::create('Any-Latin; Latin-ASCII;');
            if ($transliterator !== null) {
                $name = (string)($transliterator->transliterate($name) ?: $name);
            }
        }
        $name = (string)(preg_replace('/[^a-zA-Z0-9-_]/', '-', $name) ?? '');
        $name = (string)(preg_replace('/-+/', '-', $name) ?? '');
        $name = trim($name, '-');
        $name = (string)(preg_replace('/\s+/', '_', $name) ?? '');
        return $pageId . '-' . str_replace('/', '-', $name);
    }

    /**
     * Strip Google Docs HTML chrome and normalize common inline styles.
     */
    protected function normalizeGoogleDocsHtml(string $html): string
    {
        $html = (string)(preg_replace('#<head>(.*?)</head>#is', '', $html) ?? $html);
        $html = (string)(preg_replace('/<span(.*?)font-weight:700(.*?)>(.*?)<\/span>/si', '<span${1}${2}><b>${3}</b></span>', $html) ?? $html);
        $html = (string)(preg_replace('/<span(.*?)font-style:italic(.*?)>(.*?)<\/span>/si', '<span${1}${2}><i>${3}</i></span>', $html) ?? $html);
        $html = (string)(preg_replace('/<span(.*?)text-decoration:underline(.*?)>(.*?)<\/span>/si', '<span${1}${2}><u>${3}</u></span>', $html) ?? $html);
        // Promote CSS width/height on <img> to real attributes before style stripping — Google
        // Docs often uses width="0" height="0" with the visible size only in style="…px".
        $html = (string)(preg_replace_callback(
            '/<img\b([^>]*?)style="([^"]*)"([^>]*)>/i',
            static function (array $m): string {
                $before = $m[1];
                $style = $m[2];
                $after = $m[3];
                $attrs = $before . $after;
                if (preg_match('/(?:^|;)\s*width\s*:\s*([\d.]+)\s*px/i', $style, $wm)
                    && (!preg_match('/\bwidth\s*=\s*"(?!0(?:\.0+)?)[^"]*"/i', $attrs))
                ) {
                    $attrs .= ' width="' . (int)round((float)$wm[1]) . '"';
                }
                if (preg_match('/(?:^|;)\s*height\s*:\s*([\d.]+)\s*px/i', $style, $hm)
                    && (!preg_match('/\bheight\s*=\s*"(?!0(?:\.0+)?)[^"]*"/i', $attrs))
                ) {
                    $attrs .= ' height="' . (int)round((float)$hm[1]) . '"';
                }
                return '<img' . $attrs . '>';
            },
            $html
        ) ?? $html);
        // Strip style/id attributes without consuming the trailing "/>" of void tags (which
        // previously left "<hr/" that RTE then expanded to the invalid "<hr/ />").
        $html = (string)(preg_replace('/\s(?:style|id)="[^"]*"/i', '', $html) ?? $html);
        $html = (string)(preg_replace('/(.*?)<body>(.*?)<\/body>(.*?)/si', '$2', $html) ?? $html);
        $html = str_replace('</html>', '', $html);
        return GoogleDocsSectionParser::normalizeVoidTags($html);
    }

    /**
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
    protected function getPluginRequestArguments(): array
    {
        $parsedBody = $this->request->getParsedBody();
        $extbase = [];
        try {
            $arguments = $this->request->getArguments();
            if (is_array($arguments)) {
                $extbase = $arguments;
            }
        } catch (\Throwable) {
        }

        return ImportRequestMapper::map(
            is_array($parsedBody) ? $parsedBody : null,
            $this->request->getQueryParams(),
            $extbase
        );
    }

    protected function getBackendUser(): BackendUserAuthentication
    {
        return $GLOBALS['BE_USER'];
    }

    protected function getLanguageService(): LanguageService
    {
        return $GLOBALS['LANG'];
    }
}
