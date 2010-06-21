<?php

/**
 * Digital Asset Management
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      COMVATION Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  module_downloads
 * @version     1.0.0
 */

/**
 * @ignore
 */
require_once dirname(__FILE__).'/lib/downloadsLib.class.php';
require_once ASCMS_LIBRARY_PATH.'/FRAMEWORK/Validator.class.php';
require_once(ASCMS_FRAMEWORK_PATH.DIRECTORY_SEPARATOR.'Image.class.php');

/**
* Digital Asset Management Frontend
* @copyright    CONTREXX CMS - COMVATION AG
* @author       COMVATION Development Team <info@comvation.com>
* @package      contrexx
* @subpackage   module_downloads
* @version      1.0.0
*/
class downloads extends DownloadsLibrary
{
    private $htmlLinkTemplate = '<a href="%s" title="%s">%s</a>';
    private $htmlImgTemplate = '<img src="%s" alt="%s" />';
    private $moduleParamsHtml = '?section=downloads';
    private $moduleParamsJs = '?section=downloads';
    private $userId;
    private $categoryId;
    private $cmd = '';
    private $XMLHttpRequest = null;
    private $pageTitle;
    /**
     * @var HTML_Template_Sigma
     */
    private $objTemplate;
    /**
     * Contains the info messages about done operations
     * @var array
     * @access private
     */
    private $arrStatusMsg = array('ok' => array(), 'error' => array());


    /**
    * Constructor
    *
    * Calls the parent constructor and creates a local template object
    */
    function __construct($strPageContent)
    {
        parent::__construct();

        $objFWUser = FWUser::getFWUserObject();
        $this->userId = $objFWUser->objUser->login() ? $objFWUser->objUser->getId() : 0;
        $this->parseURLModifiers();

        if (!$this->XMLHttpRequest) {
            $this->objTemplate = new HTML_Template_Sigma('.');
            CSRF::add_placeholder($this->objTemplate);
            $this->objTemplate->setErrorHandling(PEAR_ERROR_DIE);
            $this->objTemplate->setTemplate($strPageContent);
        }
    }


    private function parseURLModifiers()
    {
        $cmd = isset($_REQUEST['cmd']) ? $_REQUEST['cmd'] : '';

        if (isset($_GET['download'])) {
            $this->cmd = 'download_file';
        } elseif (isset($_GET['delete_file'])) {
            $this->cmd = 'delete_file';
        } elseif (isset($_GET['delete_category'])) {
            $this->cmd = 'delete_category';
        } elseif ($cmd) {
            $this->cmd = $cmd;
        }

        if ($cmd) {
            $this->moduleParamsHtml .= '&amp;cmd='.htmlentities($cmd, ENT_QUOTES, CONTREXX_CHARSET);
            $this->moduleParamsJs .= '&cmd='.htmlspecialchars($cmd, ENT_QUOTES, CONTREXX_CHARSET);
        }

        if (intval($cmd)) {
            $this->categoryId = !empty($_REQUEST['category']) ? intval($_REQUEST['category']) : intval($cmd);
        } else {
            $this->categoryId = !empty($_REQUEST['category']) ? intval($_REQUEST['category']) : 0;
        }

        if (!empty($_GET['js'])) {
            $this->XMLHttpRequest = $_GET['js'];
        }
    }


    /**
    * Reads $this->cmd and selects (depending on the value) an action
    *
    */
    public function getPage()
    {
        if ($this->XMLHttpRequest) {
            switch($this->XMLHttpRequest) {
                case 'downloads':
                    print json_encode($this->getDownloadList());
                    exit;
                    break;

                default:
                    exit;
                    break;
            }
        }

        CSRF::add_code();
        switch ($this->cmd) {
            case 'download_file':
                $this->download();
                exit;
                break;

            case 'delete_file':
                $this->deleteDownload();
                $this->overview();
                break;

            case 'delete_category':
                $this->deleteCategory();
                $this->overview();
                break;

            default:
                $this->overview();
                break;
        }

        $this->parseMessages();

        return $this->objTemplate->get();
    }


    private function parseMessages()
    {
        $this->objTemplate->setVariable(array(
            'DOWNLOADS_MSG_OK'      => count($this->arrStatusMsg['ok']) ? implode('<br />', $this->arrStatusMsg['ok']) : '',
            'DOWNLOADS_MSG_ERROR'   => count($this->arrStatusMsg['error']) ? implode('<br />', $this->arrStatusMsg['error']) : ''
        ));
    }


    private function deleteDownload()
    {
        global $_LANGID, $_ARRAYLANG;

        CSRF::check_code();
        $objDownload = new Download();
        $objDownload->load(isset($_GET['delete_file']) ? $_GET['delete_file'] : 0);

        if (!$objDownload->EOF) {
            $name = '<strong>'.htmlentities($objDownload->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET).'</strong>';
            if ($objDownload->delete($this->categoryId)) {
                $this->arrStatusMsg['ok'][] = sprintf($_ARRAYLANG['TXT_DOWNLOADS_DOWNLOAD_DELETE_SUCCESS'], $name);
            } else {
                $this->arrStatusMsg['error'] = array_merge($this->arrStatusMsg['error'], $objDownload->getErrorMsg());
            }
        }
    }


    private function deleteCategory()
    {
        global $_LANGID, $_ARRAYLANG;

        CSRF::check_code();
        $objCategory = Category::getCategory(isset($_GET['delete_category']) ? $_GET['delete_category'] : 0);

        if (!$objCategory->EOF) {
            $name = '<strong>'.htmlentities($objCategory->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET).'</strong>';
            if ($objCategory->delete()) {
                $this->arrStatusMsg['ok'][] = sprintf($_ARRAYLANG['TXT_DOWNLOADS_CATEGORY_DELETE_SUCCESS'], $name);
            } else {
                $this->arrStatusMsg['error'] = array_merge($this->arrStatusMsg['error'], $objCategory->getErrorMsg());
            }
        }
    }


