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
use TYPO3\CMS\Extbase\Annotation\Inject as inject;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility as transalte;
use TYPO3\CMS\Core\Resource\ResourceFactory;

if(isset($_SESSION)){
    session_start();
}
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
     * @inject
     */
    protected $userInfoRepository = null;
    protected $userData = null;
    protected $client = null;
    protected $folder_id = null;
    protected $service = null;
    protected $adapter = null;
    protected $globalSettings = null;
    protected $apiContent = null;

    /**
     * @var \TYPO3\CMS\Core\Resource\ResourceFactory
     * @inject
     */
    protected $resourceFactory;

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
                $msgException = false;
                $this->globalSettings['clientException'] = false;
            } catch (\Exception $e) {
                $this->globalSettings['clientException'] = transalte::translate('service.exception', 'ns_googledocs');
            }
            // clientStatus and info updated
            $this->globalSettings['clientStatus'] = true;
            $this->globalSettings['clientInfo']['client_secret'] = $this->userData['client_secret'];
            $this->globalSettings['clientInfo']['client_id'] = $this->userData['client_id'];
            $this->globalSettings['clientInfo']['refresh_token'] = $this->userData['refresh_token'];
            $this->globalSettings['clientInfo']['google_files_request'] = $this->userData['google_files_request'];
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
                    $this->globalSettings['docsImportType'] = transalte::translate('reports_import_type.1', 'ns_googledocs');
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
        $pageID = $this->pageUid;
        $request = GeneralUtility::_GP('tx_nsgoogledocs_nitsan_nsgoogledocsgoogledocs');
        $googleDocsFolderName = $request['googleDocsName'];
        // Convert to lowercase + remove tags
        $googleDocsFolderName = mb_strtolower($googleDocsFolderName, 'utf-8');
        $googleDocsFolderName = strip_tags($googleDocsFolderName);
        // Convert extended letters to ascii equivalents
        // The specCharsToASCII() converts "€" to "EUR"
        $googleDocsFolderName = GeneralUtility::makeInstance(CharsetConverter::class)->specCharsToASCII('utf-8', $googleDocsFolderName);
        $googleDocsFolderName = preg_replace('/\s+/', '_', $googleDocsFolderName);
        $googleDocsFolderName = $pageID . '-' . str_replace('/', '-', $googleDocsFolderName);
        $docFolder = 'ns_googledocs/';

        // current page doktype
        $elementType = 1;
        $docFolder .= 'pages/' . $googleDocsFolderName . '/';
        // Update the sys_files table
        $this->globalSettings['googleDocsFolderName'] = $docFolder;

        // fetch file content
        $response = $this->service->files->export($request['googleDocsID'], 'text/html');
        // fetch body from html
        $html = (string) $response->getBody();
        // convert strong tag
        $html = preg_replace('/<span(.*?)font-weight:700(.*?)>(.*?)<\/span>/si', '<span${1}${2}><b>${3}</b></span>', $html);
        // convert italic tag
        $html = preg_replace('/<span(.*?)font-style:italic(.*?)>(.*?)<\/span>/si', '<span${1}${2}><i>${3}</i></span>', $html);
        // convert underline tag
        $html = preg_replace('/<span(.*?)text-decoration:underline(.*?)>(.*?)<\/span>/si', '<span${1}${2}><u>${3}</u></span>', $html);
        // remove style from tags
        $html = preg_replace('/(<[^>]+) style=".*?"/i', '$1', $html);
        // remove id from tags
        $html = preg_replace('/(<[^>]+) id=".*?"/i', '$1', $html);
        // remove body tag
        $html = preg_replace('/(.*?)<body>(.*?)<\/body>(.*?)/si', '$2', $html);

        $stringbody = str_replace('</html>', '', $html);
        $stringbody = html_entity_decode($stringbody, ENT_QUOTES, 'UTF-8');
        if (!is_null($pageID) && $pageID !== '') {
            // Page type configuration
            $result = true;
            // Create elements array using Google Docs content
            $elements = $this->findAndReplace($stringbody);
            foreach ($elements as $key => $element) {
                // Insert page record
                $result = $this->userInfoRepository->insertPageElement($pageID, $element, $request);
                if (!$result) {
                    // return status of action
                    $arr['status'] = false;
                    echo json_encode($arr);
                    die;
                }
            }
        }
        // return status of action
        if ($result) {
            $arr['status'] = true;
            $arr['affectedRows'] = $affectedRows;
            echo json_encode($arr);
            die;
        } else {
            $arr['status'] = false;
            echo json_encode($arr);
            die;
        }
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
                        // replace the image url google docs to local server
                        $stringbody = $this->updateImageInBodytext($elm[1], 1);
                        $finalElements[$key]['CType'] = 'text';
                        $finalElements[$key]['header'] = $stringbody['header'];
                        $finalElements[$key]['header_layout'] = $stringbody['header_layout'];
                        $finalElements[$key]['bodytext'] = trim(preg_replace('/\>\s+\</m', '><', $stringbody['bodytext']));
                        break;
                    default:
                        $finalElements[$key]['CType'] = 'textpic';
                        $finalElements[$key]['images'] = $this->findImage($elm[1]);
                        $finalElements[$key]['imageorient'] = (int) str_replace('textpic-', '', $elm[0]);
                        $bodytext = preg_replace('/<img[^>]+>/i', '', $elm[1]);
                        $stringbody = $this->findHeaderInBodytext($bodytext, 1);
                        $finalElements[$key]['header'] = $stringbody['header'];
                        $finalElements[$key]['header_layout'] = $stringbody['header_layout'];
                        $finalElements[$key]['bodytext'] = preg_replace('/<img[^>]+>/i', '', $stringbody['bodytext']);
                        break;
                }
                //Element sorting order
                $finalElements[$key]['sorting'] = $sorting;
                $i++;
            } else {
                if (strlen($elements[$key]) > 2) {
                    // replace the image url google docs to local server
                    $stringbody = $this->updateImageInBodytext($elm[0], 1);
                    $sorting = ($i * 256) + $elementSorting;
                    $finalElements[$key]['CType'] = 'text';
                    $finalElements[$key]['header'] = $stringbody['header'];
                    $finalElements[$key]['header_layout'] = $stringbody['header_layout'];
                    $finalElements[$key]['bodytext'] = trim(preg_replace('/\>\s+\</m', '><', $stringbody['bodytext']));
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
        $storageFolder = $storage->getRootLevelFolder();
        if (!file_exists($this->globalSettings['siteRoot'] . 'fileadmin/')) {
            // create new folder in fileadmin
            GeneralUtility::mkdir_deep($this->globalSettings['siteRoot'] . 'fileadmin/');
        }
        // check the ns_googledocs folder exist in fileadmin
        if (!file_exists($this->globalSettings['siteRoot'] . 'fileadmin/' . $this->globalSettings['googleDocsFolderName'])) {
            // create new folder in fileadmin
            $storageFolder->createFolder($this->globalSettings['googleDocsFolderName']);
        }
        // tmp folder for image download
        $folder = $this->globalSettings['siteRoot'] . 'typo3temp/assets/images/ns_googledocs/';
        $fileName = $folder . 'ns_googledocs_' . rand(10, 1000000);
        $storageFolder = $storageFolder->getSubfolder($this->globalSettings['googleDocsFolderName']);
        // find the image src
        $doc = new \DOMDocument();
        @$doc->loadHTML($content);
        $xml = simplexml_import_dom($doc);
        $images = $xml->xpath('//img');
        foreach ($images as $img) {
            $fileName = $folder . 'ns_googledocs_' . rand(10, 1000000);
            if (!file_exists($folder)) {
                // create folder in tmp
                GeneralUtility::mkdir_deep($folder);
                // copy file from Google docs
                copy($img['src'], $fileName);
            } else {
                copy($img['src'], $fileName);
            }
            $exif = getimagesize($fileName);
            // get file Mine type
            $imageMineType = $this->findImageMimeType($exif['mime']);
            // Insert in record in sys_file`
            $newFile = $storage->addFile(
                $fileName,
                $storageFolder,
                'ns_googledocs_' . rand(10, 1000000) . '.' . $imageMineType
            );
            $imagesUids[] = $newFile->getUid();
        }
        return $imagesUids;
    }

    /**
    * updateImageInBodytext
    *
    * @return array|null
    */
    public function updateImageInBodytext($content, $findHeader)
    {
        // create the new FAL records
        $content = preg_replace("/<p[^>]*>(?:\s|&nbsp;)*<\/p>/", '', $content);
        $images = $this->findImage($content);
        $content = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head><body>' . $content . '</body></html>';
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);;
        $dom = new \DOMDocument();
        @$dom->loadHTML($content);
        //Evaluate img tag in HTML
        $xpath = new \DOMXPath($dom);
        $srcs = $xpath->evaluate('//img');
        for ($i = 0; $i < $srcs->length; $i++) {
            $src = $srcs->item($i);
            $file = $resourceFactory->getFileObject($images[$i]);
            //remove and set src attribute
            $src->removeAttribute('src');
            $src->setAttribute('src', '/' . $file->getPublicUrl());
        }
        $bodies = $dom->getElementsByTagName('body');
        assert($bodies->length === 1);
        $body = $bodies->item(0);
        for ($i = 0; $i < $body->children->length; $i++) {
            $body->remove($body->children->item($i));
        }
        if ($findHeader) {
            // find heaer from bodytext
            switch ($body->childNodes->item(0)->tagName) {
                case 'h1':
                    // Header layout
                    $string['header_layout'] = 1;
                    // Header content
                    $string['header'] = $body->childNodes->item(0)->textContent;
                    $body->removeChild($body->childNodes->item(0));
                    break;
                case 'h2':
                    $string['header_layout'] = 2;
                    $string['header'] = $body->childNodes->item(0)->textContent;
                    $body->removeChild($body->childNodes->item(0));
                    break;
                case 'h3':
                    $string['header_layout'] = 3;
                    $string['header'] = $body->childNodes->item(0)->textContent;
                    $body->removeChild($body->childNodes->item(0));
                    break;
                case 'h4':
                    $string['header_layout'] = 4;
                    $string['header'] = $body->childNodes->item(0)->textContent;
                    $body->removeChild($body->childNodes->item(0));
                    break;
                case 'h5':
                    $string['header_layout'] = 5;
                    $string['header'] = $body->childNodes->item(0)->textContent;
                    $body->removeChild($body->childNodes->item(0));
                    break;
                case 'h6':
                    $string['header_layout'] = 6;
                    $string['header'] = $body->childNodes->item(0)->textContent;
                    $body->removeChild($body->childNodes->item(0));
                    break;
            }
        }
        // save html
        $stringbody = $dom->saveHTML($body);
        // body content
        $stringbody = preg_replace('/<body>(.*?)<\/body>/si', '${1}', $stringbody);
        // $stringbody = str_replace('Â', '', $stringbody);
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
        // create the new FAL records
        $content = preg_replace("/<p[^>]*>(?:\s|&nbsp;)*<\/p>/", '', $content);
        $content = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head><body>' . $content . '</body></html>';
        $dom = new \DOMDocument();
        @$dom->loadHTML($content);
        $bodies = $dom->getElementsByTagName('body');
        assert($bodies->length === 1);
        $body = $bodies->item(0);
        for ($i = 0; $i < $body->children->length; $i++) {
            $body->remove($body->children->item($i));
        }
        if ($findHeader) {
            switch ($body->childNodes->item(0)->tagName) {
                case 'h1':
                    $string['header_layout'] = 1;
                    $string['header'] = $body->childNodes->item(0)->textContent;
                    $body->removeChild($body->childNodes->item(0));
                    break;
                case 'h2':
                    $string['header_layout'] = 2;
                    $string['header'] = $body->childNodes->item(0)->textContent;
                    $body->removeChild($body->childNodes->item(0));
                    break;
                case 'h3':
                    $string['header_layout'] = 3;
                    $string['header'] = $body->childNodes->item(0)->textContent;
                    $body->removeChild($body->childNodes->item(0));
                    break;
                case 'h4':
                    $string['header_layout'] = 4;
                    $string['header'] = $body->childNodes->item(0)->textContent;
                    $body->removeChild($body->childNodes->item(0));
                    break;
                case 'h5':
                    $string['header_layout'] = 5;
                    $string['header'] = $body->childNodes->item(0)->textContent;
                    $body->removeChild($body->childNodes->item(0));
                    break;
                case 'h6':
                    $string['header_layout'] = 6;
                    $string['header'] = $body->childNodes->item(0)->textContent;
                    $body->removeChild($body->childNodes->item(0));
                    break;
            }
        }
        // save html
        $stringbody = $dom->saveHTML($body);
        // body content
        $stringbody = preg_replace('/<body>(.*?)<\/body>/si', '${1}', $stringbody);
        // $stringbody = str_replace('Â', '', $stringbody);
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
