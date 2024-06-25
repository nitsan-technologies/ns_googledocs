<?php

namespace NITSAN\NsGoogledocs\Controller;

/***
 *
 * This file is part of the "GoogleDocs" Extension for TYPO3 CMS.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 *  (c) 2020
 *
 ***/

use TYPO3\CMS\Core\Core\Environment;
use Psr\Http\Message\ResponseInterface;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Charset\CharsetConverter;
use TYPO3\CMS\Core\Information\Typo3Version;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Backend\Template\ModuleTemplate;
use TYPO3\CMS\Backend\Template\ModuleTemplateFactory;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;
use NITSAN\NsGoogledocs\Domain\Repository\UserInfoRepository;
use TYPO3\CMS\Extbase\Utility\LocalizationUtility as transalte;
use TYPO3\CMS\Backend\Utility\BackendUtility as BackendUtilityCore;

if (isset($_SESSION)) {
    session_start();
}

if (Environment::isComposerMode()) {
    if ((GeneralUtility::makeInstance(Typo3Version::class))->getMajorVersion() == 11) {
        require_once Environment::getPublicPath() . '/typo3conf/ext/ns_googledocs/Classes/google-api-php-client-v11/vendor/autoload.php';
    } else {
        require_once Environment::getProjectPath() . '/vendor/nitsan/ns-googledocs/Classes/google-api-php-client/vendor/autoload.php';
    }
} else {
    if ((GeneralUtility::makeInstance(Typo3Version::class))->getMajorVersion() == 11) {
        require_once Environment::getPublicPath() . '/typo3conf/ext/ns_googledocs/Classes/google-api-php-client-v11/vendor/autoload.php';
    } else {
        require_once Environment::getPublicPath() . '/typo3conf/ext/ns_googledocs/Classes/google-api-php-client/vendor/autoload.php';
    }
}

/**
 * UserInfoController
 */
class UserInfoController extends ActionController
{
    /**
     * userInfoRepository
     *
     * @var UserInfoRepository
     */
    protected $userInfoRepository = null;
    protected $userData = null;
    protected $client = null;
    protected $folder_id = null;
    protected $service = null;
    protected $adapter = null;
    protected $globalSettings = null;
    protected $pageUid = null;
    protected $pageInformation = null;
    protected $currentVersion = null;

    /**
     * @var ResourceFactory
     */
    protected $resourceFactory;

    /**
     * @param UserInfoRepository $userInfoRepository
     * @param ModuleTemplateFactory $moduleTemplateFactory
     */
    public function __construct(
        UserInfoRepository                       $userInfoRepository,
        protected readonly ModuleTemplateFactory $moduleTemplateFactory,
    ) {
        $this->userInfoRepository = $userInfoRepository;
    }

    /**
     * Initialize Action
     *
     */
    public function initializeAction()
    {
        $importTypeInfo =[];
        $this->currentVersion = (GeneralUtility::makeInstance(Typo3Version::class));
        // Find current user info
        $this->globalSettings['siteRoot'] = Environment::getPublicPath() . '/';
        $this->userData = $GLOBALS['BE_USER']->user;
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
            $this->globalSettings['clientInfo']['google_files_request'] = 10;
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
        $this->pageUid = $this->request->getQueryParams()['id'] ?? 0;
        $this->pageInformation = BackendUtilityCore::readPageAccess($this->pageUid, '');
        parent::initializeAction();
    }

    /**
     * action dashboard
     *
     * @return ResponseInterface
     */
    public function dashboardAction(): ResponseInterface
    {
        $bootstrapVariable = 'data-bs';
        // View assigned
        $assign = [
            'globalSettings' => $this->globalSettings,
            'activePage' => $this->actionMethodName,
            'pageInformation' => $this->pageInformation,
            'bootstrapVariable' => $bootstrapVariable
        ];
        $this->view->assignMultiple($assign);
        if ($this->currentVersion->getMajorVersion() == 11) {
            $assign['version'] = 11;
            $this->view->assignMultiple($assign);
            return $this->htmlResponse();
        } else {
            $assign['version'] = 12;
            $view = $this->initializeModuleTemplate($this->request);
            $view->assignMultiple($assign);
            return $view->renderResponse();
        }
    }