    private function overview()
    {
        global $_LANGID;



        $objDownload = new Download();
        $objCategory = Category::getCategory($this->categoryId);

        if ($objCategory->getId()) {
            // check access permissions to selected category
            if (!Permission::checkAccess(142, 'static', true)
                && $objCategory->getReadAccessId()
                && !Permission::checkAccess($objCategory->getReadAccessId(), 'dynamic', true)
                && $objCategory->getOwnerId() != $this->userId
            ) {
                Permission::noAccess(base64_encode(CONTREXX_SCRIPT_PATH.$this->moduleParamsJs.'&category='.$objCategory->getId()));
            }


            // parse crumbtrail
            $this->parseCrumbtrail($objCategory);

            if ($objDownload->load(!empty($_REQUEST['id']) ? intval($_REQUEST['id']) : 0)
                && (!$objDownload->getExpirationDate() || $objDownload->getExpirationDate() > time())
            ) {
                /* DOWNLOAD DETAIL PAGE */
                $this->pageTitle = htmlentities($objDownload->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET);

                $this->parseRelatedCategories($objDownload);
                $this->parseRelatedDownloads($objDownload, $objCategory->getId());

                $this->parseDownload($objDownload, $objCategory->getId());

                // hide unwanted blocks on the detail page
                if ($this->objTemplate->blockExists('downloads_category')) {
                    $this->objTemplate->hideBlock('downloads_category');
                }
                if ($this->objTemplate->blockExists('downloads_subcategory_list')) {
                    $this->objTemplate->hideBlock('downloads_subcategory_list');
                }
                if ($this->objTemplate->blockExists('downloads_file_list')) {
                    $this->objTemplate->hideBlock('downloads_file_list');
                }
                if ($this->objTemplate->blockExists('downloads_simple_file_upload')) {
                    $this->objTemplate->hideBlock('downloads_simple_file_upload');
                }
                if ($this->objTemplate->blockExists('downloads_advanced_file_upload')) {
                    $this->objTemplate->hideBlock('downloads_advanced_file_upload');
                }
            } else {
                /* CATEGORY DETAIL PAGE */
                $this->pageTitle = htmlentities($objCategory->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET);

                // process upload
                $this->processUpload($objCategory);

                // process create directory
                $this->processCreateDirectory($objCategory);

                // parse selected category
                $this->parseCategory($objCategory);

                // parse subcategories
                $this->parseCategories($objCategory, array('downloads_subcategory_list', 'downloads_subcategory'), null, 'SUB');

                // parse downloads of selected category
                $this->parseDownloads($objCategory);

                // parse upload form
                $this->parseUploadForm($objCategory);

                // parse create directory form
                $this->parseCreateCategoryForm($objCategory);

                // hide unwanted blocks on the category page
                if ($this->objTemplate->blockExists('downloads_download')) {
                    $this->objTemplate->hideBlock('downloads_download');
                }
                if ($this->objTemplate->blockExists('downloads_file_detail')) {
                    $this->objTemplate->hideBlock('downloads_file_detail');
                }
            }

            // hide unwanted blocks on the category/detail page
            if ($this->objTemplate->blockExists('downloads_overview')) {
                $this->objTemplate->hideBlock('downloads_overview');
            }
            if ($this->objTemplate->blockExists('downloads_most_viewed_file_list')) {
                $this->objTemplate->hideBlock('downloads_most_viewed_file_list');
            }
            if ($this->objTemplate->blockExists('downloads_most_downloaded_file_list')) {
                $this->objTemplate->hideBlock('downloads_most_downloaded_file_list');
            }
            if ($this->objTemplate->blockExists('downloads_most_popular_file_list')) {
                $this->objTemplate->hideBlock('downloads_most_popular_file_list');
            }
            if ($this->objTemplate->blockExists('downloads_newest_file_list')) {
                $this->objTemplate->hideBlock('downloads_newest_file_list');
            }
            if ($this->objTemplate->blockExists('downloads_updated_file_list')) {
                $this->objTemplate->hideBlock('downloads_updated_file_list');
            }
        } else {
            /* CATEGORY OVERVIEW PAGE */
            $this->parseCategories($objCategory, array('downloads_overview', 'downloads_overview_category'), null, null, 'downloads_overview_row', array('downloads_overview_subcategory_list', 'downloads_overview_subcategory'), $this->arrConfig['overview_max_subcats']);

            if (!empty($this->searchKeyword)) {
                $this->parseDownloads($objCategory);
            } else {
                if ($this->objTemplate->blockExists('downloads_file_list')) {
                    $this->objTemplate->hideBlock('downloads_file_list');
                }
            }

            /* PARSE NESTED CATEGORY LIST */
            $this->parseNestedCategoryList();

            /* PARSE MOST VIEWED DOWNLOADS */
            $this->parseSpecialDownloads(array('downloads_most_viewed_file_list', 'downloads_most_viewed_file'), array('is_active' => true, 'expiration' => array('=' => 0, '>' => time())) /* this filters purpose is only that the method Download::getFilteredIdList() gets processed */, array('views' => 'desc'), $this->arrConfig['most_viewed_file_count']);

            /* PARSE MOST DOWNLOADED DOWNLOADS */
            $this->parseSpecialDownloads(array('downloads_most_downloaded_file_list', 'downloads_most_downloaded_file'), array('is_active' => true, 'expiration' => array('=' => 0, '>' => time())) /* this filters purpose is only that the method Download::getFilteredIdList() gets processed */, array('download_count' => 'desc'), $this->arrConfig['most_downloaded_file_count']);

            /* PARSE MOST POPULAR DOWNLOADS */
            // TODO: Rating system has to be implemented first!
            //$this->parseSpecialDownloads(array('downloads_most_popular_file_list', 'downloads_most_popular_file'), null, array('rating' => 'desc'), $this->arrConfig['most_popular_file_count']);

            /* PARSE RECENTLY UPDATED DOWNLOADS */
            $filter = array(
                'ctime' => array(
                    '>=' => time() - $this->arrConfig['new_file_time_limit']
                ),
                'expiration' => array(
                    '=' => 0,
                    '>' => time()
                )
            );
            $this->parseSpecialDownloads(array('downloads_newest_file_list', 'downloads_newest_file'), $filter, array('ctime' => 'desc'), $this->arrConfig['newest_file_count']);

            // parse recently updated downloads
            $filter = array(
                'mtime' => array(
                    '>=' => time() - $this->arrConfig['updated_file_time_limit']
                ),
                // exclude newest downloads
                'ctime' => array(
                    '<' => time() - $this->arrConfig['new_file_time_limit']
                ),
                'expiration' => array(
                    '=' => 0,
                    '>' => time()
                )
            );
            $this->parseSpecialDownloads(array('downloads_updated_file_list', 'downloads_updated_file'), $filter, array('mtime' => 'desc'), $this->arrConfig['updated_file_count']);


            // hide unwanted blocks on the overview page
            if ($this->objTemplate->blockExists('downloads_category')) {
                $this->objTemplate->hideBlock('downloads_category');
            }
            if ($this->objTemplate->blockExists('downloads_crumbtrail')) {
                $this->objTemplate->hideBlock('downloads_crumbtrail');
            }
            if ($this->objTemplate->blockExists('downloads_subcategory_list')) {
                $this->objTemplate->hideBlock('downloads_subcategory_list');
            }
            if ($this->objTemplate->blockExists('downloads_file_detail')) {
                $this->objTemplate->hideBlock('downloads_file_detail');
            }
            if ($this->objTemplate->blockExists('downloads_simple_file_upload')) {
                $this->objTemplate->hideBlock('downloads_simple_file_upload');
            }
            if ($this->objTemplate->blockExists('downloads_advanced_file_upload')) {
                $this->objTemplate->hideBlock('downloads_advanced_file_upload');
            }
        }

        $this->parseGlobalStuff($objCategory);

    }

    private function processFormUpload(&$fileName, &$fileExtension, &$suffix)
    {
        global $_ARRAYLANG;

        $inputField = 'downloads_upload_file';
        if (!isset($_FILES[$inputField]) || !is_array($_FILES[$inputField])) {
            return false;
        }

        $fileName = !empty($_FILES[$inputField]['name']) ? contrexx_stripslashes($_FILES[$inputField]['name']) : '';
        $fileTmpName = !empty($_FILES[$inputField]['tmp_name']) ? $_FILES[$inputField]['tmp_name'] : '';

        switch ($_FILES[$inputField]['error']) {
            case UPLOAD_ERR_INI_SIZE:
                //Die hochgeladene Datei überschreitet die in der Anweisung upload_max_filesize in php.ini festgelegte Grösse.
                include_once ASCMS_FRAMEWORK_PATH.'/System.class.php';
                $this->arrStatusMsg['error'][] = sprintf($_ARRAYLANG['TXT_DOWNLOADS_FILE_SIZE_EXCEEDS_LIMIT'], htmlentities($fileName, ENT_QUOTES, CONTREXX_CHARSET), FWSystem::getMaxUploadFileSize());
                break;

            case UPLOAD_ERR_FORM_SIZE:
                //Die hochgeladene Datei überschreitet die in dem HTML Formular mittels der Anweisung MAX_FILE_SIZE angegebene maximale Dateigrösse.
                $this->arrStatusMsg['error'][] = sprintf($_ARRAYLANG['TXT_DOWNLOADS_FILE_TOO_LARGE'], htmlentities($fileName, ENT_QUOTES, CONTREXX_CHARSET));
                break;

            case UPLOAD_ERR_PARTIAL:
                //Die Datei wurde nur teilweise hochgeladen.
                $this->arrStatusMsg['error'][] = sprintf($_ARRAYLANG['TXT_DOWNLOADS_FILE_CORRUPT'], htmlentities($fileName, ENT_QUOTES, CONTREXX_CHARSET));
                break;

            case UPLOAD_ERR_NO_FILE:
                //Es wurde keine Datei hochgeladen.
                continue;
                break;

            default:
                if (!empty($fileTmpName)) {
                    $suffix = '';
                    $file = ASCMS_DOWNLOADS_IMAGES_PATH.'/'.$fileName;
                    $arrFile = pathinfo($file);
                    $i = 0;
                    while (file_exists($file)) {
                        $suffix = '-'.++$i;
                        $file = ASCMS_DOWNLOADS_IMAGES_PATH.'/'.$arrFile['filename'].$suffix.'.'.$arrFile['extension'];
                    }

                    if (FWValidator::is_file_ending_harmless($fileName)) {
                        $fileExtension = $arrFile['extension'];

                        if (@move_uploaded_file($fileTmpName, $file)) {
                            $fileName = $arrFile['filename'];

                            if (in_array(strtolower($fileExtension), array('jpg', 'jpeg', 'png', 'gif'))) {
                                ImageManager::_createThumb(ASCMS_DOWNLOADS_IMAGES_PATH, ASCMS_DOWNLOADS_IMAGES_WEB_PATH, $arrFile['filename'].$suffix.'.'.$arrFile['extension']);
                            }

                            return true;
                        } else {
                            $this->arrStatusMsg['error'][] = sprintf($_ARRAYLANG['TXT_DOWNLOADS_FILE_UPLOAD_FAILED'], htmlentities($fileName, ENT_QUOTES, CONTREXX_CHARSET));
                        }
                    } else {
                        $this->arrStatusMsg['error'][] = sprintf($_ARRAYLANG['TXT_DOWNLOADS_FILE_EXTENSION_NOT_ALLOWED'], htmlentities($fileName, ENT_QUOTES, CONTREXX_CHARSET));
                    }
                }
                break;
        }
        return false;
    }


