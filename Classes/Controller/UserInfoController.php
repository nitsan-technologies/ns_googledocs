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

use NITSAN\NsGoogledocs\Domain\Repository\UserInfoRepository;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Backend\Utility\BackendUtility as BackendUtilityCore;
use TYPO3\CMS\Backend\View\BackendLayoutView;
use TYPO3\CMS\Core\Core\Environment;
use TYPO3\CMS\Core\Http\JsonResponse;
use TYPO3\CMS\Core\Information\Typo3Version;
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

    public function __construct(
        UserInfoRepository $userInfoRepository,
        ModuleTemplateFactory $moduleTemplateFactory,
        ResourceFactory $resourceFactory,
        StorageRepository $storageRepository
    ) {
        $this->userInfoRepository = $userInfoRepository;
        $this->moduleTemplateFactory = $moduleTemplateFactory;
        $this->resourceFactory = $resourceFactory;
        $this->storageRepository = $storageRepository;
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
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        $this->ensureGoogleClientAutoload();
        $this->currentVersion = GeneralUtility::makeInstance(Typo3Version::class);
        $this->globalSettings['siteRoot'] = Environment::getPublicPath() . '/';
        $this->userData = $GLOBALS['BE_USER']->user ?? [];

        $googleDocsSetupWizard = 'https://composer.t3terminal.com/API/ExtBackendModuleAPI.php?extKey=ns_googledocs&blockName=GoogleDocsSetupWizard';
        $dashboardSupportUrl = 'https://composer.t3terminal.com/API/ExtBackendModuleAPI.php?extKey=ns_googledocs&blockName=DashboardSupport';
        $generalFooterUrl = 'https://composer.t3terminal.com/API/ExtBackendModuleAPI.php?extKey=ns_googledocs&blockName=GeneralFooter';
        $premiumExtensionUrl = 'https://composer.t3terminal.com/API/ExtBackendModuleAPI.php?extKey=ns_googledocs&blockName=PremiumExtension';

        $this->userInfoRepository->deleteOldApiData();
        $checkApiData = $this->userInfoRepository->checkApiData();
        if (!$checkApiData) {
            $this->apiContent['googleDocsSetupWizard'] = $this->userInfoRepository->curlInitCall($googleDocsSetupWizard);
            $this->apiContent['dashboardSupportData'] = $this->userInfoRepository->curlInitCall($dashboardSupportUrl);
            $this->apiContent['generalFooterData'] = $this->userInfoRepository->curlInitCall($generalFooterUrl);
            $this->apiContent['premiumExtensionData'] = $this->userInfoRepository->curlInitCall($premiumExtensionUrl);

            $data = [
                'googleDocsSetupWizard' => $this->apiContent['googleDocsSetupWizard'],
                'support_html' => $this->apiContent['dashboardSupportData'],
                'footer_html' => $this->apiContent['generalFooterData'],
                'premuim_extension_html' => $this->apiContent['premiumExtensionData'],
                'extension_key' => 'ns_googledocs',
                'last_update' => date('Y-m-d'),
            ];
            $this->userInfoRepository->insertNewData($data);
        } else {
            $this->apiContent['googleDocsSetupWizard'] = $checkApiData['googleDocsSetupWizard'] ?? '';
            $this->apiContent['dashboardSupportData'] = $checkApiData['support_html'] ?? '';
            $this->apiContent['premiumExtensionData'] = $checkApiData['premuim_extension_html'] ?? '';
        }

        if (($this->userData['uid'] ?? 0) > 0 && ($this->userData['client_id'] ?? '') !== '') {
            try {
                $this->client = new \Google_Client();
                $this->client->setClientId($this->userData['client_id']);
                $this->client->setClientSecret($this->userData['client_secret'] ?? '');
                $this->client->refreshToken($this->userData['refresh_token'] ?? '');
                $this->client->setAccessType('offline');
                $this->service = new \Google_Service_Drive($this->client);
                $this->globalSettings['clientException'] = false;
            } catch (\Exception $e) {
                $this->globalSettings['clientException'] = transalte::translate('service.exception', 'ns_googledocs');
            }
            $this->globalSettings['clientStatus'] = true;
            $this->globalSettings['clientInfo']['client_secret'] = $this->userData['client_secret'] ?? '';
            $this->globalSettings['clientInfo']['client_id'] = $this->userData['client_id'] ?? '';
            $this->globalSettings['clientInfo']['refresh_token'] = $this->userData['refresh_token'] ?? '';
            $this->globalSettings['clientInfo']['google_files_request'] = $this->userData['google_files_request'] ?? 10;
        } else {
            $this->globalSettings['clientStatus'] = false;
            if (!isset($_COOKIE['firstTimeLoad'])) {
                setcookie('firstTimeLoad', '1', time() + (86400 * 30 * 365), '/', '', false, true);
                $this->redirect('globalSettings');
            }
        }

        $this->globalSettings['clientInfo']['uid'] = $this->userData['uid'] ?? 0;
        if (($this->userData['import_type'] ?? '') !== '') {
            $importType = explode(',', (string)$this->userData['import_type']);
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

    public function dashboardAction(): ResponseInterface
    {
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
            ];
        }

        $moduleTemplate = $this->initializeModuleTemplate($this->request);
        $moduleTemplate->assignMultiple($assign);
        return $moduleTemplate->renderResponse('UserInfo/Import');
    }

    public function docsImportAction(): ResponseInterface
    {
        $request = $this->getPluginRequestArguments();
        $pageID = $this->pageUid;
        if ($pageID <= 0) {
            $pageID = (int)($request['pageId'] ?? $request['id'] ?? 0);
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
        $googleDocsId = (string)($request['googleDocsID'] ?? $request['googleDocsId'] ?? '');

        if ($this->service === null || $googleDocsId === '' || $pageID <= 0) {
            return new JsonResponse($arr);
        }

        try {
            $response = $this->service->files->export($googleDocsId, 'text/html');
            $html = $this->normalizeGoogleDocsHtml((string)$response->getBody());
            $stringbody = html_entity_decode($html, ENT_QUOTES, 'UTF-8');

            $elements = $this->findAndReplace($stringbody);
            if ($elements === []) {
                // Plain Google Doc without ### markers: import whole body as one text CE.
                $stringbodyParsed = $this->updateImageInBodytext($stringbody, 1);
                $elements = [[
                    'CType' => 'text',
                    'header' => $stringbodyParsed['header'] ?? '',
                    'header_layout' => $stringbodyParsed['header_layout'] ?? 0,
                    'bodytext' => trim((string)($stringbodyParsed['bodytext'] ?? '')),
                    'sorting' => $this->userInfoRepository->findElementSorting($pageID) + 256,
                ]];
            }

            foreach ($elements as $element) {
                $result = $this->userInfoRepository->insertPageElement($pageID, $element, $request);
                if (!$result) {
                    return new JsonResponse(['status' => false, 'affectedRows' => 0]);
                }
            }

            $arr['status'] = true;
        } catch (\Throwable $e) {
            $arr['status'] = false;
            $arr['error'] = $e->getMessage();
        }

        return new JsonResponse($arr);
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
        $doc = new \DOMDocument();
        @$doc->loadHTML($content);
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
            if ($src === '') {
                continue;
            }
            if (!@copy($src, $fileName) || !is_file($fileName)) {
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
        $images = $this->findImage($content);
        $content = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head><body>' . $content . '</body></html>';
        $dom = new \DOMDocument();
        @$dom->loadHTML($content);
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
        $content = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head><body>' . $content . '</body></html>';
        $dom = new \DOMDocument();
        @$dom->loadHTML($content);
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

    public function premiumAction(): ResponseInterface
    {
        $assign = [
            'premiumExtensionData' => $this->apiContent['premiumExtensionData'] ?? '',
            'globalSettings' => $this->globalSettings,
            'activePage' => $this->actionMethodName,
            'googleDocsSetupWizard' => $this->apiContent['googleDocsSetupWizard'] ?? '',
            'bootstrapVariable' => 'data-bs',
            'version' => $this->currentVersion->getMajorVersion(),
        ];
        $moduleTemplate = $this->initializeModuleTemplate($this->request);
        $moduleTemplate->assignMultiple($assign);
        return $moduleTemplate->renderResponse('UserInfo/Premium');
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
        return $this->moduleTemplateFactory->create($request);
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
     * @return array<string, mixed>
     */
    protected function getPluginRequestArguments(): array
    {
        $parsedBody = $this->request->getParsedBody();
        if (is_array($parsedBody)) {
            foreach ($parsedBody as $value) {
                if (is_array($value) && (
                    isset($value['GoogleDocsClientId'])
                    || isset($value['googleDocsID'])
                    || isset($value['GoogleDocsSecretKey'])
                    || isset($value['importType'])
                    || isset($value['colPos'])
                )) {
                    return $value;
                }
            }
            if (
                isset($parsedBody['GoogleDocsClientId'])
                || isset($parsedBody['googleDocsID'])
                || isset($parsedBody['GoogleDocsSecretKey'])
            ) {
                return $parsedBody;
            }
        }

        try {
            $arguments = $this->request->getArguments();
            if (is_array($arguments) && $arguments !== []) {
                return $arguments;
            }
        } catch (\Throwable) {
            // Fall through
        }

        return [];
    }
}
