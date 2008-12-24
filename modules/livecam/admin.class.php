<?php
/**
 * Livecam
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author        Comvation Development Team <info@comvation.com>
 * @version        1.0.0
 * @package     contrexx
 * @subpackage  module_livecam
 * @todo        Edit PHP DocBlocks!
 */

/**
 * @ignore
 */
require_once ASCMS_MODULE_PATH.'/livecam/lib/livecamLib.class.php';

/**
 * Livecam
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author        Comvation Development Team <info@comvation.com>
 * @access        public
 * @version        1.0.0
 * @package     contrexx
 * @subpackage  module_livecam
 */
class LivecamManager extends LivecamLibrary
{
    var $_objTpl;
    var $_pageTitle;
    var $_strErrMessage = '';
    var $_strOkMessage = '';

    /**
    * Constructor
    */
    function LivecamManager()
    {
        $this->__construct();
    }

    /**
    * PHP5 constructor
    *
    * @global HTML_Template_Sigma
    * @global array
    * @global array
    */
    function __construct()
    {

        global $objTemplate, $_ARRAYLANG, $_CONFIG;

        $this->_objTpl = &new HTML_Template_Sigma(ASCMS_MODULE_PATH.'/livecam/template');
        $this->_objTpl->setErrorHandling(PEAR_ERROR_DIE);

        $this->getSettings();
/*
        what the fuck is this?
        if (isset($_POST['saveSettings'])) {
            $arrSettings = array(
                'blockStatus'    => isset($_POST['blockUseBlockSystem']) ? intval($_POST['blockUseBlockSystem']) : 0
            );
            $this->_saveSettings($arrSettings);
        }
        */

        $objTemplate->setVariable("CONTENT_NAVIGATION", "<a href='index.php?cmd=livecam'>".$_ARRAYLANG['TXT_CAMS']."</a>".
                                   "<a href='index.php?cmd=livecam&amp;act=settings'>".$_ARRAYLANG['TXT_SETTINGS']."</a>");

    }

    /**
     * Get page
     *
     * Get a page of the block system administration
     *
     * @access public
     * @global HTML_Template_Sigma
     * @global array
     */
    function getPage()
    {
        global $objTemplate, $_CONFIG;

        if (!isset($_REQUEST['act'])) {
            $_REQUEST['act'] = '';
        }

        switch ($_REQUEST['act']) {
            case 'saveSettings':
                $this->saveSettings();
                header("Location: index.php?cmd=livecam&act=settings");
                break;
            case 'settings':
                $this->settings();
                break;
            case 'saveCam':
                $this->saveCam();
                break;
            case 'cams':
            default:
                $this->showCams();
                break;
        }

        $objTemplate->setVariable(array(
            'CONTENT_TITLE'                => $this->_pageTitle,
            'CONTENT_OK_MESSAGE'        => $this->_strOkMessage,
            'CONTENT_STATUS_MESSAGE'    => $this->_strErrMessage,
            'ADMIN_CONTENT'                => $this->_objTpl->get()
        ));
    }