    private function processFileUploaderUpload(&$fileName, &$fileExtension, &$suffix)
    {
        require_once ASCMS_MODULE_PATH.'/fileUploader/lib/FileUploaderLib.class.php';
        $objFileUploader = new FileUploaderLib();
        $objFileUploader->upload(true);
        $fileName = $objFileUploader->getUploadFileName();
        $fileExtension = $objFileUploader->getUploadFileExtension();
        $suffix = $objFileUploader->getUploadFileSuffix();
    }

    private function addDownloadFromUpload($fileName, $fileExtension, $suffix, $objCategory)
    {
        $objDownload = new Download();

        // parse name and description attributres
        $arrLanguageIds = array_keys(FWLanguage::getLanguageArray());

        foreach ($arrLanguageIds as $langId) {
            $arrNames[$langId] = $fileName.'.'.$fileExtension;
            $arrDescriptions[$langId] = '';
        }

        $fileMimeType = null;
        foreach (Download::$arrMimeTypes as $mimeType => $arrMimeType) {
            if (!count($arrMimeType['extensions'])) {
                continue;
            }
            if (in_array(strtolower($fileExtension), $arrMimeType['extensions'])) {
                $fileMimeType = $mimeType;
                break;
            }
        }

        $objDownload->setNames($arrNames);
        $objDownload->setDescriptions($arrDescriptions);
        $objDownload->setType('file');
        $objDownload->setSource(ASCMS_DOWNLOADS_IMAGES_WEB_PATH.'/'.$fileName.$suffix.'.'.$fileExtension, $fileName.'.'.$fileExtension);
        $objDownload->setActiveStatus(true);
        $objDownload->setMimeType($fileMimeType);
        if ($objDownload->getMimeType() == 'image') {
            $objDownload->setImage(ASCMS_DOWNLOADS_IMAGES_WEB_PATH.'/'.$fileName.$suffix.'.'.$fileExtension);
        }
        $this->arrConfig['use_attr_size'] ? $objDownload->setSize(filesize(ASCMS_DOWNLOADS_IMAGES_PATH.'/'.$fileName.$suffix.'.'.$fileExtension)) : null;
        $objDownload->setVisibility(true);
        $objDownload->setProtection(false);
        $objDownload->setGroups(array());
        $objDownload->setCategories(array($objCategory->getId()));
        $objDownload->setDownloads(array());

        if (!$objDownload->store($objCategory)) {
            $this->arrStatusMsg['error'] = array_merge($this->arrStatusMsg['error'], $objDownload->getErrorMsg());
            return false;
        } else {
	        DownloadsLibrary::sendNotificationEmails($objDownload, $objCategory);
			return true;
		}
    }

    private function processUpload($objCategory)
    {
        global $_ARRAYLANG;

        // check for sufficient permissions
        if ($objCategory->getAddFilesAccessId()
            && !Permission::checkAccess($objCategory->getAddFilesAccessId(), 'dynamic', true)
            && $objCategory->getOwnerId() != $this->userId
        ) {
            return;
        }

        $fileName = '';
        $fileExtension = '';
        $suffix = '';
        if (isset($_REQUEST['fileUploader'])) {
            $this->processFileUploaderUpload($fileName, $fileExtension, $suffix, $objCategory);
        } else {
            if (!$this->processFormUpload($fileName, $fileExtension, $suffix, $objCategory)) {
                return;
            }
        }

        $this->addDownloadFromUpload($fileName, $fileExtension, $suffix, $objCategory);
        if (isset($_REQUEST['fileUploader'])) {
            die($fileName.'.'.$fileExtension);
        }
   }


    private function processCreateDirectory($objCategory)
    {
        if (empty($_POST['downloads_category_name'])) {
            return;
        } else {
            $name = contrexx_stripslashes($_POST['downloads_category_name']);
        }

        CSRF::check_code();

        // check for sufficient permissiosn
        if ($objCategory->getAddSubcategoriesAccessId()
            && !Permission::checkAccess($objCategory->getAddSubcategoriesAccessId(), 'dynamic', true)
            && $objCategory->getOwnerId() != $this->userId
        ) {
            return;
        }

        // parse name and description attributres
        $arrLanguageIds = array_keys(FWLanguage::getLanguageArray());


        foreach ($arrLanguageIds as $langId) {
            $arrNames[$langId] = $name;
            $arrDescriptions[$langId] = '';
        }

        $objSubcategory = new Category();
        $objSubcategory->setParentId($objCategory->getId());
        $objSubcategory->setActiveStatus(true);
        $objSubcategory->setVisibility($objCategory->getVisibility());
        $objSubcategory->setNames($arrNames);
        $objSubcategory->setDescriptions($arrDescriptions);
        $objSubcategory->setPermissions(array(
            'read' => array(
                'protected' => (bool) $objCategory->getAddSubcategoriesAccessId(),
                'groups'    => array()
            ),
            'add_subcategories' => array(
                'protected' => (bool) $objCategory->getAddSubcategoriesAccessId(),
                'groups'    => array()
            ),
            'manage_subcategories' => array(
                'protected' => (bool) $objCategory->getAddSubcategoriesAccessId(),
                'groups'    => array()
            ),
            'add_files' => array(
                'protected' => (bool) $objCategory->getAddSubcategoriesAccessId(),
                'groups'    => array()
            ),
            'manage_files' => array(
                'protected' => (bool) $objCategory->getAddSubcategoriesAccessId(),
                'groups'    => array()
            )
        ));

        if (!$objSubcategory->store()) {
            $this->arrStatusMsg['error'] = array_merge($this->arrStatusMsg['error'], $objSubcategory->getErrorMsg());
        }
    }


    private function parseUploadForm($objCategory)
    {
        global $_CONFIG, $_ARRAYLANG;

        if (!$this->objTemplate->blockExists('downloads_simple_file_upload') && !$this->objTemplate->blockExists('downloads_advanced_file_upload')) {
            return;
        }

        // check for upload permissiosn
        if ($objCategory->getAddFilesAccessId()
            && !Permission::checkAccess($objCategory->getAddFilesAccessId(), 'dynamic', true)
            && $objCategory->getOwnerId() != $this->userId
        ) {
            if ($this->objTemplate->blockExists('downloads_simple_file_upload')) {
                $this->objTemplate->hideBlock('downloads_simple_file_upload');
            }
            if ($this->objTemplate->blockExists('downloads_advanced_file_upload')) {
                $this->objTemplate->hideBlock('downloads_advanced_file_upload');
            }
            return;
        }

        require_once ASCMS_CORE_PATH.'/Modulechecker.class.php';

        $objModulChecker = new ModuleChecker();
        if ($objModulChecker->getModuleStatusById(52) && $_CONFIG['fileUploaderStatus'] == 'on') {
            if ($this->objTemplate->blockExists('downloads_advanced_file_upload')) {
                $path = 'index.php?section=fileUploader&amp;standalone=true&amp;type=downloads&amp;catId='.$objCategory->getId();
                $this->objTemplate->setVariable(array(
                    'DOWNLOADS_FILE_UPLOAD_BUTTON'  => '<input type="button" onclick="objDAMPopup=window.open(\''.$path.'\',\'fileUploader\',\'width=800,height=600,resizable=yes,status=no,scrollbars=no\');objDAMPopup.focus();" value="'.$_ARRAYLANG['TXT_DOWNLOADS_BROWSE'].'" />',
                    'TXT_DOWNLOADS_ADD_NEW_FILE'    => $_ARRAYLANG['TXT_DOWNLOADS_ADD_NEW_FILE']
                ));
                $this->objTemplate->parse('downloads_advanced_file_upload');
            }

            if ($this->objTemplate->blockExists('downloads_simple_file_upload')) {
                $this->objTemplate->hideBlock('downloads_simple_file_upload');
            }
        } else {
            if ($this->objTemplate->blockExists('downloads_simple_file_upload')) {
                $this->objTemplate->setVariable(array(
                    'TXT_DOWNLOADS_BROWSE'          => $_ARRAYLANG['TXT_DOWNLOADS_BROWSE'],
                    'TXT_DOWNLOADS_UPLOAD_FILE'     => $_ARRAYLANG['TXT_DOWNLOADS_UPLOAD_FILE'],
                    'TXT_DOWNLOADS_MAX_FILE_SIZE'   => $_ARRAYLANG['TXT_DOWNLOADS_MAX_FILE_SIZE'],
                    'TXT_DOWNLOADS_ADD_NEW_FILE'    => $_ARRAYLANG['TXT_DOWNLOADS_ADD_NEW_FILE'],
                    'DOWNLOADS_UPLOAD_URL'          => CONTREXX_SCRIPT_PATH.$this->moduleParamsHtml.'&amp;category='.$objCategory->getId(),
                    'DOWNLOADS_MAX_FILE_SIZE'       => $this->getFormatedFileSize(FWSystem::getMaxUploadFileSize())
                ));
                $this->objTemplate->parse('downloads_simple_file_upload');
            }

            if ($this->objTemplate->blockExists('downloads_advanced_file_upload')) {
                $this->objTemplate->hideBlock('downloads_advanced_file_upload');
            }
        }
    }


