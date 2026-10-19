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
use NITSAN\NsGoogledocs\Service\RemoteContentFetcher;
use NITSAN\NsGoogledocs\Service\RemoteHtmlSanitizer;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility as BackendUtilityCore;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility as transalte;

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
    protected bool $apiContentLoaded = false;

    private const PREVIEW_SESSION_KEY = 'ns_googledocs_import_preview';

    public function __construct(
        UserInfoRepository $userInfoRepository,
        ModuleTemplateFactory $moduleTemplateFactory,
        ResourceFactory $resourceFactory,
        StorageRepository $storageRepository,
        PremiumStrip $premiumStrip,
        PageRenderer $pageRenderer,
        RemoteHtmlSanitizer $remoteHtmlSanitizer,
        RemoteContentFetcher $remoteContentFetcher,
        GoogleDocsSectionParser $sectionParser
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
    }

    /**
     * Prefer project Composer autoload (google/apiclient). Fall back to the
     * extension-local vendor/ for Classic installs.
     */
    protected function ensureGoogleClientAutoload(): void
    {
        if (class_exists(\Google\Client::class) || class_exists(\Google_Client::class)) {
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
        $this->userData = $GLOBALS['BE_USER']->user ?? [];
        $this->apiContent = [
            'googleDocsSetupWizard' => '',
            'dashboardSupportData' => '',
            'generalFooterData' => '',
        ];

        $clientId = $this->resolveUserCredential('client_id');
        $clientSecret = $this->resolveUserCredential('client_secret');
        $refreshToken = $this->resolveUserCredential('refresh_token');
        $importTypeRaw = $this->resolveUserCredential('import_type');

        if (($this->userData['uid'] ?? 0) > 0 && $clientId !== '') {
            try {
                $this->client = new \Google_Client();
                $this->client->setClientId($clientId);
                $this->client->setClientSecret($clientSecret);
                $this->client->refreshToken($refreshToken);
                $this->client->setAccessType('offline');
                $this->service = new \Google_Service_Drive($this->client);
                $this->globalSettings['clientException'] = false;
            } catch (\Exception $e) {
                $this->globalSettings['clientException'] = transalte::translate('service.exception', 'ns_googledocs');
            }
            $this->globalSettings['clientStatus'] = true;
            $this->globalSettings['clientInfo']['client_id'] = $clientId;
            $this->globalSettings['clientInfo']['client_secret'] = $clientSecret;
            $this->globalSettings['clientInfo']['refresh_token'] = $refreshToken;
            $this->globalSettings['clientInfo']['google_files_request'] = $this->userData['google_files_request'] ?? 10;
        } else {
            $this->globalSettings['clientStatus'] = false;
            $this->globalSettings['clientInfo']['client_id'] = '';
            $this->globalSettings['clientInfo']['client_secret'] = '';
            $this->globalSettings['clientInfo']['refresh_token'] = '';
            $sessionKey = 'ns_googledocs';
            $sessionData = $GLOBALS['BE_USER']->getSessionData($sessionKey);
            if (!is_array($sessionData) || empty($sessionData['firstTimeLoad'])) {
                $GLOBALS['BE_USER']->setAndSaveSessionData($sessionKey, ['firstTimeLoad' => true]);
                $this->redirect('globalSettings');
            }
        }

        $this->globalSettings['clientInfo']['uid'] = $this->userData['uid'] ?? 0;
        if ($importTypeRaw !== '') {
            $importType = explode(',', $importTypeRaw);
            $importTypeInfo = [];
            foreach ($importType as $type) {
                $importTypeInfo[$type] = transalte::translate('import_type.' . $type, 'ns_googledocs');
            }
            $this->globalSettings['clientInfo']['import_type'] = $importTypeInfo;
        }
        $this->globalSettings['clientInfo']['name'] = ($this->userData['realName'] ?? '') !== ''
            ? $this->userData['realName']
            : ($this->userData['username'] ?? '');

        $queryParams = $this->request->getQueryParams();
        $parsedBody = $this->request->getParsedBody();
        $this->pageUid = (int)($queryParams['id'] ?? 0);
        if ($this->pageUid <= 0 && is_array($parsedBody)) {
            foreach ($parsedBody as $value) {
                if (is_array($value) && isset($value['pageId'])) {
                    $this->pageUid = (int)$value['pageId'];
                    break;
                }
            }
            if ($this->pageUid <= 0) {
                $this->pageUid = (int)($parsedBody['id'] ?? $parsedBody['pageId'] ?? 0);
            }
        }
        $this->pageInformation = BackendUtilityCore::readPageAccess(
            $this->pageUid,
            $GLOBALS['BE_USER']->getPagePermsClause(1)
        );
        parent::initializeAction();
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

        $checkApiData = $this->userInfoRepository->checkApiData();
        if (!$checkApiData) {
            $this->userInfoRepository->deleteOldApiData();
            $googleDocsSetupWizard = 'https://composer.t3terminal.com/API/ExtBackendModuleAPI.php?extKey=ns_googledocs&blockName=GoogleDocsSetupWizard';
            $dashboardSupportUrl = 'https://composer.t3terminal.com/API/ExtBackendModuleAPI.php?extKey=ns_googledocs&blockName=DashboardSupport';
            $generalFooterUrl = 'https://composer.t3terminal.com/API/ExtBackendModuleAPI.php?extKey=ns_googledocs&blockName=GeneralFooter';

            $this->apiContent['googleDocsSetupWizard'] = (string)$this->userInfoRepository->curlInitCall($googleDocsSetupWizard);
            $this->apiContent['dashboardSupportData'] = (string)$this->userInfoRepository->curlInitCall($dashboardSupportUrl);
            $this->apiContent['generalFooterData'] = (string)$this->userInfoRepository->curlInitCall($generalFooterUrl);

            $data = [
                'googleDocsSetupWizard' => $this->apiContent['googleDocsSetupWizard'],
                'support_html' => $this->apiContent['dashboardSupportData'],
                'footer_html' => $this->apiContent['generalFooterData'],
                'premuim_extension_html' => '',
                'extension_key' => 'ns_googledocs',
                'last_update' => date('Y-m-d'),
            ];
            $this->userInfoRepository->insertNewData($data);
        } else {
            $this->apiContent['googleDocsSetupWizard'] = (string)($checkApiData['googleDocsSetupWizard'] ?? '');
            $this->apiContent['dashboardSupportData'] = (string)($checkApiData['support_html'] ?? '');
            $this->apiContent['generalFooterData'] = (string)($checkApiData['footer_html'] ?? '');
        }

        foreach (['googleDocsSetupWizard', 'dashboardSupportData', 'generalFooterData'] as $key) {
            $this->apiContent[$key] = $this->remoteHtmlSanitizer->sanitize((string)($this->apiContent[$key] ?? ''));
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

    public function dashboardAction(): ResponseInterface
    {
        $this->ensureApiContentLoaded();
        $assign = [
            'globalSettings' => $this->globalSettings,
            'activePage' => $this->actionMethodName,
            'pageInformation' => $this->pageInformation,
            'sidebarData' => $this->apiContent['dashboardSupportData'] ?? '',
            'googleDocsSetupWizard' => $this->apiContent['googleDocsSetupWizard'] ?? '',
            'bootstrapVariable' => 'data-bs',
            'version' => $this->currentVersion->getMajorVersion(),
        ];
        $moduleTemplate = $this->initializeModuleTemplate($this->request);
        $moduleTemplate->assignMultiple($assign);
        return $moduleTemplate->renderResponse('UserInfo/Dashboard');
    }

    public function importAction(): ResponseInterface
    {
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
                'defaultElementType' => $this->getDefaultContentElementType(),
                'elementTypeOptionsJson' => '{}',
                'previewSectionsUri' => '',
                'pageColPos' => [],
            ];
        } else {
            $this->globalSettings['pageTitle'] = $this->pageInformation['title'] ?? '';
            $this->globalSettings['importStatus'] = false;
            $doktype = (int)($this->pageInformation['doktype'] ?? 0);

            if ($doktype === 254) {
                $this->globalSettings['importStatus'] = false;
            } else {
                $pageColPos = $this->getBackendLayoutColumns((int)$this->pageUid);
                if ($doktype === 137) {
                    $this->globalSettings['importStatus'] = false;
                } else {
                    $this->globalSettings['docsImportType'] = transalte::translate('reports_import_type.1', 'ns_googledocs');
                    $this->globalSettings['docsImportTypeID'] = 1;
                    if (isset($this->globalSettings['clientInfo']['import_type'][1])) {
                        $this->globalSettings['importStatus'] = true;
                    }
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
                            if ($this->findDocsType($file->getMimeType()) === 'Docs'
                                || $this->findDocsType($file->getMimeType()) === 'Document'
                            ) {
                                $docsFields[$key]['id'] = $file->getId();
                                $docsFields[$key]['name'] = $file->getName();
                                $docsFields[$key]['lastModified'] = $file->getModifiedTime();
                                $docsFields[$key]['docType'] = $this->findDocsType($file->getMimeType());
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
                    }
                    $msgException = false;
                } catch (\Google_Service_Exception $e) {
                    $msgException = transalte::translate('listFiles.exception', 'ns_googledocs');
                }
            }

            $assign = [
                'pageColPos' => $pageColPos,
                'docsFiels' => $docsFields,
                'activePage' => $this->actionMethodName,
                'globalSettings' => $this->globalSettings,
                'pageStatus' => true,
                'msgException' => $msgException,
                'googleDocsSetupWizard' => $this->apiContent['googleDocsSetupWizard'] ?? '',
                'bootstrapVariable' => 'data-bs',
                'pageId' => (int)($this->pageInformation['uid'] ?? $this->pageUid),
                'version' => $this->currentVersion->getMajorVersion(),
                'defaultElementType' => $this->getDefaultContentElementType(),
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
        $googleDocsId = $this->extractGoogleDocsIdFromArguments($request);
        if ($this->service === null || $googleDocsId === '') {
            return new JsonResponse(['status' => false, 'error' => 'Missing Google Docs id or client.']);
        }

        try {
            $sections = $this->fetchAndParseSections($googleDocsId);
            $defaultType = $this->normalizeContentElementType($this->getDefaultContentElementType());
            $payload = [];
            $untitledLabel = (string)(transalte::translate('import_section_untitled', 'ns_googledocs') ?: 'Untitled section');
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
            return new JsonResponse(['status' => false, 'error' => $e->getMessage()]);
        }
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
                $GLOBALS['BE_USER']->getPagePermsClause(1)
            );
        }

        $googleDocsFolderName = $this->sanitizeFolderName((string)($request['googleDocsName'] ?? ''), $pageID);
        $docFolder = 'ns_googledocs/pages/' . $googleDocsFolderName . '/';
        $this->globalSettings['googleDocsFolderName'] = $docFolder;

        $arr = ['status' => false, 'affectedRows' => 0];
        $googleDocsId = $this->extractGoogleDocsIdFromArguments($request);

        if ($this->service === null) {
            return new JsonResponse(['status' => false, 'error' => 'Google client is not configured for this backend user.']);
        }
        if ($googleDocsId === '') {
            return new JsonResponse(['status' => false, 'error' => 'Missing Google Docs file id.']);
        }
        if ($pageID <= 0) {
            return new JsonResponse(['status' => false, 'error' => 'Missing page id. Select a page in the page tree and try again.']);
        }

        $preview = $GLOBALS['BE_USER']->getSessionData(self::PREVIEW_SESSION_KEY);
        $cachedSections = [];
        if (is_array($preview)
            && ($preview['googleDocsId'] ?? '') === $googleDocsId
            && !empty($preview['sections'])
            && is_array($preview['sections'])
        ) {
            $cachedSections = $preview['sections'];
        } else {
            // Session can drop between preview and import — rebuild sections from the Doc.
            try {
                $cachedSections = $this->fetchAndParseSections($googleDocsId);
            } catch (\Throwable $e) {
                return new JsonResponse([
                    'status' => false,
                    'error' => 'Could not read document sections: ' . $e->getMessage(),
                ]);
            }
        }

        if ($cachedSections === []) {
            return new JsonResponse([
                'status' => false,
                'error' => 'No importable sections found in this Google Doc. Add Heading 1 / Heading 2 sections and try again.',
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
                $element = $this->buildElementFromSection($section, $cType, $header);

                $result = $this->userInfoRepository->insertPageElement($pageID, $element, $request, $insertAfterUid);
                if (!$result) {
                    $errors = $this->userInfoRepository->getLastDataHandlerErrors();
                    return new JsonResponse([
                        'status' => false,
                        'affectedRows' => $imported,
                        'error' => $errors !== []
                            ? implode('; ', $errors)
                            : 'The document was not imported successfully.',
                    ]);
                }
                $imported++;
                $newUid = $this->userInfoRepository->getLastInsertedUid();
                $insertAfterUid = $newUid > 0
                    ? $newUid
                    : $this->userInfoRepository->findLastContentElementUidInColumn($pageID, $colPos);
            }

            if ($imported === 0) {
                return new JsonResponse([
                    'status' => false,
                    'error' => 'No sections were selected for import.',
                ]);
            }

            $arr['status'] = true;
            $arr['imported'] = $imported;
            $GLOBALS['BE_USER']->setAndSaveSessionData(self::PREVIEW_SESSION_KEY, null);
        } catch (\Throwable $e) {
            $arr['status'] = false;
            $arr['error'] = $e->getMessage();
        }

        return new JsonResponse($arr);
    }

    /**
     * @return list<array{index: int, title: string, fallbackTitle?: string, headingLevel: int, html: string, hasImages: bool}>
     */
    protected function fetchAndParseSections(string $googleDocsId): array
    {
        $rawHtml = $this->readGoogleDocsExportHtml($googleDocsId);
        if (trim($rawHtml) === '') {
            throw new \RuntimeException('Google Docs export returned empty HTML for this file.', 1760854500);
        }
        $html = $this->normalizeGoogleDocsHtml($rawHtml);
        $stringbody = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
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
        $GLOBALS['BE_USER']->setAndSaveSessionData(self::PREVIEW_SESSION_KEY, [
            'googleDocsId' => $googleDocsId,
            'sections' => $sections,
        ]);
        return $sections;
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
     * Prefer CTypes that typical site packages keep enabled (text / textpic).
     */
    protected function normalizeContentElementType(string $cType): string
    {
        if (!in_array($cType, ['header', 'text', 'textpic', 'image'], true)) {
            $cType = $this->getDefaultContentElementType();
        }
        // Many distributions (incl. theme_camino) remove header/image from the wizard;
        // map to still-available equivalents so DataHandler can create records.
        return match ($cType) {
            'header' => 'text',
            'image' => 'textpic',
            default => $cType,
        };
    }

    /**
     * @param array{index: int, title: string, fallbackTitle?: string, headingLevel: int, html: string, hasImages: bool} $section
     * @return array<string, mixed>
     */
    protected function buildElementFromSection(array $section, string $cType, string $header): array
    {
        $html = (string)($section['html'] ?? '');
        $headingLevel = (int)($section['headingLevel'] ?? 1);
        $element = [
            'CType' => $cType,
            'header' => $header,
            'header_layout' => $headingLevel > 0 ? $headingLevel : 1,
            'imageorient' => 0,
        ];

        if ($cType === 'header') {
            return $element;
        }

        if ($cType === 'image') {
            $element['images'] = $this->findImage($html);
            $element['bodytext'] = '';
            return $element;
        }

        if ($cType === 'textpic') {
            $element['images'] = $this->findImage($html);
            $bodyWithoutImages = preg_replace('/<img[^>]+>/i', '', $html) ?? $html;
            $parsed = $this->findHeaderInBodytext($bodyWithoutImages, 0);
            $element['bodytext'] = trim((string)($parsed['bodytext'] ?? ''));
            return $element;
        }

        // text: keep inline images rewritten to fileadmin URLs
        $parsed = $this->updateImageInBodytext($html, 0);
        $element['bodytext'] = trim((string)($parsed['bodytext'] ?? ''));
        return $element;
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
        return [
            'header' => (string)(transalte::translate('element_type.header', 'ns_googledocs') ?: 'Header'),
            'text' => (string)(transalte::translate('element_type.text', 'ns_googledocs') ?: 'Text Element'),
            'textpic' => (string)(transalte::translate('element_type.textpic', 'ns_googledocs') ?: 'Text & Image'),
            'image' => (string)(transalte::translate('element_type.image', 'ns_googledocs') ?: 'Image Only'),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findAndReplace(string $body): array
    {
        $elementSorting = $this->userInfoRepository->findElementSorting((int)($this->pageInformation['uid'] ?? 0));

        $body = preg_replace('/<a href="(.*?)q=(.*?)&sa(.*?)">/si', '<a href="$2">', $body) ?? $body;
        $body = str_replace('<p><span>###TEXT###</span></p>', '||text::', $body);
        $body = str_replace('<p><span>###TEXTIMAGELEFT###</span></p>', '||textpic-18::', $body);
        $body = str_replace('<p><span>###TEXTIMAGERIGHT###</span></p>', '||textpic-17::', $body);
        $body = str_replace('<p><span>###TEXTIMAGETOP###</span></p>', '||textpic-0::', $body);
        $body = str_replace('<p><span>###TEXTIMAGEBOTTOM###</span></p>', '||textpic-8::', $body);
        $body = str_replace('<p><span>###IMAGES###</span></p>', '||image::', $body);
        $body = str_replace('<span>', '', $body);
        $body = str_replace('</span>', '', $body);
        $body = str_replace('<p></p>', '', $body);

        $elements = explode('||', $body);
        $finalElements = [];
        $i = 1;
        foreach ($elements as $key => $value) {
            $elm = explode('::', $value);
            if (count($elm) > 1) {
                $sorting = ($i * 256) + $elementSorting;
                $elm[1] = preg_replace('/\>\s+\</m', '><', $elm[1]) ?? $elm[1];
                switch (trim($elm[0])) {
                    case 'image':
                        $finalElements[$key]['CType'] = 'image';
                        $finalElements[$key]['images'] = $this->findImage($elm[1]);
                        $finalElements[$key]['imageorient'] = 0;
                        $stringbody = $this->updateImageInBodytext($elm[1], 1);
                        $finalElements[$key]['header'] = $stringbody['header'] ?? '';
                        $finalElements[$key]['header_layout'] = $stringbody['header_layout'] ?? 0;
                        break;
                    case 'text':
                        $stringbody = $this->updateImageInBodytext($elm[1], 1);
                        $finalElements[$key]['CType'] = 'text';
                        $finalElements[$key]['header'] = $stringbody['header'] ?? '';
                        $finalElements[$key]['header_layout'] = $stringbody['header_layout'] ?? 0;
                        $finalElements[$key]['bodytext'] = trim(preg_replace('/\>\s+\</m', '><', $stringbody['bodytext'] ?? '') ?? '');
                        break;
                    default:
                        $finalElements[$key]['CType'] = 'textpic';
                        $finalElements[$key]['images'] = $this->findImage($elm[1]);
                        $finalElements[$key]['imageorient'] = (int)str_replace('textpic-', '', $elm[0]);
                        $bodytext = preg_replace('/<img[^>]+>/i', '', $elm[1]) ?? '';
                        $stringbody = $this->findHeaderInBodytext($bodytext, 1);
                        $finalElements[$key]['header'] = $stringbody['header'] ?? '';
                        $finalElements[$key]['header_layout'] = $stringbody['header_layout'] ?? 0;
                        $finalElements[$key]['bodytext'] = preg_replace('/<img[^>]+>/i', '', $stringbody['bodytext'] ?? '') ?? '';
                        break;
                }
                $finalElements[$key]['sorting'] = $sorting;
                $i++;
            } elseif (strlen($elements[$key]) > 2) {
                $stringbody = $this->updateImageInBodytext($elm[0], 1);
                $sorting = ($i * 256) + $elementSorting;
                $finalElements[$key]['CType'] = 'text';
                $finalElements[$key]['header'] = $stringbody['header'] ?? '';
                $finalElements[$key]['header_layout'] = $stringbody['header_layout'] ?? 0;
                $finalElements[$key]['bodytext'] = trim(preg_replace('/\>\s+\</m', '><', $stringbody['bodytext'] ?? '') ?? '');
                $finalElements[$key]['sorting'] = $sorting;
                $i++;
            }
        }

        return $finalElements;
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
        $storageFolder = $storage->getRootLevelFolder();
        if (!file_exists($this->globalSettings['siteRoot'] . 'fileadmin/')) {
            GeneralUtility::mkdir_deep($this->globalSettings['siteRoot'] . 'fileadmin/');
        }
        $targetFolderName = (string)($this->globalSettings['googleDocsFolderName'] ?? 'ns_googledocs/');
        if (!file_exists($this->globalSettings['siteRoot'] . 'fileadmin/' . $targetFolderName)) {
            $storage->createFolder($targetFolderName);
        }
        $folder = $this->globalSettings['siteRoot'] . 'typo3temp/assets/images/ns_googledocs/';
        $storageFolder = $storage->getFolder($targetFolderName);
        $doc = $this->createDomDocumentFromHtml($content);
        if ($doc === null) {
            return $imagesUids;
        }
        $xml = simplexml_import_dom($doc);
        if ($xml === false) {
            return $imagesUids;
        }
        $images = $xml->xpath('//img');
        foreach ($images as $img) {
            $fileName = $folder . 'ns_googledocs_' . random_int(10, 1000000);
            if (!file_exists($folder)) {
                GeneralUtility::mkdir_deep($folder);
            }
            $src = (string)($img['src'] ?? '');
            if ($src === '' || !$this->remoteContentFetcher->isAllowedHttpsUrl($src)) {
                continue;
            }
            if (!$this->remoteContentFetcher->downloadToFile($src, $fileName) || !is_file($fileName)) {
                continue;
            }
            $exif = @getimagesize($fileName);
            $imageMineType = $this->findImageMimeType($exif['mime'] ?? '');
            $newFile = $storage->addFile(
                $fileName,
                $storageFolder,
                'ns_googledocs_' . random_int(10, 1000000) . '.' . $imageMineType
            );
            $imagesUids[] = $newFile->getUid();
        }
        return $imagesUids;
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
        $string['bodytext'] = html_entity_decode($stringbody, ENT_QUOTES, 'UTF-8');
        return $string;
    }

    /**
     * @return array{header?: string, header_layout?: int, bodytext: string}
     */
    public function findHeaderInBodytext(string $content, int $findHeader): array
    {
        $content = preg_replace("/<p[^>]*>(?:\s|&nbsp;)*<\/p>/", '', $content) ?? $content;
        if (trim($content) === '') {
            return ['bodytext' => ''];
        }
        $dom = $this->createDomDocumentFromHtml($content);
        if ($dom === null) {
            return ['bodytext' => $content];
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
        $string['bodytext'] = html_entity_decode($stringbody, ENT_QUOTES, 'UTF-8');
        return $string;
    }

    public function findImageMimeType(string $docType): string
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
        return $filesType[$docType] ?? 'jpg';
    }

    public function findDocsType(string $docType): string
    {
        $filesType = [
            'application/vnd.google-apps.audio' => 'Audio',
            'application/vnd.google-apps.document' => 'Document',
            'application/vnd.google-apps.drawing' => 'Drive file',
            'application/vnd.google-apps.file' => 'Drive folder',
            'application/vnd.google-apps.folder' => 'Folder',
            'application/vnd.google-apps.form' => 'Forms',
            'application/vnd.google-apps.fusiontable' => 'Fusion Tables',
            'application/vnd.google-apps.map' => 'My Maps',
            'application/vnd.google-apps.photo' => 'photo',
            'application/vnd.google-apps.presentation' => 'Slides',
            'application/vnd.google-apps.script' => 'Apps Scripts',
            'application/vnd.google-apps.site' => 'Sites',
            'application/vnd.google-apps.spreadsheet' => 'Sheets',
            'application/vnd.google-apps.unknown' => 'Unknown',
            'application/vnd.google-apps.video' => 'Video',
            'application/vnd.google-apps.drive-sdk' => '3rd party shortcut',
            'application/zip' => 'ZIP',
            'application/pdf' => 'PDF',
            'application/msword' => 'Docs',
            'image/x-photoshop' => 'Image',
            'image/jpeg' => 'Image',
            'image/png' => 'Image',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' => 'Sheet',
        ];
        return $filesType[$docType] ?? 'Unknown';
    }

    public function globalSettingsAction(): ResponseInterface
    {
        $this->ensureApiContentLoaded();
        $assign = [
            'globalSettings' => $this->globalSettings,
            'activePage' => $this->actionMethodName,
            'googleDocsSetupWizard' => $this->apiContent['googleDocsSetupWizard'] ?? '',
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

        if ($request !== []) {
            $result = $this->userInfoRepository->updateDocsConfig(
                (int)($this->globalSettings['clientInfo']['uid'] ?? 0),
                $request
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
            $label = $GLOBALS['LANG']->sL($label) ?: $label;
        }

        $contentLabel = (string)(transalte::translate('content_column', 'ns_googledocs') ?: 'Content');

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
        $html = (string)(preg_replace('/(<[^>]+) style=".*?"/i', '$1', $html) ?? $html);
        $html = (string)(preg_replace('/(<[^>]+) id=".*?"/i', '$1', $html) ?? $html);
        $html = (string)(preg_replace('/(.*?)<body>(.*?)<\/body>(.*?)/si', '$2', $html) ?? $html);
        return str_replace('</html>', '', $html);
    }

    /**
     * Collect Extbase plugin arguments from parsed body / query / Extbase args.
     *
     * Prefer the payload that still has a string googleDocsID. When section fields
     * are posted with a wrong name prefix, PHP can turn googleDocsID into an array
     * and the file id string is lost — recover by scanning the full body.
     *
     * @return array<string, mixed>
     */
    protected function getPluginRequestArguments(): array
    {
        $parsedBody = $this->request->getParsedBody();
        $candidates = [];
        if (is_array($parsedBody)) {
            foreach ($parsedBody as $value) {
                if (is_array($value) && (
                    array_key_exists('GoogleDocsClientId', $value)
                    || array_key_exists('googleDocsID', $value)
                    || array_key_exists('googleDocsId', $value)
                    || array_key_exists('GoogleDocsSecretKey', $value)
                    || array_key_exists('importType', $value)
                    || array_key_exists('colPos', $value)
                    || array_key_exists('sections', $value)
                    || array_key_exists('pageId', $value)
                )) {
                    $candidates[] = $value;
                }
            }
            if (
                array_key_exists('GoogleDocsClientId', $parsedBody)
                || array_key_exists('googleDocsID', $parsedBody)
                || array_key_exists('googleDocsId', $parsedBody)
                || array_key_exists('GoogleDocsSecretKey', $parsedBody)
                || array_key_exists('sections', $parsedBody)
            ) {
                $candidates[] = $parsedBody;
            }
        }

        $best = [];
        foreach ($candidates as $candidate) {
            $docId = $this->extractGoogleDocsIdFromArguments($candidate);
            if ($docId !== '') {
                $candidate['googleDocsID'] = $docId;
                return $candidate;
            }
            if ($best === [] || (isset($candidate['sections']) && !isset($best['sections']))) {
                $best = $candidate;
            }
        }

        if ($best !== []) {
            $docId = $this->extractGoogleDocsIdFromArguments(is_array($parsedBody) ? $parsedBody : []);
            if ($docId !== '') {
                $best['googleDocsID'] = $docId;
            }
            return $best;
        }

        try {
            $arguments = $this->request->getArguments();
            if (is_array($arguments) && $arguments !== []) {
                $docId = $this->extractGoogleDocsIdFromArguments($arguments);
                if ($docId !== '') {
                    $arguments['googleDocsID'] = $docId;
                }
                return $arguments;
            }
        } catch (\Throwable) {
            // Fall through
        }

        return [];
    }

    /**
     * @param array<string, mixed> $arguments
     */
    protected function extractGoogleDocsIdFromArguments(array $arguments): string
    {
        foreach (['googleDocsID', 'googleDocsId'] as $key) {
            if (!array_key_exists($key, $arguments)) {
                continue;
            }
            $value = $arguments[$key];
            if (is_string($value) || is_numeric($value)) {
                $id = trim((string)$value);
                if ($id !== '') {
                    return $id;
                }
            }
            // Corrupted by colliding section field names: googleDocsID became an array.
            if (is_array($value)) {
                foreach (['id', 'value', 'googleDocsID', 'googleDocsId'] as $nestedKey) {
                    if (isset($value[$nestedKey]) && (is_string($value[$nestedKey]) || is_numeric($value[$nestedKey]))) {
                        $id = trim((string)$value[$nestedKey]);
                        if ($id !== '') {
                            return $id;
                        }
                    }
                }
            }
        }

        foreach ($arguments as $value) {
            if (!is_array($value)) {
                continue;
            }
            $nested = $this->extractGoogleDocsIdFromArguments($value);
            if ($nested !== '') {
                return $nested;
            }
        }

        return '';
    }
}