    /**
     * Show the cameras
     *
     * @access private
     * @global array
     * @global array
     * @global array
     */
    function showCams()
    {
        global $_ARRAYLANG, $_CONFIG, $_CORELANG;

        $this->_pageTitle = $_ARRAYLANG['TXT_SETTINGS'];
        $this->_objTpl->loadTemplateFile('module_livecam_cams.html');

        $amount = $this->arrSettings['amount_of_cams'];

        $cams = $this->getCamSettings();

        $this->_objTpl->setGlobalVariable(array(
            'TXT_SETTINGS'            => $_ARRAYLANG['TXT_SETTINGS'],
            'TXT_CURRENT_IMAGE_URL'   => $_ARRAYLANG['TXT_CURRENT_IMAGE_URL'],
            'TXT_ARCHIVE_PATH'        => $_ARRAYLANG['TXT_ARCHIVE_PATH'],
            'TXT_SAVE'                => $_ARRAYLANG['TXT_SAVE'],
            'TXT_THUMBNAIL_PATH'      => $_ARRAYLANG['TXT_THUMBNAIL_PATH'],
            'TXT_LIGHTBOX_ACTIVE'     => $_CORELANG['TXT_ACTIVATED'],
            'TXT_LIGHTBOX_INACTIVE'   => $_CORELANG['TXT_DEACTIVATED'],
            'TXT_ACTIVATE_LIGHTBOX'   => $_ARRAYLANG['TXT_ACTIVATE_LIGHTBOX'],
            'TXT_ACTIVATE_LIGHTBOX_INFO'    => $_ARRAYLANG['TXT_ACTIVATE_LIGHTBOX_INFO'],
            'TXT_MAKE_A_FRONTEND_PAGE'    => $_ARRAYLANG['TXT_MAKE_A_FRONTEND_PAGE'],
            'TXT_CURRENT_IMAGE_MAX_SIZE'    => $_ARRAYLANG['TXT_CURRENT_IMAGE_MAX_SIZE'],
            'TXT_THUMBNAIL_MAX_SIZE'        => $_ARRAYLANG['TXT_THUMBNAIL_MAX_SIZE'],
            'TXT_CAM'                 => $_ARRAYLANG['TXT_CAM'],
            'MODULE_PATH'             => ASCMS_MODULE_WEB_PATH,
            'ASCMS_PATH_OFFSET'       => ASCMS_PATH_OFFSET,
            'ASCMS_PATH_OFFSET'       => ASCMS_PATH_OFFSET,
            'TXT_SUCCESS'             => $_CORELANG['TXT_SETTINGS_UPDATED'],
            'TXT_TO_MODULE'           => $_ARRAYLANG['TXT_LIVECAM_TO_MODULE']
        ));

        for ($i = 1; $i<=$amount; $i++) {
            if ($cams[$i]['lightboxActivate'] == 1) {
                $lightboxActive = 'checked="checked"';
                $lightboxInctive = '';
            } else {
                $lightboxActive = '';
                $lightboxInctive = 'checked="checked"';
            }

            $this->_objTpl->setVariable(array(
                'CAM_NUMBER'             => $i,
                'CURRENT_IMAGE_URL'      => $cams[$i]['currentImagePath'],
                'ARCHIVE_PATH'           => $cams[$i]['archivePath'],
                'THUMBNAIL_PATH'         => $cams[$i]['thumbnailPath'],
                'LIGHTBOX_ACTIVE'         => $lightboxActive,
                'LIGHTBOX_INACTIVE'         => $lightboxInctive,
                'CURRENT_IMAGE_MAX_SIZE' => $cams[$i]['maxImageWidth'],
                'THUMBNAIL_MAX_SIZE'     => $cams[$i]['thumbMaxSize']
            ));

            if (preg_match('/^https{0,1}:\/\//', $cams[$i]['currentImagePath'])) {
                $filepath = $cams[$i]['currentImagePath'];
                $this->_objTpl->setVariable('PATH', $filepath);
                $this->_objTpl->parse('current_image');
            } else {
                $filepath = ASCMS_PATH.$cams[$i]['currentImagePath'];
                if (file_exists($filepath) && is_file($filepath)) {
                    $this->_objTpl->setVariable('PATH', $cams[$i]['currentImagePath']);
                    $this->_objTpl->parse('current_image');
                } else {
                    $this->_objTpl->hideBlock('current_image');
                }
            }
            $this->_objTpl->parse('cam');
            /*
            $this->_objTpl->setVariable('BLOCK_USE_BLOCK_SYSTEM', $_CONFIG['blockStatus'] == '1' ? 'checked="checked"' : '');
            */
        }
    }