    private function parseCreateCategoryForm($objCategory)
    {
        global $_ARRAYLANG;

        if (!$this->objTemplate->blockExists('downloads_create_category')) {
            return;
        }

        // check for sufficient permissiosn
        if ($objCategory->getAddSubcategoriesAccessId()
            && !Permission::checkAccess($objCategory->getAddSubcategoriesAccessId(), 'dynamic', true)
            && $objCategory->getOwnerId() != $this->userId
        ) {
            if ($this->objTemplate->blockExists('downloads_create_category')) {
                $this->objTemplate->hideBlock('downloads_create_category');
            }
            return;
        }

        $this->objTemplate->setVariable(array(
            'TXT_DOWNLOADS_CREATE_DIRECTORY'        => $_ARRAYLANG['TXT_DOWNLOADS_CREATE_DIRECTORY'],
            'TXT_DOWNLOADS_CREATE_NEW_DIRECTORY'    => $_ARRAYLANG['TXT_DOWNLOADS_CREATE_NEW_DIRECTORY'],
            'DOWNLOADS_CREATE_CATEGORY_URL'         => CONTREXX_SCRIPT_PATH.$this->moduleParamsHtml.'&amp;category='.$objCategory->getId()
        ));
        $this->objTemplate->parse('downloads_create_category');
    }

    private function parseNestedCategoryList()
    {
        $tplBlock = 'downloads_nested_category_list';
        if (!$this->objTemplate->blockExists($tplBlock)) {
            return;
        }

        $objCategory = Category::getCategories(array('is_active' => true), null, array('order' => 'asc', 'name' => 'asc'), null);

        if ($objCategory->EOF) {
            $this->objTemplate->hideBlock($tplBlock);
            return;
        }

        $arrCategories = array();
        while (!$objCategory->EOF) {
            // TODO: getVisibility() < should only be checked if the user isn't an admin or so
            if ($objCategory->getVisibility() || Permission::checkAccess($objCategory->getReadAccessId(), 'dynamic', true)) {
                $arrCategories[$objCategory->getParentId()][] = array(
                    'id'        => $objCategory->getId(),
                    'name'      => $objCategory->getName(LANG_ID),
                    'owner_id'  => $objCategory->getOwnerId(),
                    'is_child'  => $objCategory->check4Subcategory($categoryId)
                );
            }

            $objCategory->next();
        }

        $nestedCategoryList = $this->parseNestedCategoryListTree($arrCategories);
        $this->objTemplate->setVariable('DOWNLOADS_NESTED_CATEGORY_LIST', $nestedCategoryList);
    }

    private function parseNestedCategoryListTree($arrCategories, $parentId = 0, $level = 0)
    {
        $list = array();

        if (!isset($arrCategories[$parentId])) {
            return '';
        }

        $list[] = '<ul id="categoryList'.$parentId.'" class="level'.$level.' '.($parentId ? 'closed' : 'open').'">';
        foreach($arrCategories[$parentId] as $arrCategory) {
            $isParent = isset($arrCategories[$arrCategory['id']]);
            $arrClasses = array();
            if ($isParent) {
                $list[] = '<li class="isParent">';
            } else {
                $list[] = '<li>';
            }

            if ($isParent) {
                $list[] = '<a href="javascript:void(0);" onclick="downloadsCategoryToggleExpand(this,\'categoryList'.$arrCategory['id'].'\')"><img src="images/modules/downloads/pluslink.gif" width="11" height="11" class="expander" /></a>';
            } else {
                $list[] = '<img src="images/modules/downloads/pixel.gif" width="11" height="11" class="expander" />';
            }

            $list[] = '<a href="javascript:void(0);" onclick="downloadsCategoryOnClick(this,'.$arrCategory['id'].')">';
            $list[] = '<img src="images/modules/downloads/folder_front.gif" class="folder" />'
                      .htmlentities($arrCategory['name'], ENT_QUOTES, CONTREXX_CHARSET);
            $list[] = '</a>';

            if (isset($arrCategories[$arrCategory['id']])) {
                $list[] = $this->parseNestedCategoryListTree($arrCategories, $arrCategory['id'], $level+1);
            }
            $list[] = '</li>';
        }
        $list[] = "</ul>";

        return join("\n", $list);
    }

    private function getDownloadList()
    {
        $arrDownloads = array();

        $objCategory = Category::getCategory($this->categoryId);

        if (!$objCategory->getId()) {
            return $arrDownloads;
        }

        // check access permissions to selected category
        if (!Permission::checkAccess(142, 'static', true)
            && $objCategory->getReadAccessId()
            && !Permission::checkAccess($objCategory->getReadAccessId(), 'dynamic', true)
            && $objCategory->getOwnerId() != $this->userId
        ) {
            return $arrDownloads;
        }

        $objDownload = new Download();
        $objDownload->loadDownloads(array('category_id' => $objCategory->getId(), 'expiration' => array('=' => 0, '>' => time())));

        if ($objDownload->EOF) {
            return $arrDownloads;
        } else {
            while (!$objDownload->EOF) {
                $arrDownloads[] = array(
                    'id'            => $objDownload->getId(),
                    'md5_sum'       => $objDownload->getMD5Sum(),
                    'icon'          => $objDownload->getIcon(true),
                    'size'          => $objDownload->getSize(),
                    'size_literal'  => FWSystem::getLiteralSizeFormat($objDownload->getSize()),
                    'image'         => $objDownload->getImage(),
                    'origin'        => $objDownload->getOrigin(),
                    'license'       => $objDownload->getLicense(),
                    'version'       => $objDownload->getVersion(),
                    'author'        => $objDownload->getAuthor(),
                    'website'       => $objDownload->getWebsite(),
                    'views'         => $objDownload->getViewCount(),
                    'downloads'     => $objDownload->getDownloadCount(),
                    'name'          => $objDownload->getName(LANG_ID),
                    'description'   => $objDownload->getDescription(LANG_ID)
                );

                $objDownload->next();
            }
        }

        return $arrDownloads;
    }