    /**
     * action import
     *
     * @return ResponseInterface
     */
    public function importAction(): ResponseInterface
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
                    if ($this->currentVersion->getMajorVersion() == 11) {
                        $pageColPos[$key]['label'] = $backendLayout[0];
                        $pageColPos[$key]['colPos'] = $backendLayout[1];
                    } else {
                        $pageColPos[$key]['label'] = $backendLayout['label'];
                        $pageColPos[$key]['colPos'] = $backendLayout['value'];
                    }
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
                    'pageSize' => 100,
                    'fields' => 'files(id, name, createdTime, modifiedTime, webViewLink, owners, mimeType, exportLinks)',
                    'q' => '\'me\' in owners and mimeType=\'application/vnd.google-apps.document\''
                ];
                try {
                    // request for files list
                    if ($this->service) {
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
                    }
                    $msgException = false;
                } catch (\Google_Service_Exception $e) {
                    $msgException = transalte::translate('listFiles.exception', 'ns_googledocs');
                }
            }
            $bootstrapVariable = 'data-bs';
            // View assigned
            $assign = [
                'pageColPos' => $pageColPos ?? '',
                'docsFiels' => $docsFiels ?? '',
                'activePage' => $this->actionMethodName,
                'globalSettings' => $this->globalSettings,
                'pageStatus' => true,
                'msgException' => $msgException ?? '',
                'bootstrapVariable' => $bootstrapVariable
            ];
        }
        if ($this->currentVersion->getMajorVersion() == 11) {
            $assign['version'] = 11;
            $this->view->assignMultiple($assign);
            return $this->htmlResponse();
        } else {
            $assign['version'] = 12;
            $view = $this->initializeModuleTemplate($this->request);
            $view->assignMultiple($assign);
            return $view->renderResponse();
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
        $request = $this->request->getArguments();
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
        $html = (string)$response->getBody();
        $html = preg_replace('#<head>(.*?)</head>#', '', $html);
        // $html = preg_replace('#<meta content="text/html; charset=UTF-8" http-equiv="content-type">(.*?)</style>#', '', $html);
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
            $arr['affectedRows'] = $affectedRows ?? '';
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
     * @return array
     */
    public function findAndReplace($body)
    {
        // Find current page sorting field value
        $elementSorting = $this->userInfoRepository->findElementSorting($this->pageInformation['uid']);

        $body = preg_replace('/<a href="(.*?)q=(.*?)&sa(.*?)">/si', '<a href="$2">', $body);

        // Define a pattern to match everything before the first <h1> or <h2> tag
        $pattern = '/^(.*?)(<h[12]>.*)$/is';

        // Check if the pattern matches
        if (preg_match($pattern, $body, $matches)) {
            // Return the content starting from the first <h1> or <h2> tag
            $body = $matches[2];
        }

        // remove span tag
        $body = str_replace('<span>', '', $body);
        $body = str_replace('</span>', '', $body);
        // remove blank p
        $body = str_replace('<p></p>', '', $body);

        // Regular expression to match empty tags
        $pattern = '/<(\w+)\b[^>]*>\s*<\/\1>/';
        // Apply the regex until no more empty tags are found
        do {
            $previous_html = $body;
            $body = preg_replace($pattern, '', $body);
        } while ($body !== $previous_html);

        $body = preg_replace('/(<h1>)/', '|newElement|text:newElement:' . '$1', $body);
        $body = preg_replace('/(<h2>)/', '|newElement|text:newElement:' . '$1', $body);

        // Explode all the elements
        $elements = explode('|newElement|', $body);
        $finalElements = [];
        $i = 1;
        foreach ($elements as $key => $value) {
            // Explode key and content
            $elm = explode(':newElement:', $value);
            if (count($elm) > 1) {
                $sorting = ($i * 256) + $elementSorting;
                // configure element array with CType
                $elm[1] = preg_replace('/\>\s+\</m', '><', $elm[1]);
                // optimize HTML for header
                $stringbody = $this->updateBodytext($elm[1]);
                $finalElements[$key]['CType'] = 'text';
                $finalElements[$key]['header'] = $stringbody['header'] ?? '';
                $finalElements[$key]['header_layout'] = $stringbody['header_layout'] ?? '';
                $finalElements[$key]['bodytext'] = trim(preg_replace('/\>\s+\</m', '><', $stringbody['bodytext'])) ?? '';
                //Element sorting order
                $finalElements[$key]['sorting'] = $sorting;
                $i++;
            } else {
                if (strlen($elements[$key]) > 2) {
                    // replace the image url Google Docs to local server
                    $stringbody = $this->updateBodytext($elm[0], 1);
                    $sorting = ($i * 256) + $elementSorting;
                    $finalElements[$key]['CType'] = 'text';
                    $finalElements[$key]['header'] = $stringbody['header'] ?? '';
                    $finalElements[$key]['header_layout'] = $stringbody['header_layout'] ?? '';
                    $finalElements[$key]['bodytext'] = trim(preg_replace('/\>\s+\</m', '><', $stringbody['bodytext'])) ?? '';
                    $finalElements[$key]['sorting'] = $sorting ?? '';
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
     * updateBodytext
     *
     * @return array|null
     */
    public function updateBodytext($content)
    {
        // create the new FAL records
        $content = preg_replace("/<p[^>]*>(?:\s|&nbsp;)*<\/p>/", '', $content);
        $content = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /></head><body>' . $content . '</body></html>';
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        ;
        $dom = new \DOMDocument();
        @$dom->loadHTML($content);
        //Evaluate img tag in HTML
        $xpath = new \DOMXPath($dom);
        $bodies = $dom->getElementsByTagName('body');
        assert($bodies->length === 1);
        $body = $bodies->item(0);
        if (isset($body->children)) {
            for ($i = 0; $i < $body->children->length; $i++) {
                $body->remove($body->children->item($i));
            }
        }

        // find header from body text
        if (isset($body->childNodes->item(0)->tagName)) {
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

        $pattern = '/<img[^>]*>/i';
        return preg_replace($pattern, '', $string);
    }

    /**
     * action findDocsType
     *
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
     * @return ResponseInterface
     */
    public function globalSettingsAction(): ResponseInterface
    {
        $bootstrapVariable = 'data-bs';
        $assign = [
            'globalSettings' => $this->globalSettings,
            'activePage' => $this->actionMethodName,
            'bootstrapVariable' => $bootstrapVariable
        ];
        if ($this->currentVersion->getMajorVersion() == 11) {
            $assign['version'] = 11;
            $this->view->assignMultiple($assign);
            return $this->htmlResponse();
        } else {
            $assign['version'] = 12;
            $view = $this->initializeModuleTemplate($this->request);
            $view->assignMultiple($assign);
            return $view->renderResponse();
        }
    }

    /**
     * action update
     *
     * @return void
     */
    public function updateAction()
    {
        // assign request data to variable
        $request = $this->request->getParsedBody();
        if ($this->currentVersion->getMajorVersion() == 11) {
            $request = $request['tx_nsgoogledocs_nitsan_nsgoogledocsgoogledocs'];
        }
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

    /**
     * Generates the action menu
     */
    protected function initializeModuleTemplate(
        ServerRequestInterface $request
    ): ModuleTemplate {
        return $this->moduleTemplateFactory->create($request);
    }
}
