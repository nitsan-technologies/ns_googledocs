<?php
namespace NITSAN\NsGoogledocs\Controller;

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

use TYPO3\CMS\Backend\Utility\BackendUtility as BackendUtilityCore;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility as transalte;
use TYPO3\CMS\Core\Resource\ResourceFactory;

// Google API client: newer package needs PHP features available on TYPO3 11.1+;
// TYPO3 9.1–11.0 use the older bundled client; TYPO3 8 uses PATH_typo3conf.
if (version_compare(TYPO3_branch, '9.0', '>')) {
    if (version_compare(TYPO3_branch, '11.0', '>')) {
        require_once \TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/typo3conf/ext/ns_googledocs/Classes/google-api-php-client/vendor/autoload.php';
    } else {
        require_once \TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/typo3conf/ext/ns_googledocs/Classes/google-api-php-client-old/vendor/autoload.php';
    }
} else {
    require_once PATH_typo3conf . 'ext/ns_googledocs/Classes/google-api-php-client/vendor/autoload.php';
}
/**
 * UserInfoController
 */
class UserInfoController extends ActionController
{

    /**
     * userInfoRepository
     *
     * @var \NITSAN\NsGoogledocs\Domain\Repository\UserInfoRepository
     */
    protected $userInfoRepository = null;
    protected $userData = null;
    protected $client = null;
    protected $service = null;
    protected $globalSettings = null;
    protected $apiContent = null;