    private function parseCategory($objCategory)
    {
        global $_LANGID;

        if (!$this->objTemplate->blockExists('downloads_category')) {
            return;
        }

        $description = $objCategory->getDescription($_LANGID);
        $shortDescription = strip_tags($description);
        if (strlen($shortDescription) > 100) {
            $shortDescription = substr($shortDescription, 0, 97).'...';
        }
        $imageSrc = $objCategory->getImage();
        if (!empty($imageSrc) && file_exists(ASCMS_PATH.$imageSrc)) {
            $thumb_name = ImageManager::getThumbnailFilename($imageSrc);
            if (file_exists(ASCMS_PATH.$thumb_name)) {
                $thumbnailSrc = $thumb_name;
            } else {
                $thumbnailSrc = ImageManager::getThumbnailFilename(
                    $this->defaultCategoryImage['src']);
            }
        } else {
            $imageSrc = $this->defaultCategoryImage['src'];
            $thumbnailSrc = ImageManager::getThumbnailFilename(
                $this->defaultCategoryImage['src']);
        }

        $this->objTemplate->setVariable(array(
            'DOWNLOADS_CATEGORY_ID'                 =>  $objCategory->getId(),
            'DOWNLOADS_CATEGORY_NAME'               => htmlentities($objCategory->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET),
            'DOWNLOADS_CATEGORY_DESCRIPTION'        => $description,
            'DOWNLOADS_CATEGORY_SHORT_DESCRIPTION'  => $shortDescription,
            'DOWNLOADS_CATEGORY_IMAGE'              => $this->getHtmlImageTag($imageSrc, htmlentities($objCategory->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET)),
            'DOWNLOADS_CATEGORY_IMAGE_SRC'          => $imageSrc,
            'DOWNLOADS_CATEGORY_THUMBNAIL'          => $this->getHtmlImageTag($thumbnailSrc, htmlentities($objCategory->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET)),
            'DOWNLOADS_CATEGORY_THUMBNAIL_SRC'      => $thumbnailSrc
        ));

        $this->parseGroups($objCategory);

        $this->objTemplate->parse('downloads_category');
    }

    private function parseGroups($objCategory)
    {
        global $_LANGID;

        if (!$this->objTemplate->blockExists('downloads_category_group_list')) {
            return;
        }

        $objGroup = Group::getGroups(array('category_id' => $objCategory->getId()));

        if (!$objGroup->EOF) {
            while (!$objGroup->EOF) {
                $this->objTemplate->setVariable(array(
                    'DOWNLOADS_GROUP_ID'        => $objGroup->getId(),
                    'DOWNLOADS_GROUP_NAME'      => htmlentities($objGroup->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET),
                    'DOWNLOADS_GROUP_PAGE'      => $objGroup->getInfoPage()
                ));

                $this->objTemplate->parse('downloads_category_group');
                $objGroup->next();
            }

            $this->objTemplate->parse('downloads_category_group_list');
        } else {
            $this->objTemplate->hideBlock('downloads_category_group_list');
        }
    }

    private function parseCrumbtrail($objParentCategory)
    {
        global $_ARRAYLANG, $_LANGID;

        if (!$this->objTemplate->blockExists('downloads_crumbtrail')) {
            return;
        }

        $arrCategories = array();

        do {
            $arrCategories[] = array(
                'id'    => $objParentCategory->getId(),
                'name'  => htmlentities($objParentCategory->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET)
            );
            $objParentCategory = Category::getCategory($objParentCategory->getParentId());
        } while ($objParentCategory->getId());

        krsort($arrCategories);

        foreach ($arrCategories as $arrCategory) {
            $this->objTemplate->setVariable(array(
                'DOWNLOADS_CRUMB_ID'    => $arrCategory['id'],
                'DOWNLOADS_CRUMB_NAME'  => $arrCategory['name']
            ));
            $this->objTemplate->parse('downloads_crumb');
        }

        $this->objTemplate->setVariable('TXT_DOWNLOADS_START', $_ARRAYLANG['TXT_DOWNLOADS_START']);

        $this->objTemplate->parse('downloads_crumbtrail');
    }


    private function parseGlobalStuff($objCategory)
    {
        $this->objTemplate->setVariable(array(
            'DOWNLOADS_JS'  => $this->getJavaScriptCode($objCategory)
        ));

        $this->parseSearchForm($objCategory);
    }


    private function getJavaScriptCode($objCategory)
    {
        global $_ARRAYLANG;

        $fileDeleteTxt = preg_replace('#\n#', '\\n', addslashes($_ARRAYLANG['TXT_DOWNLOADS_CONFIRM_DELETE_DOWNLOAD']));
        $fileDeleteLink = CSRF::enhanceURI(CONTREXX_SCRIPT_PATH.$this->moduleParamsJs)
            .'&category='.$objCategory->getId().'&delete_file=';
        $categoryDeleteTxt = preg_replace('#\n#', '\\n', addslashes($_ARRAYLANG['TXT_DOWNLOADS_CONFIRM_DELETE_CATEGORY']));
        $categoryDeleteLink = CSRF::enhanceURI(CONTREXX_SCRIPT_PATH.$this->moduleParamsJs)
            .'&category='.$objCategory->getId().'&delete_category=';

        $javascript = <<<JS_CODE
<script type="text/javascript">
// <![CDATA[
function downloadsDeleteFile(id,name)
{
    msg = '$fileDeleteTxt'
    if (confirm(msg.replace('%s',name))) {
        window.location.href='$fileDeleteLink'+id;
    }
}

function downloadsDeleteCategory(id,name)
{
    msg = '$categoryDeleteTxt'
    if (confirm(msg.replace('%s',name))) {
        window.location.href='$categoryDeleteLink'+id;
    }
}

function downloadsCategoryToggleExpand(toggler, id)
{
    imgClosedSrc = 'images/modules/downloads/pluslink.gif';
    imgOpenedSrc = 'images/modules/downloads/minuslink.gif';
    \$J(\$J(toggler).children()[0]).attr('src', \$J('#'+id).hasClass('closed') ? imgOpenedSrc : imgClosedSrc);
    \$J('#'+id).toggleClass('closed');
}
// ]]>
</script>
JS_CODE;

        return $javascript;
    }


    public function getPageTitle()
    {
        return $this->pageTitle;
    }


    private function parseCategories($objCategory, $arrCategoryBlocks, $categoryLimit = null, $variablePrefix = '', $rowBlock = null, $arrSubCategoryBlocks = null, $subCategoryLimit = null)
    {
        global $_ARRAYLANG;

        if (!$this->objTemplate->blockExists($arrCategoryBlocks[0])) {
            return;
        }

        $allowDeleteCategories = !$objCategory->getManageSubcategoriesAccessId()
                            || Permission::checkAccess($objCategory->getManageSubcategoriesAccessId(), 'dynamic', true)
                            || $objCategory->getOwnerId() == $this->userId;
        $objSubcategory = Category::getCategories(array('parent_id' => $objCategory->getId(), 'is_active' => true), null, array('order' => 'asc', 'name' => 'asc'), null, $categoryLimit);

        if ($objSubcategory->EOF) {
            $this->objTemplate->hideBlock($arrCategoryBlocks[0]);
        } else {
            $row = 1;
            while (!$objSubcategory->EOF) {
                // set category attributes
                $this->parseCategoryAttributes($objSubcategory, $row++, $variablePrefix, $allowDeleteCategories);

                // parse subcategories
                if (isset($arrSubCategoryBlocks)) {
                    $this->parseCategories($objSubcategory, array('downloads_overview_subcategory_list', 'downloads_overview_subcategory'), $subCategoryLimit, 'SUB');
                }

                // parse category
                $this->objTemplate->parse($arrCategoryBlocks[1]);

                // parse row
                if (isset($rowBlock) && $this->objTemplate->blockExists($rowBlock) && $row % $this->arrConfig['overview_cols_count'] == 0) {
                    $this->objTemplate->parse($rowBlock);
                }

                $objSubcategory->next();
            }

            $this->objTemplate->setVariable(array(
                'TXT_DOWNLOADS_CATEGORIES'  => $_ARRAYLANG['TXT_DOWNLOADS_CATEGORIES'],
                'TXT_DOWNLOADS_DIRECTORIES' => $_ARRAYLANG['TXT_DOWNLOADS_DIRECTORIES']
            ));
            $this->objTemplate->parse($arrCategoryBlocks[0]);
        }
    }


    private function parseRelatedCategories($objDownload)
    {
        global $_ARRAYLANG;

        if (!$this->objTemplate->blockExists('downloads_file_category_list')) {
            return;
        }

        $arrCategoryIds = $objDownload->getAssociatedCategoryIds();
        if (count($arrCategoryIds)) {
            $row = 1;
            foreach ($arrCategoryIds as $categoryId) {
                $objCategory = Category::getCategory($categoryId);

                if (!$objCategory->EOF) {
                    // set category attributes
                    $this->parseCategoryAttributes($objCategory, $row++, 'FILE_');

                    // parse category
                    $this->objTemplate->parse('downloads_file_category');
                }
            }

            $this->objTemplate->setVariable('TXT_DOWNLOADS_RELATED_CATEGORIES', $_ARRAYLANG['TXT_DOWNLOADS_RELATED_CATEGORIES']);
            $this->objTemplate->parse('downloads_file_category_list');
        } else {
            $this->objTemplate->hideBlock('downloads_file_category_list');
        }
    }