    /**
     * Save the cam's settings
     *
     */
    function saveCam()
    {
        global $objDatabase;

        $id = intval($_POST['id']);
        $currentImagePath = $_POST['currentImagePath'];
        $maxImageWidth = intval($_POST['maxImageWidth']);
        $archivePath = $_POST['archivePath'];
        $thumbnailPath = $_POST['thumbnailPath'];
        $thumbMaxSize = intval($_POST['thumbMaxSize']);
        $lightboxActivate = intval($_POST['lightboxActivate']);

        $query = " UPDATE ".DBPREFIX."module_livecam
                   SET currentImagePath = '".$currentImagePath."',
                       maxImageWidth = ".$maxImageWidth.",
                       archivePath = '".$archivePath."',
                       thumbnailPath = '".$thumbnailPath."',
                       thumbMaxSize = ".$thumbMaxSize.",
                       lightboxActivate = '".$lightboxActivate."'
                   WHERE id = ".$id;
        if ($objDatabase->Execute($query) === false) {
            // return a 500 or so
            header("HTTP/1.0 500 Internal Server Error");
            die();
        }
        die();
    }

    /**
     * Show settings
     *
     */
    private function settings()
    {
        global $_ARRAYLANG, $objDatabase;

        $this->_pageTitle = $_ARRAYLANG['TXT_SETTINGS'];
        $this->_objTpl->loadTemplateFile('module_livecam_settings.html');

        /*
            i'd do this differently if i had the time and since there's
            only property i guess this isn't that bat
        */
        $query = "SELECT setvalue FROM ".DBPREFIX."module_livecam_settings
                    WHERE setname = 'amount_of_cams'";
        $result = $objDatabase->Execute($query);


        $this->_objTpl->setVariable(array(
            "TXT_SETTINGS"          => $_ARRAYLANG['TXT_SETTINGS'],
            "TXT_SAVE"              => $_ARRAYLANG['TXT_SAVE'],
            "TXT_NUMBER_OF_CAMS"    => $_ARRAYLANG['TXT_LIVECAM_NUMBER_OF_CAMS'],
            "NUMBER_OF_CAMS"        => $result->fields['setvalue']
        ));
    }


    /**
     * Save Settings
     *
     * @access private
     * @global ADONewConnection
     * @global array
     * @global array
     */
    private function saveSettings()
    {
        global $objDatabase, $_ARRAYLANG, $_CORELANG;

        $number_of_cams = intval($_POST['number_of_cams']);
        $this->save("amount_of_cams", $number_of_cams);

        for ($i = 1; $i<=$number_of_cams; $i++) {
            $query = "  SELECT id
                        FROM ".DBPREFIX."module_livecam
                        WHERE id = ".$i;
            $result = $objDatabase->Execute($query);
            if ($result->RecordCount() == 0) {
                $query = "  INSERT INTO ".DBPREFIX."module_livecam
                            (id, currentImagePath, archivePath, thumbnailPath,
                             maxImageWidth, thumbMaxSize, lightboxActivate)
                            VALUES
                            (".$i.", '/webcam/cam".$i."/current.jpg',
                             '/webcam/cam".$i."/archive',
                             '/webcam/cam".$i."/thumbs', 400, 120, 1)";
                $objDatabase->Execute($query);
            }
        }

        $this->cleanUp($number_of_cams);
    }

    /**
     * Save
     *
     * Saves one option
     *
     * @access private
     * @global ADONewConnection
     */
    private function save($setname, $setval)
    {
        global $objDatabase;

        $setval = addslashes($setval);
        $setname = addslashes($setname);

        $query = "UPDATE ".DBPREFIX."module_livecam_settings
                SET setvalue = '$setval'
                WHERE setname = '$setname'";

        if (!$objDatabase->Execute($query)) {
            return false;
        } else {
            return true;
        }
    }

    /**
     * Enter description here...
     *
     * @param unknown_type $number
     */
    private function cleanUp($number)
    {
        global $objDatabase;

        $query = " DELETE FROM ".DBPREFIX."module_livecam
                   WHERE id > ".$number;
        $objDatabase->Execute($query);
    }

}
?>