    /**
     * @param \NITSAN\NsGoogledocs\Domain\Repository\UserInfoRepository $userInfoRepository
     * @return void
     */
    public function injectUserInfoRepository(\NITSAN\NsGoogledocs\Domain\Repository\UserInfoRepository $userInfoRepository) {
        $this->userInfoRepository = $userInfoRepository;
    }
    /**
     * Initialize Action
     *
     * @return void
     */
    public function initializeAction()
    {
        // Find current user info
        if (version_compare(TYPO3_branch, '9.0', '>')) {
            $this->globalSettings['siteRoot'] = \TYPO3\CMS\Core\Core\Environment::getPublicPath() . '/';
        } else {
            $this->globalSettings['siteRoot'] = PATH_site;
        }
        $this->userData = $GLOBALS['BE_USER']->user;
        //Links for the All Dashboard VIEW from API...
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
                'support_html'=> $this->apiContent['dashboardSupportData'],
                'footer_html' => $this->apiContent['generalFooterData'],
                'premuim_extension_html' => $this->apiContent['premiumExtensionData'],
                'extension_key' => 'ns_googledocs',
                'last_update' => date('Y-m-d')
            ];
            $this->userInfoRepository->insertNewData($data);
        } else {
            $this->apiContent['googleDocsSetupWizard'] = $checkApiData['googleDocsSetupWizard'];
            $this->apiContent['dashboardSupportData'] = $checkApiData['support_html'];
            $this->apiContent['premiumExtensionData'] = $checkApiData['premuim_extension_html'];
        }
        if ($this->userData['uid'] > 0 && $this->userData['client_id'] !== '') {
            try {
                // Client initialize
                $this->client = new \Google_Client();
                $this->client->setClientId($this->userData['client_id']);
                $this->client->setClientSecret($this->userData['client_secret']);
                $this->client->refreshToken($this->userData['refresh_token']);
                // Service initialize
                $this->service = new \Google_Service_Drive($this->client);
                $this->globalSettings['clientException'] = false;
            } catch (\Exception $e) {
                $this->globalSettings['clientException'] = transalte::translate('service.exception', 'ns_googledocs');
            }
            // clientStatus and info updated
            $this->globalSettings['clientStatus'] = true;
            $this->globalSettings['clientInfo']['client_secret'] = $this->userData['client_secret'];
            $this->globalSettings['clientInfo']['client_id'] = $this->userData['client_id'];
            $this->globalSettings['clientInfo']['refresh_token'] = $this->userData['refresh_token'];
        } else {
            // clientStatus updated
            $this->globalSettings['clientStatus'] = false;
            if (!isset($_COOKIE['firstTimeLoad'])) {
                setcookie('firstTimeLoad', true, time() + (86400 * 30 * 365), '/', '', 0);
                $this->redirect('globalSettings');
            }
        }
        // clientInfo updated in globalSettings
        $this->globalSettings['clientInfo']['uid'] = $this->userData['uid'];
        if ($this->userData['import_type'] != '') {
            // importType configure
            $importType = explode(',', $this->userData['import_type']);
            foreach ($importType as $type) {
                $importTypeInfo[$type] = transalte::translate('import_type.' . $type, 'ns_googledocs');
            }
            $this->globalSettings['clientInfo']['import_type'] = $importTypeInfo;
        }
        $this->globalSettings['clientInfo']['name'] = ($this->userData['realName'] != '' ? $this->userData['realName'] : $this->userData['username']);
        $this->pageUid = (int) GeneralUtility::_GET('id');
        $this->pageInformation = BackendUtilityCore::readPageAccess($this->pageUid, '');
        parent::initializeAction();
    }

    /**
     * action dashboard
     *
     * @return void
     */
    public function dashboardAction()
    {
        $bootstrapVariable = 'data';
        if (version_compare(TYPO3_branch, '11.0', '>')) {
            $bootstrapVariable = 'data-bs';
        }
        // View assigned
        $assign = [
            'globalSettings' => $this->globalSettings,
            'activePage' => $this->actionMethodName,
            'pageInformation' => $this->pageInformation,
            'sidebarData' => $this->apiContent['dashboardSupportData'],
            'googleDocsSetupWizard' => $this->apiContent['googleDocsSetupWizard'],
            'bootstrapVariable' => $bootstrapVariable
        ];
        $this->view->assignMultiple($assign);
    }

    /**
     * action import
     *
     * @return void
     */
    public function importAction()
    {
        // If client not found then redirect to dashboard
        if (!$this->globalSettings['clientStatus']) {
            $this->redirect('dashboard');
        }
        if (!isset($this->pageInformation['uid'])) {
            // View assigned
            $assign = [
                'activePage' => $this->actionMethodName,
                'pageStatus' => false,
            ];
            $this->view->assignMultiple($assign);
        } else {
            // Find the page title
            $this->globalSettings['pageTitle'] = $this->pageInformation['title'];
            // importStatus default false
            $this->globalSettings['importStatus'] = false;
            // doktype 254 for news
            if ($this->pageInformation['doktype'] == 254) {
                $this->globalSettings['importStatus'] = false;
            } else {
                $backendLayout = GeneralUtility::makeInstance(\TYPO3\CMS\Backend\View\BackendLayoutView::class)->getSelectedBackendLayout($this->pageUid);
                foreach ($backendLayout['__items'] as $key => $backendLayout) {
                    $pageColPos[$key]['label'] = $backendLayout[0];
                    $pageColPos[$key]['colPos'] = $backendLayout[1];
                }
                // doktype 137 for blog
                if ($this->pageInformation['doktype'] == 137) {
                    $this->globalSettings['importStatus'] = false;
                } else {
                    // doktype 1 for pages
                    $this->globalSettings['docsImportType'] = transalte::translate('import_type.1', 'ns_googledocs');
                    $this->globalSettings['docsImportTypeID'] = 1;
                    if (isset($this->globalSettings['clientInfo']['import_type'][1])) {
                        $this->globalSettings['importStatus'] = true;
                    }
                }
            }
            if ($this->globalSettings['importStatus']) {
                // request additional params
                $docsFiels = [];
                $optParams = [
                  'pageSize' => 10,
                  'fields' => 'files(id, name, createdTime, modifiedTime, webViewLink, owners, mimeType, exportLinks)',
                  'q' => '\'me\' in owners and mimeType=\'application/vnd.google-apps.document\''
                ];
                try {
                    // request for files list
                    $results = $this->service->files->listFiles($optParams);
                    if (count($results->getFiles()) > 0) {
                        foreach ($results->getFiles() as $key => $file) {
                            if ($this->findDocsType($file->getMimeType()) == 'Docs' || $this->findDocsType($file->getMimeType()) == 'Document') {
                                // docsFiles array build
                                $docsFiels[$key]['id'] = $file->getId();
                                $docsFiels[$key]['name'] = $file->getName();
                                $docsFiels[$key]['lastModified'] = $file->getModifiedTime();
                                $docsFiels[$key]['docType'] = $this->findDocsType($file->getMimeType());
                                $docsFiels[$key]['owner'] = $file->getOwners()[0]->getDisplayName();
                                $docsFiels[$key]['importURL'] = $file->getExportLinks()['text/html'];
                                $docsFiels[$key]['ViewURL'] = $file->getWebViewLink();
                            }
                        }
                    }
                    $msgException = false;
                } catch (\Google_Service_Exception $e) {
                    $msgException = transalte::translate('listFiles.exception', 'ns_googledocs');
                }
            }
            $bootstrapVariable = 'data';
            if (version_compare(TYPO3_branch, '11.0', '>')) {
                $bootstrapVariable = 'data-bs';
            }
            // View assigned
            $assign = [
                'pageColPos' => $pageColPos,
                'docsFiels' => $docsFiels,
                'activePage' => $this->actionMethodName,
                'globalSettings' => $this->globalSettings,
                'pageInformation' => $this->pageInformation,
                'pageStatus' => true,
                'msgException' => $msgException,
                'googleDocsSetupWizard' => $this->apiContent['googleDocsSetupWizard'],
                'bootstrapVariable' => $bootstrapVariable
            ];
            $this->view->assignMultiple($assign);
        }
    }

    /**
     * action import
     *
     * @return void
     */
    public function docsImportAction()
    {
        try {
            $request = GeneralUtility::_GP('tx_nsgoogledocs_nitsan_nsgoogledocsgoogledocs');
            if (!is_array($request)) {
                $request = [];
            }
            // Prefer page id from form (AJAX), fall back to module GET id
            $pageID = (int)($request['pageUid'] ?? $this->pageUid);
            if ($pageID <= 0) {
                echo json_encode(['status' => false, 'message' => 'Please select a page in the page tree before importing.']);
                die;
            }
            if (empty($request['googleDocsID'])) {
                echo json_encode(['status' => false, 'message' => 'Missing Google Doc id.']);
                die;
            }

            // Ensure page information is available for sorting even when id came from POST
            if (empty($this->pageInformation['uid']) || (int)$this->pageInformation['uid'] !== $pageID) {
                $this->pageUid = $pageID;
                $this->pageInformation = BackendUtilityCore::readPageAccess($pageID, '');
            }

            $googleDocsFolderName = $this->sanitizeFolderName(
                $pageID . '-' . (string)($request['googleDocsName'] ?? 'google-doc')
            );
            // Nested path under fileadmin; created segment-by-segment in findImage()
            $docFolder = 'ns_googledocs/pages/' . $googleDocsFolderName;
            $this->globalSettings['googleDocsFolderName'] = $docFolder;

            // fetch file content
            $response = $this->service->files->export($request['googleDocsID'], 'text/html');
            $html = (string)$response->getBody();
            // Extract <body> with string offsets. Large Google Docs HTML (~1MB+) makes
            // preg_replace('/(.*?)<body>.../s') hit pcre.backtrack_limit and return null.
            $stringbody = $this->extractGoogleDocsBody($html);
            if ($stringbody === '') {
                echo json_encode(['status' => false, 'message' => 'Could not extract content from Google Doc.']);
                die;
            }
            // Convert inline Google Docs styles to semantic tags without nested (.*?) regexes
            // that hit pcre.backtrack_limit on large documents.
            $stringbody = $this->normalizeGoogleDocsInlineStyles($stringbody);
            // DOM/saveHTML often emits style='...' (single quotes). Strip both quote styles.
            $stringbody = $this->stripHtmlAttributes($stringbody);
            $stringbody = html_entity_decode($stringbody, ENT_QUOTES, 'UTF-8');

            $elements = $this->findAndReplace($stringbody);
            if (empty($elements)) {
                echo json_encode(['status' => false, 'message' => 'No importable content elements found in the Google Doc.']);
                die;
            }

            $inserted = 0;
            foreach ($elements as $element) {
                $result = $this->userInfoRepository->insertPageElement($pageID, $element, $request);
                if (!$result) {
                    echo json_encode(['status' => false, 'message' => 'Failed to insert content element into the page.']);
                    die;
                }
                $inserted++;
            }

            echo json_encode(['status' => true, 'affectedRows' => $inserted]);
            die;
        } catch (\Throwable $e) {
            echo json_encode(['status' => false, 'message' => $e->getMessage()]);
            die;
        }
    }

    /**
     * Extract inner HTML of the Google Docs export <body> without PCRE backtracking.
     *
     * @param string $html
     * @return string
     */
    protected function extractGoogleDocsBody($html)
    {
        if (!is_string($html) || $html === '') {
            return '';
        }
        $bodyPos = stripos($html, '<body');
        if ($bodyPos === false) {
            return '';
        }
        $contentStart = strpos($html, '>', $bodyPos);
        if ($contentStart === false) {
            return '';
        }
        $contentStart++;
        $bodyEnd = stripos($html, '</body>', $contentStart);
        if ($bodyEnd === false) {
            return '';
        }
        return substr($html, $contentStart, $bodyEnd - $contentStart);
    }

    /**
     * Convert Google Docs span styles to semantic tags using DOM (safe on large HTML).
     *
     * @param string $html
     * @return string
     */
    protected function normalizeGoogleDocsInlineStyles($html)
    {
        if (!is_string($html) || $html === '') {
            return '';
        }
        $doc = new \DOMDocument();
        $wrapped = '<?xml encoding="utf-8" ?><div id="ns-googledocs-root">' . $html . '</div>';
        @$doc->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        $xpath = new \DOMXPath($doc);
        $spans = $xpath->query('//*[@style]');
        if ($spans === false) {
            return $html;
        }
        // Walk backwards so nested replacements stay stable
        for ($i = $spans->length - 1; $i >= 0; $i--) {
            /** @var \DOMElement $node */
            $node = $spans->item($i);
            if (!$node instanceof \DOMElement || strtolower($node->tagName) !== 'span') {
                continue;
            }
            $style = strtolower((string)$node->getAttribute('style'));
            $wrapperTag = null;
            if (strpos($style, 'font-weight:700') !== false || strpos($style, 'font-weight:bold') !== false) {
                $wrapperTag = 'b';
            } elseif (strpos($style, 'font-style:italic') !== false) {
                $wrapperTag = 'i';
            } elseif (strpos($style, 'text-decoration:underline') !== false) {
                $wrapperTag = 'u';
            }
            if ($wrapperTag === null) {
                continue;
            }
            $wrapper = $doc->createElement($wrapperTag);
            while ($node->firstChild) {
                $wrapper->appendChild($node->firstChild);
            }
            if ($node->parentNode) {
                $node->parentNode->insertBefore($wrapper, $node);
                $node->parentNode->removeChild($node);
            }
        }
        $root = $doc->getElementById('ns-googledocs-root');
        if (!$root) {
            return $html;
        }
        $result = '';
        foreach ($root->childNodes as $child) {
            $result .= $doc->saveHTML($child);
        }
        return $result;
    }

    /**
    * findAndReplace
    *
    * @param string $body
    * @return void
    */
    public function findAndReplace($body)
    {
        // Find current page sorting field value
        $elementSorting = $this->userInfoRepository->findElementSorting($this->pageInformation['uid']);

        $body = preg_replace('/<a href="(.*?)q=(.*?)&sa(.*?)">/si', '<a href="$2">', $body);
        //body

        //text
        $body = str_replace('<p><span>###TEXT###</span></p>', '||text::', $body);
        //text pic left
        $body = str_replace('<p><span>###TEXTIMAGELEFT###</span></p>', '||textpic-18::', $body);
        //text pic right
        $body = str_replace('<p><span>###TEXTIMAGERIGHT###</span></p>', '||textpic-17::', $body);
        //text pic top
        $body = str_replace('<p><span>###TEXTIMAGETOP###</span></p>', '||textpic-0::', $body);
        //text pic bottom
        $body = str_replace('<p><span>###TEXTIMAGEBOTTOM###</span></p>', '||textpic-8::', $body);
        // only image
        $body = str_replace('<p><span>###IMAGES###</span></p>', '||image::', $body);
        // remove span tag
        $body = str_replace('<span>', '', $body);
        $body = str_replace('</span>', '', $body);
        // remove blank p
        $body = str_replace('<p></p>', '', $body);
        // Explode all the elements
        $elements = explode('||', $body);
        $finalElements = [];
        $i = 1;
        foreach ($elements as $key => $value) {
            // Explode key and content
            $elm = explode('::', $value);
            if (count($elm) > 1) {
                $sorting = ($i * 256) + $elementSorting;
                // configure element array with CType
                $elm[1] = preg_replace('/\>\s+\</m', '><', $elm[1]);
                switch (trim($elm[0])) {
                    case 'image':
                        // Element CType
                        $finalElements[$key]['CType'] = 'image';
                        // Find FAL object uid
                        $finalElements[$key]['images'] = $this->findImage($elm[1]);
                        // Image position
                        $finalElements[$key]['imageorient'] = 0;
                        // Split header and bodytext from staring
                        $stringbody = $this->updateImageInBodytext($elm[1], 1);
                        $finalElements[$key]['header'] = $stringbody['header'];
                        $finalElements[$key]['header_layout'] = $stringbody['header_layout'];
                        break;
                    case 'text':
                        $finalElements[$key] = $this->buildTextOrTextpicElement($elm[1], 0);
                        break;
                    default:
                        $imageorient = (int) str_replace('textpic-', '', $elm[0]);
                        $finalElements[$key] = $this->buildTextOrTextpicElement($elm[1], $imageorient, true);
                        break;
                }
                //Element sorting order
                $finalElements[$key]['sorting'] = $sorting;
                $i++;
            } else {
                if (strlen($elements[$key]) > 2) {
                    $sorting = ($i * 256) + $elementSorting;
                    // Docs without ### markers: if images exist, import as textpic + FAL
                    // so TYPO3 renders them. Inline <img> in CType=text is unreliable.
                    $finalElements[$key] = $this->buildTextOrTextpicElement($elm[0], 0);
                    $finalElements[$key]['sorting'] = $sorting;
                    $i++;
                } else {
                    unset($elements[$key]);
                }
            }
        }
        // return element array
        return $finalElements;
    }

    /**
    * findImage
    *
    * @return void
    */
    public function findImage($content)
    {
        // Resource instance
        $imagesUids = [];
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $storage = $resourceFactory->getDefaultStorage();
        if (!$storage) {
            return $imagesUids;
        }
        if (!file_exists($this->globalSettings['siteRoot'] . 'fileadmin/')) {
            GeneralUtility::mkdir_deep($this->globalSettings['siteRoot'] . 'fileadmin/');
        }

        // Create nested folders one segment at a time. Passing a full path with
        // special chars (e.g. [maik]) to createFolder()/getSubfolder() fails because
        // FAL sanitizes names on create but lookup still uses the unsanitized path.
        $storageFolder = $this->ensureStorageFolder(
            $storage,
            (string)($this->globalSettings['googleDocsFolderName'] ?? 'ns_googledocs/pages/import')
        );

        // tmp folder for image download
        $folder = $this->globalSettings['siteRoot'] . 'typo3temp/assets/images/ns_googledocs/';
        if (!file_exists($folder)) {
            GeneralUtility::mkdir_deep($folder);
        }
        // find the image src
        $doc = new \DOMDocument();
        @$doc->loadHTML('<?xml encoding="utf-8" ?>' . $content);
        $images = $doc->getElementsByTagName('img');
        foreach ($images as $img) {
            $src = $img->getAttribute('src');
            if ($src === '') {
                $imagesUids[] = 0;
                continue;
            }
            $fileName = $folder . 'ns_googledocs_' . rand(10, 1000000);
            $copied = @copy($src, $fileName);
            if (!$copied || !is_file($fileName)) {
                $imagesUids[] = 0;
                continue;
            }
            $exif = @getimagesize($fileName);
            if (!$exif || empty($exif['mime'])) {
                @unlink($fileName);
                $imagesUids[] = 0;
                continue;
            }
            // get file Mine type
            $imageMineType = $this->findImageMimeType($exif['mime']);
            if (!$imageMineType) {
                @unlink($fileName);
                $imagesUids[] = 0;
                continue;
            }
            try {
                // Insert in record in sys_file`
                $newFile = $storage->addFile(
                    $fileName,
                    $storageFolder,
                    'ns_googledocs_' . rand(10, 1000000) . '.' . $imageMineType
                );
                $imagesUids[] = $newFile->getUid();
            } catch (\Throwable $e) {
                @unlink($fileName);
                $imagesUids[] = 0;
            }
        }
        return $imagesUids;
    }

    /**
     * Build a text or textpic element from Google Docs HTML.
     * Uses textpic + FAL whenever images are present so media is visible in BE/FE.
     *
     * @param string $html
     * @param int $imageorient
     * @param bool $forceTextpic
     * @return array
     */
    protected function buildTextOrTextpicElement($html, $imageorient = 0, $forceTextpic = false)
    {
        // Keep failed downloads as 0 so indexes stay aligned with <img> tags
        $images = $this->findImage($html);
        $stringbody = $this->updateImageInBodytext($html, 1, $images);
        $bodytext = trim(preg_replace('/\>\s+\</m', '><', $stringbody['bodytext']) ?? $stringbody['bodytext']);
        $validImages = array_values(array_filter($images));

        $element = [
            'header' => $stringbody['header'] ?? '',
            'header_layout' => $stringbody['header_layout'] ?? 0,
            'bodytext' => $bodytext,
            'imageorient' => $imageorient,
        ];

        if ($forceTextpic || !empty($validImages)) {
            $element['CType'] = 'textpic';
            $element['images'] = $validImages;
            // Keep gallery images in FAL; remove broken inline duplicates from bodytext
            $element['bodytext'] = trim(preg_replace('/<img[^>]+>/i', '', $bodytext) ?? $bodytext);
        } else {
            $element['CType'] = 'text';
        }

        return $element;
    }

    /**
     * Remove style/id attributes with double or single quotes (safe on large HTML).
     *
     * @param string $html
     * @return string
     */
    protected function stripHtmlAttributes($html)
    {
        if (!is_string($html) || $html === '') {
            return '';
        }
        $html = preg_replace('/\sstyle="[^"]*"/i', '', $html) ?? $html;
        $html = preg_replace("/\sstyle='[^']*'/i", '', $html) ?? $html;
        $html = preg_replace('/\sid="[^"]*"/i', '', $html) ?? $html;
        $html = preg_replace("/\sid='[^']*'/i", '', $html) ?? $html;
        return $html;
    }

    /**
     * Sanitize a single folder/file name segment for FAL/fileadmin.
     *
     * @param string $name
     * @return string
     */
    protected function sanitizeFolderName($name)
    {
        $name = strip_tags((string)$name);
        $name = GeneralUtility::makeInstance(CharsetConverter::class)->specCharsToASCII('utf-8', $name);
        $name = strtolower($name);
        $name = preg_replace('/[^a-z0-9_\-]+/', '_', $name) ?? '';
        $name = preg_replace('/_+/', '_', $name) ?? '';
        $name = trim($name, '_-');
        if (strlen($name) > 180) {
            $name = substr($name, 0, 180);
            $name = rtrim($name, '_-');
        }
        return $name !== '' ? $name : 'google-doc';
    }

    /**
     * Ensure a nested folder path exists in the default storage.
     *
     * @param \TYPO3\CMS\Core\Resource\ResourceStorage $storage
     * @param string $relativePath e.g. ns_googledocs/pages/15-my-doc
     * @return \TYPO3\CMS\Core\Resource\Folder
     */
    protected function ensureStorageFolder($storage, $relativePath)
    {
        $folder = $storage->getRootLevelFolder();
        $parts = array_filter(explode('/', str_replace('\\', '/', (string)$relativePath)), static function ($part) {
            return $part !== '' && $part !== '.' && $part !== '..';
        });
        foreach ($parts as $part) {
            $part = $this->sanitizeFolderName($part);
            if ($folder->hasFolder($part)) {
                $folder = $folder->getSubfolder($part);
            } else {
                $folder = $folder->createFolder($part);
            }
        }
        return $folder;
    }

    /**
    * updateImageInBodytext
    *
    * @return array|null
    */
    public function updateImageInBodytext($content, $findHeader, $images = null)
    {
        $string = [
            'header' => '',
            'header_layout' => 0,
            'bodytext' => '',
        ];
        // create the new FAL records
        $content = preg_replace("/<p[^>]*>(?:\s|&nbsp;)*<\/p>/", '', $content) ?? $content;
        if ($images === null) {
            $images = $this->findImage($content);
        }
        $content = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head><body>' . $content . '</body></html>';
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $dom = new \DOMDocument();
        @$dom->loadHTML($content);
        //Evaluate img tag in HTML
        $xpath = new \DOMXPath($dom);
        $srcs = $xpath->evaluate('//img');
        for ($i = 0; $i < $srcs->length; $i++) {
            $src = $srcs->item($i);
            if (empty($images[$i])) {
                // Keep original remote src if download failed
                continue;
            }
            try {
                $file = $resourceFactory->getFileObject($images[$i]);
                $src->removeAttribute('src');
                $src->setAttribute('src', '/' . ltrim($file->getPublicUrl(), '/'));
            } catch (\Throwable $e) {
                // keep original src
            }
        }
        $bodies = $dom->getElementsByTagName('body');
        if ($bodies->length < 1) {
            $string['bodytext'] = $content;
            return $string;
        }
        $body = $bodies->item(0);
        if ($findHeader) {
            $firstElement = null;
            foreach ($body->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    $firstElement = $child;
                    break;
                }
            }
            if ($firstElement instanceof \DOMElement) {
                $tag = strtolower($firstElement->tagName);
                if (preg_match('/^h([1-6])$/', $tag, $m)) {
                    $string['header_layout'] = (int)$m[1];
                    $string['header'] = $firstElement->textContent;
                    $body->removeChild($firstElement);
                }
            }
        }
        // save html
        $stringbody = $dom->saveHTML($body);
        // body content
        $stringbody = preg_replace('/<body>(.*?)<\/body>/si', '${1}', $stringbody) ?? $stringbody;
        $string['bodytext'] = html_entity_decode($stringbody, ENT_QUOTES, 'UTF-8');
        return $string;
    }

    /**
    * findHeaderInBodytext
    *
    * @return array|null
    */
    public function findHeaderInBodytext($content, $findHeader)
    {
        $string = [
            'header' => '',
            'header_layout' => 0,
            'bodytext' => '',
        ];
        // create the new FAL records
        $content = preg_replace("/<p[^>]*>(?:\s|&nbsp;)*<\/p>/", '', $content) ?? $content;
        $content = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head><body>' . $content . '</body></html>';
        $dom = new \DOMDocument();
        @$dom->loadHTML($content);
        $bodies = $dom->getElementsByTagName('body');
        if ($bodies->length < 1) {
            $string['bodytext'] = $content;
            return $string;
        }
        $body = $bodies->item(0);
        if ($findHeader) {
            $firstElement = null;
            foreach ($body->childNodes as $child) {
                if ($child instanceof \DOMElement) {
                    $firstElement = $child;
                    break;
                }
            }
            if ($firstElement instanceof \DOMElement) {
                $tag = strtolower($firstElement->tagName);
                if (preg_match('/^h([1-6])$/', $tag, $m)) {
                    $string['header_layout'] = (int)$m[1];
                    $string['header'] = $firstElement->textContent;
                    $body->removeChild($firstElement);
                }
            }
        }
        // save html
        $stringbody = $dom->saveHTML($body);
        // body content
        $stringbody = preg_replace('/<body>(.*?)<\/body>/si', '${1}', $stringbody) ?? $stringbody;
        $string['bodytext'] = html_entity_decode($stringbody, ENT_QUOTES, 'UTF-8');
        return $string;
    }

    /**
     * action findDocsType
     *
     * @return void
     */
    public function findImageMimeType($docType)
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
        return $filesType[$docType];
    }

    /**
     * action findDocsType
     *
     * @return void
     */
    public function findDocsType($docType)
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
        return $filesType[$docType];
    }

    /**
     * action globalSettings
     *
     * @return void
     */
    public function globalSettingsAction()
    {
        $bootstrapVariable = 'data';
        if (version_compare(TYPO3_branch, '11.0', '>')) {
            $bootstrapVariable = 'data-bs';
        }
        $assign = [
            'globalSettings' => $this->globalSettings,
            'activePage' => $this->actionMethodName,
            'googleDocsSetupWizard' => $this->apiContent['googleDocsSetupWizard'],
            'bootstrapVariable' => $bootstrapVariable
        ];
        $this->view->assignMultiple($assign);
    }

    /**
     * action premium
     *
     * @return void
     */
    public function premiumAction()
    {
        $assign = [
            'premiumExtensionData' => $this->apiContent['premiumExtensionData'],
            'globalSettings' => $this->globalSettings,
            'activePage' => $this->actionMethodName,
            'googleDocsSetupWizard' => $this->apiContent['googleDocsSetupWizard'],
        ];
        $this->view->assignMultiple($assign);
    }

    /**
     * action update
     *
     * @return void
     */
    public function updateAction()
    {
        // assign request data to variable
        $request = GeneralUtility::_GP('tx_nsgoogledocs_nitsan_nsgoogledocsgoogledocs');
        if ($request) {
            // update user configuration
            $result = $this->userInfoRepository->updateDocsConfig($this->globalSettings['clientInfo']['uid'], $request);
            // status return in JSON format
            if ($result) {
                $arr['status'] = true;
                echo json_encode($arr);
            } else {
                $arr['status'] = false;
                echo json_encode($arr);
            }
            die;
        } else {
            // status return in JSON format
            $arr['status'] = false;
            echo json_encode($arr);
            die;
        }
    }
}