    private function parseCategoryAttributes($objCategory, $row, $variablePrefix, $allowDeleteCategory = false)
    {
        global $_LANGID, $_ARRAYLANG;

        $description = $objCategory->getDescription($_LANGID);
        $shortDescription = strip_tags($description);
        if (strlen($shortDescription) > 100) {
            $shortDescription = substr($shortDescription, 0, 97).'...';
        }

        $imageSrc = $objCategory->getImage();
        if (!empty($imageSrc) && file_exists(ASCMS_PATH.$imageSrc)) {
            $thumb_name = ImageManager::getThumbnailFilename($imageSrc);
            if (file_exists(ASCMS_PATH.$thumb_name)) {
                $thumbnailSrc = $thumb_name;
            } else {
                $thumbnailSrc = ImageManager::getThumbnailFilename(
                    $this->defaultCategoryImage['src']);
            }
        } else {
            $imageSrc = $this->defaultCategoryImage['src'];
            $thumbnailSrc = ImageManager::getThumbnailFilename(
                $this->defaultCategoryImage['src']);
        }

        // parse delete icon link
        if ($allowDeleteCategory || $objCategory->getOwnerId() == $this->userId && $objCategory->getDeletableByOwner()) {
            $deleteIcon = $this->getHtmlDeleteLinkIcon(
                $objCategory->getId(),
                htmlspecialchars(str_replace("'", "\\'", $objCategory->getName($_LANGID)), ENT_QUOTES, CONTREXX_CHARSET),
                'downloadsDeleteCategory'
            );
        } else {
            $deleteIcon = '';
        }

        $this->objTemplate->setVariable(array(
            'DOWNLOADS_'.$variablePrefix.'CATEGORY_ID'                 => $objCategory->getId(),
            'DOWNLOADS_'.$variablePrefix.'CATEGORY_NAME'               => htmlentities($objCategory->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET),
            'DOWNLOADS_'.$variablePrefix.'CATEGORY_NAME_LINK'          => $this->getHtmlLinkTag(CONTREXX_SCRIPT_PATH.$this->moduleParamsHtml.'&amp;category='.$objCategory->getId(), sprintf($_ARRAYLANG['TXT_DOWNLOADS_SHOW_CATEGORY_CONTENT'], htmlentities($objCategory->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET)), htmlentities($objCategory->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET)),
            'DOWNLOADS_'.$variablePrefix.'CATEGORY_FOLDER_LINK'        => $this->getHtmlFolderLinkTag(CONTREXX_SCRIPT_PATH.$this->moduleParamsHtml.'&amp;category='.$objCategory->getId(), sprintf($_ARRAYLANG['TXT_DOWNLOADS_SHOW_CATEGORY_CONTENT'], htmlentities($objCategory->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET)), htmlentities($objCategory->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET)),
            'DOWNLOADS_'.$variablePrefix.'CATEGORY_DESCRIPTION'        => $description,
            'DOWNLOADS_'.$variablePrefix.'CATEGORY_SHORT_DESCRIPTION'  => $shortDescription,
            'DOWNLOADS_'.$variablePrefix.'CATEGORY_IMAGE'              => $this->getHtmlImageTag($imageSrc, htmlentities($objCategory->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET)),
            'DOWNLOADS_'.$variablePrefix.'CATEGORY_IMAGE_SRC'          => $imageSrc,
            'DOWNLOADS_'.$variablePrefix.'CATEGORY_THUMBNAIL'          => $this->getHtmlImageTag($thumbnailSrc, htmlentities($objCategory->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET)),
            'DOWNLOADS_'.$variablePrefix.'CATEGORY_THUMBNAIL_SRC'      => $thumbnailSrc,
            'DOWNLOADS_'.$variablePrefix.'CATEGORY_DOWNLOADS_COUNT'    => intval($objCategory->getAssociatedDownloadsCount()),
            'DOWNLOADS_'.$variablePrefix.'CATEGORY_DELETE_ICON'        => $deleteIcon,
            'DOWNLOADS_'.$variablePrefix.'CATEGORY_ROW_CLASS'          => 'row'.($row % 2 + 1),
            'TXT_DOWNLOADS_MORE'                                       => $_ARRAYLANG['TXT_DOWNLOADS_MORE']
        ));
    }


    private function getHtmlDeleteLinkIcon($id, $name, $method)
    {
        global $_ARRAYLANG;

        return sprintf($this->htmlLinkTemplate, "javascript:void(0)\" onclick=\"$method($id,'$name')", $_ARRAYLANG['TXT_DOWNLOADS_DELETE'], sprintf($this->htmlImgTemplate, 'cadmin/images/icons/delete.gif', $_ARRAYLANG['TXT_DOWNLOADS_DELETE']));
    }


    private function getHtmlLinkTag($href, $title, $value)
    {
        return sprintf($this->htmlLinkTemplate, $href, $title, $value);
    }


    private function getHtmlImageTag($src, $alt)
    {
        return sprintf($this->htmlImgTemplate, $src, $alt);
    }


    private function getHtmlFolderLinkTag($href, $title, $value)
    {
        return sprintf($this->htmlLinkTemplate, $href, $title, sprintf($this->htmlImgTemplate, 'images/modules/downloads/folder_front.gif', $title).' '.$value);
    }


    private function parseDownloads($objCategory)
    {
        global $_CONFIG, $_ARRAYLANG;

        if (!$this->objTemplate->blockExists('downloads_file_list')) {
            return;
        }

        $limitOffset = isset($_GET['pos']) ? intval($_GET['pos']) : 0;
        $objDownload = new Download();
        $objDownload->loadDownloads(array('category_id' => $objCategory->getId(), 'expiration' => array('=' => 0, '>' => time())), $this->searchKeyword, null, null, $_CONFIG['corePagingLimit'], $limitOffset);
        $categoryId = $objCategory->getId();
        $allowdDeleteFiles = !$objCategory->getManageFilesAccessId()
                            || Permission::checkAccess($objCategory->getManageFilesAccessId(), 'dynamic', true)
                            || $objCategory->getOwnerId() == $this->userId;

        if ($objDownload->EOF) {
            $this->objTemplate->hideBlock('downloads_file_list');
        } else {
            $row = 1;
            while (!$objDownload->EOF) {
                // select category
                if ($objCategory->EOF) {
                    $arrAssociatedCategories = $objDownload->getAssociatedCategoryIds();
                    $categoryId = $arrAssociatedCategories[0];
                }


                // parse download info
                $this->parseDownloadAttributes($objDownload, $categoryId, $allowdDeleteFiles);
                $this->objTemplate->setVariable('DOWNLOADS_FILE_ROW_CLASS', 'row'.($row++ % 2 + 1));
                $this->objTemplate->parse('downloads_file');


                $objDownload->next();
            }

            $downloadCount = $objDownload->getFilteredSearchDownloadCount();
            if ($downloadCount > $_CONFIG['corePagingLimit']) {
                $this->objTemplate->setVariable('DOWNLOADS_FILE_PAGING', getPaging($downloadCount, $limitOffset, '&amp;'.substr($this->moduleParamsHtml, 1).'&amp;category='.$objCategory->getId().'&amp;downloads_search_keyword='.htmlspecialchars($this->searchKeyword), "<b>".$_ARRAYLANG['TXT_DOWNLOADS_DOWNLOADS']."</b>"));
            }

            $this->objTemplate->setVariable(array(
                'TXT_DOWNLOADS_FILES'       => $_ARRAYLANG['TXT_DOWNLOADS_FILES'],
                'TXT_DOWNLOADS_DOWNLOAD'    => $_ARRAYLANG['TXT_DOWNLOADS_DOWNLOAD'],
                'TXT_DOWNLOADS_DOWNLOADS'   => $_ARRAYLANG['TXT_DOWNLOADS_DOWNLOADS']
            ));

            $this->objTemplate->parse('downloads_file_list');
        }
    }


    private function parseSpecialDownloads($arrBlocks, $arrFilter, $arrSort, $limit)
    {
        global $_ARRAYLANG;

        if (!$this->objTemplate->blockExists($arrBlocks[0])) {
            return;
        }

        $objDownload = new Download();
        $objDownload->loadDownloads($arrFilter, null, $arrSort, null, $limit);

        if ($objDownload->EOF) {
            $this->objTemplate->hideBlock($arrBlocks[0]);
        } else {
            $row = 1;
            while (!$objDownload->EOF) {
                // select category
                $arrAssociatedCategories = $objDownload->getAssociatedCategoryIds();
                $categoryId = $arrAssociatedCategories[0];

                // parse download info
                $this->parseDownloadAttributes($objDownload, $categoryId);
                $this->objTemplate->setVariable('DOWNLOADS_FILE_ROW_CLASS', 'row'.($row++ % 2 + 1));
                $this->objTemplate->parse($arrBlocks[1]);

                $objDownload->next();
            }

            $this->objTemplate->setVariable(array(
                'TXT_DOWNLOADS_MOST_VIEWED'         => $_ARRAYLANG['TXT_DOWNLOADS_MOST_VIEWED'],
                'TXT_DOWNLOADS_MOST_DOWNLOADED'     => $_ARRAYLANG['TXT_DOWNLOADS_MOST_DOWNLOADED'],
                'TXT_DOWNLOADS_NEW_DOWNLOADS'       => $_ARRAYLANG['TXT_DOWNLOADS_NEW_DOWNLOADS'],
                'TXT_DOWNLOADS_RECENTLY_UPDATED'    => $_ARRAYLANG['TXT_DOWNLOADS_RECENTLY_UPDATED']
            ));

            $this->objTemplate->touchBlock($arrBlocks[0]);
        }
    }


    private function parseDownloadAttributes($objDownload, $categoryId, $allowDeleteFilesFromCategory = false, $variablePrefix = '')
    {
        global $_ARRAYLANG, $_LANGID;

        $description = $objDownload->getDescription($_LANGID);
        $shortDescription = strip_tags($description);
        if (strlen($shortDescription) > 100) {
            $shortDescription = substr($shortDescription, 0, 97).'...';
        }

        $imageSrc = $objDownload->getImage();
        if (!empty($imageSrc) && file_exists(ASCMS_PATH.$imageSrc)) {
            $thumb_name = ImageManager::getThumbnailFilename($imageSrc);
            if (file_exists(ASCMS_PATH.$thumb_name)) {
                $thumbnailSrc = $thumb_name;
            } else {
                $thumbnailSrc = ImageManager::getThumbnailFilename(
                    $this->defaultCategoryImage['src']);
            }

            $imageSrc = FWValidator::getEscapedSource($imageSrc);
            $thumbnailSrc = FWValidator::getEscapedSource($thumbnailSrc);
            $image = $this->getHtmlImageTag($imageSrc, htmlentities($objDownload->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET));
            $thumbnail = $this->getHtmlImageTag($thumbnailSrc, htmlentities($objDownload->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET));
        } else {
            $imageSrc = FWValidator::getEscapedSource($this->defaultCategoryImage['src']);
            $thumbnailSrc = FWValidator::getEscapedSource(
                ImageManager::getThumbnailFilename(
                    $this->defaultCategoryImage['src']));
            $image = $this->getHtmlImageTag($this->defaultCategoryImage['src'], htmlentities($objDownload->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET));;
            $thumbnail = $this->getHtmlImageTag(
                ImageManager::getThumbnailFilename(
                    $this->defaultCategoryImage['src']),
                htmlentities($objDownload->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET));
        }

        // parse delete icon link
        if ($allowDeleteFilesFromCategory || $objDownload->getOwnerId() == $this->userId) {
            $deleteIcon = $this->getHtmlDeleteLinkIcon(
                $objDownload->getId(),
                htmlspecialchars(str_replace("'", "\\'", $objDownload->getName($_LANGID)), ENT_QUOTES, CONTREXX_CHARSET),
                'downloadsDeleteFile'
            );
        } else {
            $deleteIcon = '';
        }

        $this->objTemplate->setVariable(array(
            'TXT_DOWNLOADS_DOWNLOAD'            => $_ARRAYLANG['TXT_DOWNLOADS_DOWNLOAD'],
            'TXT_DOWNLOADS_ADDED_BY'            => $_ARRAYLANG['TXT_DOWNLOADS_ADDED_BY'],
            'TXT_DOWNLOADS_LAST_UPDATED'        => $_ARRAYLANG['TXT_DOWNLOADS_LAST_UPDATED'],
            'TXT_DOWNLOADS_DOWNLOADED'          => $_ARRAYLANG['TXT_DOWNLOADS_DOWNLOADED'],
            'TXT_DOWNLOADS_VIEWED'              => $_ARRAYLANG['TXT_DOWNLOADS_VIEWED'],
            'DOWNLOADS_'.$variablePrefix.'FILE_ID'                  => $objDownload->getId(),
            'DOWNLOADS_'.$variablePrefix.'FILE_DETAIL_SRC'          => CONTREXX_SCRIPT_PATH.$this->moduleParamsHtml.'&amp;category='.$categoryId.'&amp;id='.$objDownload->getId(),
            'DOWNLOADS_'.$variablePrefix.'FILE_NAME'                => htmlentities($objDownload->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET),
            'DOWNLOADS_'.$variablePrefix.'FILE_DESCRIPTION'         => $description,
            'DOWNLOADS_'.$variablePrefix.'FILE_SHORT_DESCRIPTION'   => $shortDescription,
            'DOWNLOADS_'.$variablePrefix.'FILE_IMAGE'               => $image,
            'DOWNLOADS_'.$variablePrefix.'FILE_IMAGE_SRC'           => $imageSrc,
            'DOWNLOADS_'.$variablePrefix.'FILE_THUMBNAIL'           => $thumbnail,
            'DOWNLOADS_'.$variablePrefix.'FILE_THUMBNAIL_SRC'       => $thumbnailSrc,
            'DOWNLOADS_'.$variablePrefix.'FILE_ICON'                => $this->getHtmlImageTag($objDownload->getIcon(), htmlentities($objDownload->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET)),
            'DOWNLOADS_'.$variablePrefix.'FILE_FILE_TYPE_ICON'      => $this->getHtmlImageTag($objDownload->getFileIcon(), htmlentities($objDownload->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET)),
            'DOWNLOADS_'.$variablePrefix.'FILE_DELETE_ICON'         => $deleteIcon,
            'DOWNLOADS_'.$variablePrefix.'FILE_DOWNLOAD_LINK_SRC'   => CONTREXX_SCRIPT_PATH.$this->moduleParamsHtml.'&amp;download='.$objDownload->getId(),
            'DOWNLOADS_'.$variablePrefix.'FILE_OWNER'               => $this->getParsedUsername($objDownload->getOwnerId()),
            'DOWNLOADS_'.$variablePrefix.'FILE_OWNER_ID'            => $objDownload->getOwnerId(),
            'DOWNLOADS_'.$variablePrefix.'FILE_SRC'                 => htmlentities($objDownload->getSourceName(), ENT_QUOTES, CONTREXX_CHARSET),
            'DOWNLOADS_'.$variablePrefix.'FILE_LAST_UPDATED'        => date(ASCMS_DATE_FORMAT, $objDownload->getMTime()),
            'DOWNLOADS_'.$variablePrefix.'FILE_VIEWS'               => $objDownload->getViewCount(),
            'DOWNLOADS_'.$variablePrefix.'FILE_DOWNLOAD_COUNT'      => $objDownload->getDownloadCount(),
            'DOWNLOADS_'.$variablePrefix.'FILE_MD5'                 => $objDownload->getMD5Sum()
        ));

        // parse size
        if ($this->arrConfig['use_attr_size']) {
            $this->objTemplate->setVariable(array(
                'TXT_DOWNLOADS_SIZE'                => $_ARRAYLANG['TXT_DOWNLOADS_SIZE'],
                'DOWNLOADS_'.$variablePrefix.'FILE_SIZE'    => $this->getFormatedFileSize($objDownload->getSize())
            ));
        }

        // parse license
        if ($this->arrConfig['use_attr_license']) {
            $this->objTemplate->setVariable(array(
                'TXT_DOWNLOADS_LICENSE'             => $_ARRAYLANG['TXT_DOWNLOADS_LICENSE'],
                'DOWNLOADS_'.$variablePrefix.'FILE_LICENSE' => htmlentities($objDownload->getLicense(), ENT_QUOTES, CONTREXX_CHARSET),
            ));
        }

        // parse version
        if ($this->arrConfig['use_attr_version']) {
            $this->objTemplate->setVariable(array(
                'TXT_DOWNLOADS_VERSION'             => $_ARRAYLANG['TXT_DOWNLOADS_VERSION'],
                'DOWNLOADS_'.$variablePrefix.'FILE_VERSION' => htmlentities($objDownload->getVersion(), ENT_QUOTES, CONTREXX_CHARSET),
            ));
        }

        // parse author
        if ($this->arrConfig['use_attr_author']) {
            $this->objTemplate->setVariable(array(
                'TXT_DOWNLOADS_AUTHOR'              => $_ARRAYLANG['TXT_DOWNLOADS_AUTHOR'],
                'DOWNLOADS_'.$variablePrefix.'FILE_AUTHOR'  => htmlentities($objDownload->getAuthor(), ENT_QUOTES, CONTREXX_CHARSET),
            ));
        }

        // parse origin
        if ($this->arrConfig['use_attr_origin']) {
            switch ($objDownload->getOrigin()) {
                case 'external':
                    $origin = $_ARRAYLANG['TXT_DOWNLOADS_EXTERNAL'];
                break;

                case 'internal':
                default:
                    $origin = $_ARRAYLANG['TXT_DOWNLOADS_INTERNAL'];
                break;
            }

            $this->objTemplate->setVariable(array(
                'TXT_DOWNLOADS_ORIGIN'              => $_ARRAYLANG['TXT_DOWNLOADS_ORIGIN'],
                'DOWNLOADS_FILE_ORIGIN'             => htmlentities($origin , ENT_QUOTES, CONTREXX_CHARSET),
            ));
        }

        // parse website
        if ($this->arrConfig['use_attr_website']) {
            $this->objTemplate->setVariable(array(
                'TXT_DOWNLOADS_WEBSITE'             => $_ARRAYLANG['TXT_DOWNLOADS_WEBSITE'],
                'DOWNLOADS_'.$variablePrefix.'FILE_WEBSITE'     => $this->getHtmlLinkTag(htmlentities($objDownload->getWebsite(), ENT_QUOTES, CONTREXX_CHARSET), htmlentities($objDownload->getWebsite(), ENT_QUOTES, CONTREXX_CHARSET), htmlentities($objDownload->getWebsite(), ENT_QUOTES, CONTREXX_CHARSET)),
                'DOWNLOADS_'.$variablePrefix.'FILE_WEBSITE_SRC' => htmlentities($objDownload->getWebsite(), ENT_QUOTES, CONTREXX_CHARSET),
            ));
        }
    }


    private function parseRelatedDownloads($objDownload, $currentCategoryId)
    {
        global $_LANGID, $_ARRAYLANG;

        if (!$this->objTemplate->blockExists('downloads_related_file_list')) {
            return;
        }

        $objRelatedDownload = $objDownload->getDownloads(array('download_id' => $objDownload->getId()), null, array('order' => 'ASC', 'name' => 'ASC', 'id' => 'ASC'));

        if ($objRelatedDownload) {
            $row = 1;
            while (!$objRelatedDownload->EOF) {
                $arrAssociatedCategories = $objRelatedDownload->getAssociatedCategoryIds();
                if (in_array($currentCategoryId, $arrAssociatedCategories)) {
                    $categoryId = $currentCategoryId;
                } else {
                    $arrPublicCategories = array();
                    $arrProtectedCategories = array();

                    foreach ($arrAssociatedCategories as $categoryId) {
                        $objCategory = Category::getCategory($categoryId);
                        if (!$objCategory->EOF) {
                            if ($objCategory->getVisibility()
                                || Permission::checkAccess($objCategory->getReadAccessId(), 'dynamic', true)
                                || $objCategory->getOwnerId() == $this->userId
                               ) {
                                $arrPublicCategories[] = $categoryId;
                                break;
                            } else {
                                $arrProtectedCategories[] = $categoryId;
                            }
                        }
                    }

                    if (count($arrPublicCategories)) {
                        $categoryId = $arrPublicCategories[0];
                    } elseif (count($arrProtectedCategories)) {
                        $categoryId = $arrProtectedCategories[0];
                    } else {
                        $objRelatedDownload->next();
                        continue;
                    }
                }

                $this->parseDownloadAttributes($objRelatedDownload, $categoryId, false, 'RELATED_');

                $this->objTemplate->setVariable('DOWNLOADS_RELATED_FILE_ROW_CLASS', 'row'.($row++ % 2 + 1));
                $this->objTemplate->parse('downloads_related_file');

                $objRelatedDownload->next();
            }

            $this->objTemplate->setVariable('TXT_DOWNLOADS_RELATED_DOWNLOADS', $_ARRAYLANG['TXT_DOWNLOADS_RELATED_DOWNLOADS']);
            $this->objTemplate->parse('downloads_related_file_list');
        } else {
            $this->objTemplate->hideBlock('downloads_related_file_list');
        }
    }


    private function parseDownload($objDownload, $categoryId)
    {
        global $_LANGID, $_ARRAYLANG;

        if (!$this->objTemplate->blockExists('downloads_file_detail')) {
            return;
        }


        $this->parseDownloadAttributes($objDownload, $categoryId);
        $this->objTemplate->parse('downloads_file_detail');

        $objDownload->incrementViewCount();
    }


    private function parseSearchForm($objCategory)
    {
        global $_ARRAYLANG;

        $this->objTemplate->setVariable(array(
            'DOWNLOADS_SEARCH_KEYWORD'  => htmlentities($this->searchKeyword, ENT_QUOTES, CONTREXX_CHARSET),
            'DOWNLOADS_SEARCH_URL'      => CONTREXX_SCRIPT_PATH.$this->moduleParamsHtml.'&amp;category='.$objCategory->getId(),
            'TXT_DOWNLOADS_SEARCH'      => $_ARRAYLANG['TXT_DOWNLOADS_SEARCH']
        ));
    }


    private function download()
    {
        global $objInit;

        $objDownload = new Download();
        $objDownload->load(!empty($_GET['download']) ? intval($_GET['download']) : 0);
        if (!$objDownload->EOF) {
            // check if the download is expired
            if ($objDownload->getExpirationDate() && $objDownload->getExpirationDate() < time()) {
                CSRF::header("Location: ".CONTREXX_DIRECTORY_INDEX."?section=error&id=404");
                exit;
            }

            if (// download is protected
                $objDownload->getAccessId()
                // the user isn't a admin
                && !Permission::checkAccess(142, 'static', true)
                // the user doesn't has access to this download
                && !Permission::checkAccess($objDownload->getAccessId(), 'dynamic', true)
                // the user isn't the owner of the download
                && $objDownload->getOwnerId() != $this->userId
            ) {
                Permission::noAccess(base64_encode($objInit->getPageUri()));
            }

            $objDownload->incrementDownloadCount();

            if ($objDownload->getType() == 'file') {
                $objDownload->send();
            } else {
                // add socket -> prevent to hide the source from the customer
                CSRF::header('Location: '.$objDownload->getSource());
            }
        }
    }


    private function getFormatedFileSize($bytes)
    {
        global $_ARRAYLANG;

        if (!$bytes) {
            return $_ARRAYLANG['TXT_DOWNLOADS_UNKNOWN'];
        }

        $exp = log($bytes, 1024);

        if ($exp < 1) {
            return $bytes.' '.$_ARRAYLANG['TXT_DOWNLOADS_BYTES'];
        } elseif ($exp < 2) {
            return round($bytes/1024, 2).' '.$_ARRAYLANG['TXT_DOWNLOADS_KBYTE'];
        } elseif ($exp < 3) {
            return round($bytes/pow(1024, 2), 2).' '.$_ARRAYLANG['TXT_DOWNLOADS_MBYTE'];
        } else {
            return round($bytes/pow(1024, 3), 2).' '.$_ARRAYLANG['TXT_DOWNLOADS_GBYTE'];
        }
    }
}

?>
