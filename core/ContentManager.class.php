<?php
/**
 * ContentManager
 * @copyright    CONTREXX CMS - COMVATION AG
 * @author        Comvation Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  core
 * @version        1.0.0
 */

/**
 * Includes
 */
require_once ASCMS_CORE_PATH.'/Tree.class.php';
require_once ASCMS_CORE_PATH.'/XMLSitemap.class.php';
require_once ASCMS_CORE_MODULE_PATH.'/cache/admin.class.php';

/**
 * ContentManager
 *
 * Manages the site content
 * @copyright    CONTREXX CMS - COMVATION AG
 * @author        Comvation Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  core
 * @version        1.0.0
 */
class ContentManager
{
   /**
     * Page title
    * @var string
    */
    var $pagetitle = '';

   /**
     * Error status message
    * @var string
    */
    var $strErrMessage = array();

    /**
     * Status message (no error)
     * @var string
     */
    var $strOkMessage = '';

   /**
     * Module ID
     * @var integer
    */
    var $setModule = 0;

   /**
     * Command (cmd) parameter
    * @var string
    */
    var $setCmd = '';

   /**
    * @var array
    * @desc Array with the WYSIWYG module ids
    */
    var $arrNoExpertmodes = array();

   /**
    * @var int
    * @desc Language id
    */
    var $langId;

    /**
     * @var int
     * @desc Id of first active language;
     */
    var $firstActiveLang;
   /**
     *
    * @var array
     * @desc
    */
    var $arrAllFrontendGroups = array();

   /**
     * Array of all backend groups (name, id)
    * @var array
    */
    var $arrAllBackendGroups = array();

   /**
    * @var array
    * @desc array of required modules
    * 1->core, 13->ids, 14->error, 15->home, 18->login
    *
    * @access private
    */
    var $_requiredModules = array(1,13,14,15,18);


    var $_navtable = array();

    var $_arrRedirectTargets = array('', '_blank', '_parent', '_self', '_top');

    var $boolHistoryEnabled = false;
    var $boolHistoryActivate = false;

    /**
    * Constructor
    *
    * @param  string
    * @access public
    */
    function __construct() {
        global $objDatabase,$objInit,$_CORELANG,$objTemplate,$_CONFIG,$objLanguage;

        $this->langId=$objInit->userFrontendLangId;
        foreach($objLanguage->getLanguageArray() as $arrLang){
            if($arrLang['frontend'] == 1){
                $this->firstActiveLang = $arrLang['id'];
                break;
            }
        }
        $objTemplate->setVariable("CONTENT_NAVIGATION",
                           "<a href='index.php?cmd=content&amp;act=new'>".$_CORELANG['TXT_NEW_PAGE']."</a>
                            <a href='index.php?cmd=content'>".$_CORELANG['TXT_CONTENT_MANAGER']."</a>
                            <a href='index.php?cmd=media&amp;path=/images/content/'>".$_CORELANG['TXT_IMAGE_ADMINISTRATION']."</a>");

        $objDatabase->Execute("OPTIMIZE TABLE ".DBPREFIX."content");
        $objDatabase->Execute("OPTIMIZE TABLE ".DBPREFIX."content_navigation");

           // normally all modules are in source code mode
           // Except the following modules : 15 Home, 13 Error, 14 IDS, 0 no module
           // Not in use anymore!
        $this->arrNoExpertmodes = array(0,13,14,15);

        $this->arrAllFrontendGroups = $this->_getAllGroups('frontend');
        $this->arrAllBackendGroups = $this->_getAllGroups('backend');

        $this->boolHistoryEnabled = ($_CONFIG['contentHistoryStatus'] == 'on') ? true : false;

        if (Permission::checkAccess(78, 'static', true)) {
            $this->boolHistoryActivate = true;
        }

        $this->collectLostPages();
    }

    /**
    * getPage
    *
    * calls the requested function
    */
    function getPage()
    {
        global $_CORELANG, $objTemplate;

        if(!isset($_GET['act'])){
            $_GET['act']='';
        }

        switch ($_GET['act']) {
        case "deleteAll":
            Permission::checkAccess(53, 'static');
            $this->_deleteAll();
            $this->contentOverview();
            // header("Location: index.php?cmd=content");
            break;

        case "copyAll":
            Permission::checkAccess(53, 'static');
            $this->_copyAll();
            $this->showCopyPage();
            break;

        case "new":
            Permission::checkAccess(5, 'static');
            $this->showNewPage();
            break;

        case "edit":
            Permission::checkAccess(35, 'static');
            $this->showEditPage();
            break;

        case "update":
            Permission::checkAccess(35, 'static');
            $this->updatePage();
            $this->showEditPage();
            break;

        case "changeprotection":
            $this->changeProtection();
            $this->contentOverview();
            break;

        case "changestatus":
            Permission::checkAccess(35, 'static');
            $this->changeStatus();
            $this->contentOverview();
            break;

        case "add":
            Permission::checkAccess(5, 'static');
            $pageId = intval($this->addPage());
            $this->showEditPage($pageId);
            break;

        case "delete":
            Permission::checkAccess(26, 'static');
            $this->deleteContent($_GET['pageId']);
            $this->collectLostPages();
            $this->contentOverview();
            break;

        case "addrepository":
            Permission::checkAccess(37, 'static');
            $this->addToRepository();
            $this->contentOverview();
            break;

        case 'changeActiveStatus':
            $this->changeActiveStatus($_GET['id']);
            if (($result = XMLSitemap::write()) !== true) {
                $this->strErrMessage[] = $result;
            }
            $this->contentOverview();
        break;
        case 'JSON':
            $this->createJSON();
            break;
        default:
            Permission::checkAccess(6, 'static');
            $this->contentOverview();
            break;
        }

        $objTemplate->setVariable(array(
            'CONTENT_TITLE'                => $this->pageTitle,
            'CONTENT_OK_MESSAGE'        => $this->strOkMessage,
            'CONTENT_STATUS_MESSAGE'    => implode("<br />\n", $this->strErrMessage)
        ));
    }

    /**
    * Show copy page
    *
    * @global    array Core language
    * @global    FWLanguage
    * @global    HTML_Template_Sigma
    */
    function showCopyPage()
    {
        global $_CORELANG, $objLanguage, $objTemplate;

        if (isset($_REQUEST['langOriginal']) && !empty($_REQUEST['langOriginal'])) {
            $this->contentOverview();
            unset($_REQUEST['langOriginal']);
        } else {
            $objTemplate->addBlockfile('ADMIN_CONTENT', 'content_copy_all', 'content_copy_all.html');
            $this->pageTitle = $_CORELANG['TXT_COPY_CONTENT'];
            $objTemplate->setVariable(array(
                'TXT_COPY_CONTENT'                => $_CORELANG['TXT_COPY_CONTENT'],
                'TXT_COPY'                        => $_CORELANG['TXT_COPY'],
                'TXT_COPY_CONTENT_OF_TO'        => $_CORELANG['TXT_COPY_CONTENT_OF_TO'],
                'TXT_THIS_PROCEDURE_DELETES_ALL_EXISTING_ENTRIES_OF_THE_SELECTED_LANGUAGE' => $_CORELANG['TXT_THIS_PROCEDURE_DELETES_ALL_EXISTING_ENTRIES_OF_THE_SELECTED_LANGUAGE'],
                'TXT_WARNING'                    => $_CORELANG['TXT_WARNING'],
                'TXT_DO_YOU_WANT_TO_CONTINUE'    => $_CORELANG['TXT_DO_YOU_WANT_TO_CONTINUE']
            ));

            foreach ($objLanguage->getLanguageArray() as $key){
                if ($key['id'] == $this->langId) {
                    $objTemplate->setVariable(array(
                        'LANG_OLD_ID' => $this->langId,
                        'LANG_OLD_NAME' => $key['name']
                    ));
                } else {
                    $objTemplate->setVariable(array(
                        'LANG_ID' => $key['id'],
                        'LANG_NAME' => $key['name']
                    ));
                    $objTemplate->parse('langList');
                }
            }
        }
    }

    /**
     * Copy page content
     *
     * @global   ADONewConnection
     * @global    array    Core language
     */
    function _copyAll()
    {
        global $objDatabase, $_CORELANG;
        if (isset($_POST['langOriginal']) && !empty($_POST['langOriginal'])) {
            $this->_deleteAll(intval($_POST['langNew']));
            $objResult = $objDatabase->Execute("SELECT catid,
                                  parcat,
                                  catname,
                                  displayorder,
                                  displaystatus,
                                  cachingstatus,
                                  username,
                                  changelog,
                                  cmd,
                                  module,
                                  startdate,
                                  enddate,
                                  themes_id,
                                  css_name
                             FROM ".DBPREFIX."content_navigation
                            WHERE `lang`=".intval($_POST['langOriginal']));
            $arrQuery = array();
            $arrId = array();
            if ($objResult !== false) {
                while (!$objResult->EOF) {
                    array_push($arrQuery, "INSERT INTO ".DBPREFIX."content_navigation (
                                                        catid,
                                                        parcat,
                                                        catname,
                                                        displayorder,
                                                        displaystatus,
                                                        cachingstatus,
                                                        username,
                                                        changelog,
                                                        cmd,
                                                        lang,
                                                        module,
                                                        startdate,
                                                        enddate,
                                                        protected,
                                                        themes_id,
                                                        css_name
                                                        ) VALUES (
                                                        '".$objResult->fields["catid"]."',
                                                        '".$objResult->fields["parcat"]."',
                                                        '".addslashes($objResult->fields["catname"])."',
                                                        '".addslashes($objResult->fields["displayorder"])."',
                                                        '".addslashes($objResult->fields["displaystatus"])."',
                                                        '".addslashes($objResult->fields["cachingstatus"])."',
                                                        '".addslashes($objResult->fields["username"])."',
                                                        '".addslashes($objResult->fields["changelog"])."',
                                                        '".addslashes($objResult->fields['cmd'])."',
                                                        ".intval($_POST['langNew']).",
                                                        '".addslashes($objResult->fields["module"])."',
                                                        '".addslashes($objResult->fields["startdate"])."',
                                                        '".addslashes($objResult->fields["enddate"])."',
                                                        '0',
                                                        '".intval($objResult->fields["themes_id"])."',
                                                        '".intval($objResult->fields["css_name"])."'
                                                        )");
                    array_push($arrId,array("old" => $objResult->fields["catid"]));
                    $objResult->MoveNext();
                }
            }

            for ($i=0; $i<count($arrId); $i++) {
                $objDatabase->Execute($arrQuery[$i]);
            }

//            for ($i=0; $i<count($arrId); $i++) {
//                $objDatabase->Execute("UPDATE ".DBPREFIX."content_navigation
//                                  SET parcat='".intval($arrId[$i]['new'])."'
//                                WHERE parcat='".intval($arrId[$i]['old'])."'
//                                  AND `lang`='".intval($_POST['langNew'])."'
//                                  AND parcat!=0");
//            }

            unset($arrQuery);
            $arrQuery = array();
            for ($i=0; $i<count($arrId); $i++) {
                $objResult = $objDatabase->Execute("SELECT id, content,
                                      title,
                                      metatitle,
                                      metadesc,
                                      metakeys,
                                      metarobots,
                                      css_name,
                                      redirect,
                                      expertmode
                                 FROM ".DBPREFIX."content
                                WHERE lang_id=".intval($_POST['langOriginal'])." AND id=".intval($arrId[$i]['old']));//
                if ($objResult !== false && $objResult->RecordCount()>0) {
                    array_push($arrQuery,"INSERT INTO ".DBPREFIX."content (id,lang_id,content,title,metatitle,metadesc,metakeys,metarobots,css_name,redirect,expertmode)
                    VALUES(
                    ".$objResult->fields["id"].",
                    ".intval($_POST['langNew']).",
                    '".addslashes($objResult->fields["content"])."',
                    '".addslashes($objResult->fields["title"])."',
                    '".addslashes($objResult->fields['metatitle'])."',
                    '".addslashes($objResult->fields["metadesc"])."',
                    '".addslashes($objResult->fields["metakeys"])."',
                    '".addslashes($objResult->fields["metarobots"])."',
                    '".addslashes($objResult->fields["css_name"])."',
                    '".addslashes($objResult->fields["redirect"])."',
                    '".addslashes($objResult->fields["expertmode"])."'
                    )
                    ");
                }
            }

            for ($i=0; $i<count($arrQuery); $i++){
                $objDatabase->Execute($arrQuery[$i]);
            }
            unset($arrQuery);
            $objDatabase->Execute("OPTIMIZE TABLE ".DBPREFIX."content_navigation");
            $objDatabase->Execute("OPTIMIZE TABLE ".DBPREFIX."content");

            if (($result = XMLSitemap::write()) !== true) {
                $this->strErrMessage[] = $result;
            }
        }
    }

    /**
    * Deletes all global site content
    *
    * @global ADONewConnection
    * @global array
    * @global FWLanguage
    */
    function _deleteAll($langId=0)
    {
        global $objDatabase, $_CORELANG, $objLanguage;

        if (isset($_GET['contentId']) && intval($_GET['contentId'])!=0) {
            $langId = intval($_GET['contentId']);
        }
        if (intval($langId) != 0) {
            // the default language site cannot be deleted
            if ($objLanguage->getLanguageParameter($langId, "is_default")=="true") {
                $this->strErrMessage[] = $_CORELANG['TXT_STANDARD_SITE_NOT_DELETED'];
            } else {
                $arrQuery = array();
                $objResult = $objDatabase->Execute("SELECT catid FROM ".DBPREFIX."content_navigation WHERE `lang`=".intval($langId));
                if ($objResult !== false) {
                    while (!$objResult->EOF) {
                        array_push($arrQuery, "DELETE FROM ".DBPREFIX."content WHERE lang_id=".intval($langId)." AND id=".intval($objResult->fields['catid']));

                        $objSubResult = $objDatabase->Execute('    SELECT    id
                                                                FROM     '.DBPREFIX.'content_navigation_history
                                                                WHERE    is_active="1" AND
                                                                        catid='.intval($objResult->fields['catid']).'
                                                                AND     `lang`='.$langId.'
                                                                LIMIT    1
                                                            ');
                        $objDatabase->Execute('    INSERT
                                                INTO    '.DBPREFIX.'content_logfile
                                                SET        action="delete",
                                                        history_id='.$objSubResult->fields['id'].'
                                            ');

                        $objResult->MoveNext();
                    }
                }
                for ($i=0; $i<count($arrQuery); $i++) {
                    $objDatabase->Execute($arrQuery[$i]);
                }
                unset($arrQuery);
                $objDatabase->Execute("DELETE FROM ".DBPREFIX."content_navigation WHERE `lang`=".intval($langId));

                //write caching-file, delete exisiting cache-files
                $objCache = new Cache();
                $objCache->writeCacheablePagesFile();

                // write xml sitemap
                if (($result = XMLSitemap::write()) !== true) {
                    $this->strErrMessage[] = $result;
                }
            }
        }
    }

    /**
    * @access private
    * @return array
    * @param string groupType
    * @desc gets all frontend groups as an array
    */
    function _getAllGroups($groupType="frontend")
    {
        global $objDatabase;

        if ($groupType!="frontend") {
            $groupType="backend";
        }

        $arrGroups=array();
        $objResult = $objDatabase->Execute("SELECT group_id, group_name FROM ".DBPREFIX."access_user_groups WHERE type='".$groupType."'");
        if ($objResult !== false) {
            while (!$objResult->EOF) {
                $arrGroups[$objResult->fields['group_id']]=$objResult->fields['group_name'];
                $objResult->MoveNext();
            }
        }
        return $arrGroups;
    }

    /**
     * Show sitemap
     *
     * @version   1.0        initial version
     * @global    ADONewConnection
     * @global    HTML_Template_Sigma
     * @global    array
     */
    function contentOverview()
    {
        global $objDatabase, $objTemplate, $_CORELANG;

        $this->pageTitle = $_CORELANG['TXT_CONTENT_MANAGER'];

        if ($_GET['act'] == "mod") {
            foreach ($_POST['catid'] as $value) {
                $displayorder_old = intval($_POST['displayorder_old'][$value]);
                $displayorder_new = intval($_POST['displayorder_new'][$value]);
                if ($displayorder_old != $displayorder_new) {
                    $objDatabase->Execute("UPDATE ".DBPREFIX."content_navigation
                                              SET displayorder=".$displayorder_new."
                                            WHERE catid=".intval($value)." AND `lang`=".$this->langId);
                    if ($this->boolHistoryEnabled) {
                        $objDatabase->Execute('UPDATE   '.DBPREFIX.'content_navigation_history
                                                  SET   changelog='.time().',
                                                        displayorder='.$displayorder_new.'
                                                WHERE   catid='.intval($value).' AND `lang`='.$this->langId.'
                                                  AND   is_active="1"
                                                LIMIT   1');
                    }

                }
            }

            switch ($_POST['frmContentSitemap_MultiAction']) {
                case 'delete':
                    Permission::checkAccess(26, 'static');
                    if (isset($_POST['selectedPages'])) {
                        foreach($_POST['selectedPages'] as $intPageId) {
                            $this->deleteContent($intPageId);
                        }
                    }
                break;
                case 'activate':
                    if (isset($_POST['selectedPages'])) {
                        foreach($_POST['selectedPages'] as $intPageId) {
                            $this->changeActiveStatus($intPageId,1);
                        }
                    }
                break;
                case 'deactivate':
                    if (isset($_POST['selectedPages'])) {
                        foreach($_POST['selectedPages'] as $intPageId) {
                            $this->changeActiveStatus($intPageId,0);
                        }
                    }
                break;
                default: //do nothing
            }

            // write xml sitemap
            if (($result = XMLSitemap::write()) !== true) {
                $this->strErrMessage[] = $result;
            }
        }
        $objNavbar = new ContentSitemap(0);
        $objTemplate->setVariable('ADMIN_CONTENT', $objNavbar->getSiteMap());
        //$objTemplate->addBlock('ADMIN_CONTENT', 'siteMap', $objNavbar->getSiteMap());
    }

    /**
     * Create new page
     *
     * @global    ADONewConnection
     * @global    array      Core language
     * @global    HTML_Template_Sigma
     */
    function showNewPage()
    {
        global $objDatabase, $_CORELANG, $objTemplate, $objLanguage;

        // init variables
        $contenthtml='';
        $pageId = '';
        $tablestatus="none";
        $existingFrontendGroups = '';
        $existingBackendGroups = '';

        $objTemplate->addBlockfile('ADMIN_CONTENT', 'content_editor', 'content_editor.html');
        $this->pageTitle = $_CORELANG['TXT_NEW_PAGE'];

        $objRS = $objDatabase->SelectLimit('SELECT max(catid)+1 AS `nextId` FROM `'.DBPREFIX.'content_navigation`');
        $pageId = $objRS->fields['nextId'];

        $langCount = 0;

        foreach ($objLanguage->getLanguageArray() as $arrLang){
            $checked = '';
            if($arrLang['frontend'] == 0){
                continue;
            }

            if(++$langCount == 1){ //first active
                $tabClass = 'active';
            }else{
                $tabClass = 'inactive';
            }

            if($arrLang['is_default'] == 'true'){
                $defaultLang = $arrLang['id'];
                $checked = 'checked="checked"';
                $objTemplate->setVariable(array(
                    'LANGUAGE_NAME'             => $arrLang['name'],
                    'LANGUAGE_TITLE'            => $arrLang['name'].'_'.$arrLang['id'],
                    'TAB_CLASS'                 => $tabClass,
                ));
                $objTemplate->parse('languages_tab');
            }

            $langActivateCheckbox = '<input type="checkbox" id="lang_'.$arrLang['lang'].'_'.$arrLang['id'].'" '.$checked.' />'
                                    .'<label for="lang_'.$arrLang['lang'].'_'.$arrLang['id'].'">'.$arrLang['name'].' ('.$arrLang['lang'].')</label>';
            $objTemplate->setVariable(array(
                'LANGUAGE_CHECKBOX'         => $langActivateCheckbox,
            ));
            $objTemplate->parse('languages_activate');
        }

        $objTemplate->setVariable('LANGUAGE_COUNT', $langCount+1);

        if (isset($_GET['pageId']) & !empty($_GET['pageId'])) {
            $pageId = intval($_GET['pageId']);

            $objResult = $objDatabase->SelectLimit("SELECT content,
                               metadesc,
                               metarobots,
                               title,
                               metakeys,
                               css_name
                          FROM ".DBPREFIX."content
                         WHERE lang_id = ".$defaultLang." AND id = ".$pageId, 1);
            if ($objResult !== false && $objResult->RecordCount()>0) {
                $contenthtml= $objResult->fields['content'];
                $contenthtml = preg_replace('/\{([A-Z0-9_-]+)\}/', '[[\\1]]' ,$contenthtml);
                $objTemplate->setVariable(array(
                    'CONTENT_HTML'             => get_wysiwyg_editor('html', $contenthtml),
                    'CONTENT_DESC'           => $objResult->fields['contentdesc'],
                    'CONTENT_META_TITLE'      => $objResult->fields['contenttitle'],
                    'CONTENT_KEY'              => $objResult->fields['contentkey'],
                    'CONTENT_CSS_NAME'      => $objResult->fields['css_name'],
                ));
            }


            $objResult = $objDatabase->SelectLimit("SELECT module,
                               startdate,
                               enddate,
                               displaystatus,
                               themes_id
                          FROM ".DBPREFIX."content_navigation
                         WHERE catid = ".$pageId." AND `lang`=".$defaultLang, 1);
            if ($objResult !== false && $objResult->RecordCount()>0) {
                $moduleId = $objResult->fields['module'];
                $startDate = $objResult->fields['startdate'];
                $endDate = $objResult->fields['enddate'];
                $displaystatus = '';
                $themesId = $objResult->fields['themes_id'];

                if ($objResult->fields['displaystatus'] == "on" ) {
                    $displaystatus = "checked";
                }

                $robotstatus = ($objResult->fields['metarobots'] == "index") ? "checked" : '';
                $objTemplate->setVariable(array(
                    'CONTENT_MODULE_MENU'              => $this->_getModuleMenu($moduleId),
                    'CONTENT_STARTDATE'                => $startDate,
                    'CONTENT_ENDDATE'                  => $endDate,
                    'CONTENT_DISPLAYSTATUS'            => $displaystatus,
                    'CONTENT_TABLE_STYLE'              => $tablestatus,
                    'CONTENT_ROBOTS'                   => $robotstatus,
                    'CONTENT_THEMES_MENU'              => $this->_getThemesMenu($themesId)
                ));
            }
        } else {
            // Never used
            //$arrAssignedFrontendGroups = $this->_getAssignedGroups('frontend');
            $objTemplate->setVariable(array(
                'CONTENT_HTML'        => get_wysiwyg_editor('html', $contenthtml),
                'CONTENT_MODULE_MENU' => $this->_getModuleMenu(''),
                'CONTENT_DATE'        => date('Y-m-d'),
                'CONTENT_TABLE_STYLE'              => $tablestatus
            ));
        }

        // Frontend Groups
        foreach ($this->arrAllFrontendGroups as $id => $name) {
            $existingFrontendGroups .= '<option value="'.$id.'">'.$name."</option>\n";
        }

        // Backend Groups
        foreach ($this->arrAllBackendGroups as $id => $name) {
            $existingBackendGroups .= '<option value="'.$id.'">'.$name."</option>\n";
        }

        // Blocks
        $blocks = array();
        $blocks = $this->getBlocks();


        $objTemplate->setVariable(array(
            'CONTENT_CATID'                     => $pageId,
            'TXT_ERROR_COULD_NOT_INSERT_PAGE'   => str_replace("'", "\'", $_CORELANG['TXT_ERROR_COULD_NOT_INSERT_PAGE']),
            'TXT_SUCCESS_PAGE_SAVED'            => str_replace("'", "\'", $_CORELANG['TXT_SUCCESS_PAGE_SAVED']),
            'TXT_CONTENT_PLEASE_WAIT'           => $_CORELANG['TXT_CONTENT_PLEASE_WAIT'],
            'TXT_CONTENT_NO_TITLE'              => $_CORELANG['TXT_CONTENT_NO_TITLE'],
            'TXT_LANGUAGES'                     => $_CORELANG['TXT_LANGUAGES'],
            'TXT_TARGET'                        => $_CORELANG['TXT_TARGET'],
            'TXT_MORE_OPTIONS'                  => $_CORELANG['TXT_MORE_OPTIONS'],
            'TXT_BASIC_DATA'                    => $_CORELANG['TXT_BASIC_DATA'],
            'TXT_FRONTEND_PERMISSION'           => $_CORELANG['TXT_FRONTEND_PERMISSION'],
            'TXT_RELATEDNESS'                   => $_CORELANG['TXT_BACKEND_RELATEDNESS'],
            'TXT_CHANGELOG'                     => $_CORELANG['TXT_CHANGELOG'],
            'TXT_PAGE_NAME'                     => $_CORELANG['TXT_PAGE_NAME'],
            'TXT_MENU_NAME'                     => $_CORELANG['TXT_MENU_NAME'],
            'TXT_NEW_CATEGORY'                  => $_CORELANG['TXT_NEW_CATEGORY'],
            'TXT_VISIBLE'                       => $_CORELANG['TXT_VISIBLE'],
            'TXT_CONTENT_TITLE'                 => $_CORELANG['TXT_PAGETITLE'],
            'TXT_META_INFORMATIONS'             => $_CORELANG['TXT_META_INFORMATIONS'],
            'TXT_META_TITLE'                    => $_CORELANG['TXT_META_TITLE'],
            'TXT_META_DESCRIPTION'              => $_CORELANG['TXT_META_DESCRIPTION'],
            'TXT_META_KEYWORD'                  => $_CORELANG['TXT_META_KEYWORD'],
            'TXT_META_ROBOTS'                   => $_CORELANG['TXT_META_ROBOTS'],
            'TXT_CONTENT'                       => $_CORELANG['TXT_CONTENT'],
            'TXT_GENERAL_OPTIONS'               => $_CORELANG['TXT_GENERAL_OPTIONS'],
            'TXT_START_DATE'                    => $_CORELANG['TXT_START_DATE'],
            'TXT_END_DATE'                      => $_CORELANG['TXT_END_DATE'],
            'TXT_EXPERT_MODE'                   => $_CORELANG['TXT_EXPERT_MODE'],
            'TXT_MODULE'                        => $_CORELANG['TXT_MODULE'],
            'TXT_NO_MODULE'                     => $_CORELANG['TXT_NO_MODULE'],
            'TXT_REDIRECT'                      => $_CORELANG['TXT_REDIRECT'],
            'TXT_BROWSE'                        => $_CORELANG['TXT_BROWSE'],
      		'TXT_CONTENT_ASSIGN_BLOCK'          => $_CORELANG['TXT_CONTENT_ASSIGN_BLOCK'],
           	'TXT_NO_REDIRECT'                   => '',
            'TXT_SOURCE_MODE'                   => $_CORELANG['TXT_SOURCE_MODE'],
            'TXT_CACHING_STATUS'                => $_CORELANG['TXT_CACHING_STATUS'],
            'TXT_THEMES'                        => $_CORELANG['TXT_THEMES'],
            'TXT_STORE'                         => $_CORELANG['TXT_SAVE'],
            'TXT_RECURSIVE_CHANGE'              => $_CORELANG['TXT_RECURSIVE_CHANGE'],
            'TXT_PROTECTION'                    => $_CORELANG['TXT_PROTECTION'],
            'TXT_PROTECTION_CHANGE'             => $_CORELANG['TXT_PROTECTION_CHANGE'],
            'TXT_RECURSIVE_CHANGE'              => $_CORELANG['TXT_RECURSIVE_CHANGE'],
            'TXT_GROUPS'                        => $_CORELANG['TXT_GROUPS'],
            'TXT_GROUPS_DEST'                   => $_CORELANG['TXT_GROUPS_DEST'],
            'TXT_SELECT_ALL'                    => $_CORELANG['TXT_SELECT_ALL'],
            'TXT_DESELECT_ALL'                  => $_CORELANG['TXT_DESELECT_ALL'],
            'TXT_ACCEPT_CHANGES'                => $_CORELANG['TXT_ACCEPT_CHANGES'],
            'TXT_PUBLIC_PAGE'                   => $_CORELANG['TXT_PUBLIC_PAGE'],
            'TXT_BACKEND_RELEASE'               => $_CORELANG['TXT_BACKEND_RELEASE'],
            'TXT_LIMIT_GROUP_RIGHTS'            => $_CORELANG['TXT_LIMIT_GROUP_RIGHTS'],
            'TXT_TARGET_BLANK'                  => $_CORELANG['TXT_TARGET_BLANK'],
            'TXT_TARGET_TOP'                    => $_CORELANG['TXT_TARGET_TOP'],
            'TXT_TARGET_PARENT'                 => $_CORELANG['TXT_TARGET_PARENT'],
            'TXT_TARGET_SELF'                   => $_CORELANG['TXT_TARGET_SELF'],
            'TXT_OPTIONAL_CSS_NAME'             => $_CORELANG['TXT_OPTIONAL_CSS_NAME'],
            'TXT_TYPE_SELECT'                   => $_CORELANG['TXT_CONTENT_TYPE'],
            'TXT_CONTENT_TYPE_DEFAULT'          => $_CORELANG['TXT_CONTENT_TYPE_DEFAULT'],
            'TXT_CONTENT_TYPE_REDIRECT'         => $_CORELANG['TXT_CONTENT_TYPE_REDIRECT'],
            'TXT_CONTENT_TYPE_HELP'             => $_CORELANG['TXT_CONTENT_TYPE_HELP'],
            'TXT_NAVIGATION'                    => $_CORELANG['TXT_NAVIGATION'],
            'TXT_ASSIGN_BLOCK'                  => $_CORELANG['TXT_ASSIGN_BLOCK'],
            'TXT_DEFAULT_ALIAS'                 => $_CORELANG['TXT_DEFAULT_ALIAS'],
            'CONTENT_ALIAS_HELPTEXT'            => $_CORELANG['CONTENT_ALIAS_HELPTEXT'],
            'CONTENT_ALIAS_DISABLE'             => ($this->_is_alias_enabled() ? '' : 'style="display: none;"'),
			'TXT_ERROR_NO_TITLE'                => $_CORELANG['TXT_ERROR_NO_TITLE'],
			'TXT_BASE_URL'                      => self::mkurl('/'),
        ));

        $objTemplate->hideBlock('deleteButton');
        $objTemplate->hideBlock('changelog1');
        $objTemplate->hideBlock('changelog2');

        $objTemplate->setVariable(array(
            'CONTENT_ACTION'                       => "add",
            'CONTENT_TOP_TITLE'                    => $_CORELANG['TXT_NEW_PAGE'],
            'CONTENT_DISPLAYSTATUS'                => "checked",
            'CONTENT_CACHING_STATUS'               => 'checked',
            'CONTENT_CAT_MENU'                     => $this->getPageMenu(),
            'CONTENT_CAT_MENU_NEW_PAGE'            => !Permission::checkAccess(127, 'static', true) ? 'disabled="disabled" style="color:graytext;"' : null,
            'CONTENT_FORM_ACTION'                  => "add",
            'CONTENT_ROBOTS'                       => "checked",
            'CONTENT_THEMES_MENU'                  => $this->_getThemesMenu(),
            'CONTENT_EXISTING_GROUPS'              => $existingFrontendGroups,
            'CONTENT_PROTECTION_INACTIVE'          => "checked",
            'CONTENT_PROTECTION_ACTIVE'            => '',
            'CONTENT_PROTECTION_DISPLAY'           => "none",
            'CONTENT_CONTROL_BACKEND_INACTIVE'     => "checked",
            'CONTENT_CONTROL_BACKEND_ACTIVE'       => '',
            'CONTENT_CONTROL_BACKEND_DISPLAY'      => "none",
            'CONTENT_EXISTING_BACKEND_GROUPS'      => $existingBackendGroups,
            'CONTENT_ASSIGNED_BACKEND_GROUPS'      => '',
            'CONTENT_EXISTING_BLOCKS'                 => $blocks[1],
            'CONTENT_ASSIGNED_BLOCK'                 => $blocks[0],
            'CONTENT_TYPE_CHECKED_CONTENT'        => 'checked="checked"',
            'CONTENT_TYPE_CHECKED_REDIRECT'        => '',
            'CONTENT_TYPE_STYLE_CONTENT'        => 'style="display: block;"',
            'CONTENT_TYPE_STYLE_REDIRECT'        => 'style="display: none;"',
        ));
    }

    /**
    * @access private
    * @return string
    * @param pageId int
    * @desc ckecks if the page is protected (returns "checked") or not.
    */
    function _getPageProtectionStatus($pageId, $langId=0)
    {
        global $objDatabase;
        $langId = intval($langId);
        if($langId == 0){ $langId = $this->firstActiveLang; }
        $objResult = $objDatabase->SelectLimit("SELECT protected FROM ".DBPREFIX."content_navigation WHERE catid=".$pageId." AND `lang`=".$langId, 1);
        if ($objResult !== false && $objResult->RecordCount()>0 && isset($objResult->fields['protected']) && $objResult->fields['protected']) {
            return "checked";
        } else {
            return '';
        }
    }

    /**
     * @access private
     * @return array
     * @global    ADONewConnection
     * @param groupType string
     * @param pageId int (optional)
     * @desc gets all frontend or backend groups ( id,name ) from this page
     */
    function _getAssignedGroups($groupType, $pageId=0, $langId = 0)
    {
        global $objDatabase;
        $langId = intval($langId);
        if($langId == 0){ $langId = $this->firstActiveLang; }
        $arrAssignedGroups = array();

        if ($groupType != 'backend') {
            $groupType = 'frontend';
        }

        if (intval($pageId) != 0) {
            $objResult = $objDatabase->Execute("SELECT rights.group_id
                                                FROM ".DBPREFIX."content_navigation AS navigation,
                                                        ".DBPREFIX."access_group_dynamic_ids AS rights
                                                WHERE navigation.catid=".intval($pageId)." AND navigation.`lang`=".$langId."
                                                AND navigation.".$groupType."_access_id=rights.access_id");
            if ($objResult !== false) {
                while (!$objResult->EOF) {
                    array_push($arrAssignedGroups, $objResult->fields['group_id']);
                    $objResult->MoveNext();
                }
            }
        }
        return $arrAssignedGroups;
    }

    function _checkModificationPermission($pageId, $langId = 0)
    {
        global $objDatabase;
        $langId = intval($langId);
        if($langId == 0){ $langId = $this->firstActiveLang; }

        $objResult = $objDatabase->SelectLimit('SELECT backend_access_id FROM '.DBPREFIX.'content_navigation WHERE catid='.$pageId.' AND `lang`='.$langId.' AND backend_access_id!=0', 1);
        if ($objResult !== false) {
            if ($objResult->RecordCount() == 1) {
                if (!Permission::checkAccess($objResult->fields['backend_access_id'], 'dynamic')) {
                    header('Location: index.php?cmd=noaccess');
                    exit;
                } else {
                    return true;
                }
            } else {
                return false;
            }
        } else {
            header('Location: index.php?cmd=noaccess');
            exit;
        }
    }

	/**
	 * Returns true if alias functionality is enabled.
	 */
	function _is_alias_enabled() {
        global $objDatabase;
		$query = "
			SELECT setvalue
			FROM ".DBPREFIX."settings
			WHERE setmodule = 41 AND setname = 'aliasStatus'
		";
		if ($res = $objDatabase->SelectLimit($query, 1)) {
			return $res->fields['setvalue'];
		}
		return false;
	}

    /**
     * Content editing
     *
     * This method manages all aspects of editing of content
     *
     * @global  ADONewConnection
     * @global  array   Core language
     * @global  HTML_Template_Sigma
     */
    function showEditPage($pageId = '')
    {
        global $objDatabase, $_CORELANG, $objTemplate, $objLanguage;

        $objTemplate->addBlockfile('ADMIN_CONTENT', 'content_editor', 'content_editor.html');
        $this->pageTitle = $_CORELANG['TXT_EDIT_PAGE'];

        $existingBackendGroups = '';
        $existingGroups = '';
        $assignedGroups = '';
        $assignedBackendGroups = '';

        if (empty($pageId)) {
            $pageId = intval($_REQUEST['pageId']);
        }
        $langId = !empty($_REQUEST['langId']) ? intval($_REQUEST['langId']) : $this->firstActiveLang;

        if ($this->_checkModificationPermission($pageId, $langId)) {
            $_backendPermissions = true;
        } else {
            $_backendPermissions = false;
        }
        $objRS = $objDatabase->Execute('SELECT `lang` FROM `'.DBPREFIX.'content_navigation` WHERE `catid`='.$pageId);
        $arrContentLanguages = array();
        while(!$objRS->EOF){
            $arrContentLanguages[$objRS->fields['lang']] = array('id' => $objRS->fields['lang']);
            $objRS->MoveNext();
        }
        $langCount = 0;
        $activeLangCount = 0;
        foreach ($objLanguage->getLanguageArray() as $arrLang){
            $checked = '';
            $langCount++;
            if(array_key_exists($arrLang['id'], $arrContentLanguages)){
                if(++$activeLangCount == 1){
                    $tabClass = 'active';
                }else{
                    $tabClass = 'inactive';
                }
                $checked = 'checked="checked"';
                $objTemplate->setVariable(array(
                    'LANGUAGE_NAME'             => $arrLang['name'],
                    'LANGUAGE_TITLE'            => $arrLang['name'].'_'.$arrLang['id'],
                    'TAB_CLASS'                 => $tabClass,
                ));
                $objTemplate->parse('languages_tab');
            }

            if($arrLang['frontend'] == 1){
                $langActivateCheckbox = '<input type="checkbox" id="lang_'.$arrLang['lang'].'_'.$arrLang['id'].'" '.$checked.' />'
                                        .'<label for="lang_'.$arrLang['lang'].'_'.$arrLang['id'].'">'.$arrLang['name'].' ('.$arrLang['lang'].')</label>';
                $objTemplate->setVariable(array(
                    'LANGUAGE_CHECKBOX'         => $langActivateCheckbox,
                ));
                $objTemplate->parse('languages_activate');
            }
        }

        $objTemplate->setVariable(array(
            'LANGUAGE_COUNT'                   => $langCount+1,
            'TXT_LANGUAGES'                    => $_CORELANG['TXT_LANGUAGES'],
            'TXT_CONTENT_PLEASE_WAIT'          => $_CORELANG['TXT_CONTENT_PLEASE_WAIT'],
            'TXT_ERROR_COULD_NOT_GET_DATA'     => $_CORELANG['TXT_ERROR_COULD_NOT_GET_DATA'],
            'TXT_SUCCESS_PAGE_SAVED'           => $_CORELANG['TXT_SUCCESS_PAGE_SAVED'],
            'TXT_TARGET'                       => $_CORELANG['TXT_TARGET'],
            'TXT_MORE_OPTIONS'                 => $_CORELANG['TXT_MORE_OPTIONS'],
            'TXT_BASIC_DATA'                   => $_CORELANG['TXT_BASIC_DATA'],
            'TXT_FRONTEND_PERMISSION'          => $_CORELANG['TXT_FRONTEND_PERMISSION'],
            'TXT_RELATEDNESS'                  => $_CORELANG['TXT_BACKEND_RELATEDNESS'],
            'TXT_PAGE_NAME'                    => $_CORELANG['TXT_PAGE_NAME'],
            'TXT_MENU_NAME'                    => $_CORELANG['TXT_MENU_NAME'],
            'TXT_NEW_CATEGORY'                 => $_CORELANG['TXT_NEW_CATEGORY'],
            'TXT_VISIBLE'                      => $_CORELANG['TXT_VISIBLE'],
            'TXT_CONTENT_TITLE'                   => $_CORELANG['TXT_PAGETITLE'],
            'TXT_META_INFORMATIONS'            => $_CORELANG['TXT_META_INFORMATIONS'],
            'TXT_META_TITLE'                   => $_CORELANG['TXT_META_TITLE'],
            'TXT_META_DESCRIPTION'            => $_CORELANG['TXT_META_DESCRIPTION'],
            'TXT_META_KEYWORD'                 => $_CORELANG['TXT_META_KEYWORD'],
            'TXT_META_ROBOTS'                  => $_CORELANG['TXT_META_ROBOTS'],
            'TXT_CONTENT'                      => $_CORELANG['TXT_CONTENT'],
            'TXT_GENERAL_OPTIONS'              => $_CORELANG['TXT_GENERAL_OPTIONS'],
            'TXT_START_DATE'                   => $_CORELANG['TXT_START_DATE'],
            'TXT_END_DATE'                     => $_CORELANG['TXT_END_DATE'],
            'TXT_EXPERT_MODE'                  => $_CORELANG['TXT_EXPERT_MODE'],
            'TXT_MODULE'                      => $_CORELANG['TXT_MODULE'],
            'TXT_NO_MODULE'                    => $_CORELANG['TXT_NO_MODULE'],
            'TXT_REDIRECT'                     => $_CORELANG['TXT_REDIRECT'],
            'TXT_BROWSE'                    => $_CORELANG['TXT_BROWSE'],
  			'TXT_CONTENT_ASSIGN_BLOCK'         => $_CORELANG['TXT_CONTENT_ASSIGN_BLOCK'],
            'TXT_NO_REDIRECT'                  => '',
            'TXT_SOURCE_MODE'                  => $_CORELANG['TXT_SOURCE_MODE'],
            'TXT_CACHING_STATUS'               => $_CORELANG['TXT_CACHING_STATUS'],
            'TXT_THEMES'                       => $_CORELANG['TXT_THEMES'],
            'TXT_STORE'                        => $_CORELANG['TXT_SAVE'],
            'TXT_RECURSIVE_CHANGE'             => $_CORELANG['TXT_RECURSIVE_CHANGE'],
            'TXT_PROTECTION'                   => $_CORELANG['TXT_PROTECTION'],
            'TXT_PROTECTION_CHANGE'            => $_CORELANG['TXT_PROTECTION_CHANGE'],
            'TXT_RECURSIVE_CHANGE'             => $_CORELANG['TXT_RECURSIVE_CHANGE'],
            'TXT_GROUPS'                       => $_CORELANG['TXT_GROUPS'],
            'TXT_GROUPS_DEST'                  => $_CORELANG['TXT_GROUPS_DEST'],
            'TXT_SELECT_ALL'                   => $_CORELANG['TXT_SELECT_ALL'],
            'TXT_DESELECT_ALL'                 => $_CORELANG['TXT_DESELECT_ALL'],
            'TXT_ACCEPT_CHANGES'               => $_CORELANG['TXT_ACCEPT_CHANGES'],
            'TXT_PUBLIC_PAGE'                  => $_CORELANG['TXT_PUBLIC_PAGE'],
            'TXT_BACKEND_RELEASE'              => $_CORELANG['TXT_BACKEND_RELEASE'],
            'TXT_LIMIT_GROUP_RIGHTS'           => $_CORELANG['TXT_LIMIT_GROUP_RIGHTS'],
            'TXT_TARGET_BLANK'                 => $_CORELANG['TXT_TARGET_BLANK'],
            'TXT_TARGET_TOP'                   => $_CORELANG['TXT_TARGET_TOP'],
            'TXT_TARGET_PARENT'                => $_CORELANG['TXT_TARGET_PARENT'],
            'TXT_TARGET_SELF'                  => $_CORELANG['TXT_TARGET_SELF'],
            'TXT_OPTIONAL_CSS_NAME'            => $_CORELANG['TXT_OPTIONAL_CSS_NAME'],
            'TXT_DELETE'                       => $_CORELANG['TXT_DELETE'],
            'TXT_DELETE_MESSAGE'               => $_CORELANG['TXT_DELETE_PAGE_JS'],
            'TXT_CHANGELOG'                      => $_CORELANG['TXT_CHANGELOG'],
            'TXT_CHANGELOG_DATE'            => $_CORELANG['TXT_DATE'],
            'TXT_CHANGELOG_NAME'            => $_CORELANG['TXT_PAGETITLE'],
            'TXT_CHANGELOG_USER'            => $_CORELANG['TXT_USER'],
            'TXT_CHANGELOG_FUNCTIONS'        => $_CORELANG['TXT_FUNCTIONS'],
            'TXT_CHANGELOG_SUBMIT'               => $_CORELANG['TXT_MULTISELECT_SELECT'],
            'TXT_CHANGELOG_SUBMIT_DEL'         => $_CORELANG['TXT_MULTISELECT_DELETE'],
            'TXT_CATEGORY'                       => $_CORELANG['TXT_CATEGORY'],
            'TXT_DELETE_HISTORY_MSG'           => $_CORELANG['TXT_DELETE_HISTORY'],
            'TXT_DELETE_HISTORY_MSG_ALL'    => $_CORELANG['TXT_DELETE_HISTORY_ALL'],
            'TXT_ACTIVATE_HISTORY_MSG'         => $_CORELANG['TXT_ACTIVATE_HISTORY_MSG'],
            'TXT_TYPE_SELECT'                   => $_CORELANG['TXT_CONTENT_TYPE'],
            'TXT_CONTENT_TYPE_DEFAULT'         => $_CORELANG['TXT_CONTENT_TYPE_DEFAULT'],
            'TXT_CONTENT_TYPE_REDIRECT'        => $_CORELANG['TXT_CONTENT_TYPE_REDIRECT'],
            'TXT_CONTENT_TYPE_HELP'               => $_CORELANG['TXT_CONTENT_TYPE_HELP'],
            'TXT_NAVIGATION'                => $_CORELANG['TXT_NAVIGATION'],
            'TXT_ASSIGN_BLOCK'                   => $_CORELANG['TXT_ASSIGN_BLOCK'],
            'CONTENT_ALIAS_HELPTEXT'        => $_CORELANG['CONTENT_ALIAS_HELPTEXT'],
            'TXT_DEFAULT_ALIAS'        => $_CORELANG['TXT_DEFAULT_ALIAS'],
            'CONTENT_ALIAS_DISABLE'    => ($this->_is_alias_enabled() ? '' : 'style="display: none;"'),
			'TXT_ERROR_NO_TITLE'       => $_CORELANG['TXT_ERROR_NO_TITLE'],
			'TXT_BASE_URL'             => self::mkurl('/'),
        ));

        if (!$this->boolHistoryEnabled) {
            $objTemplate->hideBlock('changelog1');
            $objTemplate->hideBlock('changelog2');
        }

        if (!empty($pageId)) {
			$objResult = $objDatabase->SelectLimit("SELECT c.*,
														   a_s.url AS alias_url
                                                      FROM ".DBPREFIX."content AS c
													  LEFT OUTER JOIN ".DBPREFIX."module_alias_target AS a_t ON a_t.url = c.id
													  LEFT OUTER JOIN ".DBPREFIX."module_alias_source AS a_s
														  ON  (a_s.target_id = a_t.id AND a_s.lang_id = c.lang_id)
														AND a_s.isdefault = 1
                                                     WHERE c.id =".$pageId.' AND c.lang_id='.$langId, 1);

            if ($objResult !== false && $objResult->RecordCount()>0) {
                $contenthtml = $objResult->fields['content'];
                $contenthtml = preg_replace('/\{([A-Z0-9_-]+)\}/', '[[\\1]]' ,$contenthtml);
                if ($objResult->fields['expertmode'] == "y" ) {
                    $expertmodeValue = "checked";
                    $contenthtml = htmlspecialchars($contenthtml, ENT_QUOTES, CONTREXX_CHARSET);
                    $ed = get_wysiwyg_editor('html',$contenthtml, 'html');
                } else {
                    $expertmodeValue = '';
                    $ed = get_wysiwyg_editor('html',$contenthtml);

                }

                $robots = ($objResult->fields['metarobots'] == "index") ? "checked" : '';

                if (empty($objResult->fields['redirect'])) {
                    $objTemplate->setVariable(array(
                        'CONTENT_TYPE_CHECKED_CONTENT'        => 'checked="checked"',
                        'CONTENT_TYPE_CHECKED_REDIRECT'        => '',
                        'CONTENT_TYPE_STYLE_CONTENT'        => 'style="display: block;"',
                        'CONTENT_TYPE_STYLE_REDIRECT'        => 'style="display: none;"',
                    ));
                } else {
                    $objTemplate->setVariable(array(
                        'CONTENT_TYPE_CHECKED_CONTENT'        => '',
                        'CONTENT_TYPE_CHECKED_REDIRECT'        => 'checked="checked"',
                        'CONTENT_TYPE_STYLE_CONTENT'        => 'style="display: none;"',
                        'CONTENT_TYPE_STYLE_REDIRECT'        => 'style="display: block;"',
                    ));
                }

                // Blocks
                $blocks = array();
                $blocks = $this->getBlocks($pageId, $langId);



                $objTemplate->setVariable(array(
                    'CONTENT_FORM_ACTION'      => "update",
                    'CONTENT_TOP_TITLE'           => $_CORELANG['TXT_EDIT_PAGE'],
                    'CONTENT_CATID'            => $pageId,
                    'CONTENT_HTML'               => $ed,
					'CONTENT_ALIAS'              => htmlentities($objResult->fields['alias_url'], ENT_QUOTES, CONTREXX_CHARSET),
                    'CONTENT_TITLE_VAL'           => htmlentities($objResult->fields['title'], ENT_QUOTES, CONTREXX_CHARSET),
                    'CONTENT_DESC'               => htmlentities($objResult->fields['metadesc'], ENT_QUOTES, CONTREXX_CHARSET),
                    'CONTENT_META_TITLE'       => htmlentities($objResult->fields['metatitle'], ENT_QUOTES, CONTREXX_CHARSET),
                    'CONTENT_KEY'               => htmlentities($objResult->fields['metakeys'], ENT_QUOTES, CONTREXX_CHARSET),
                    'CONTENT_CSS_NAME'           => htmlentities($objResult->fields['css_name'], ENT_QUOTES, CONTREXX_CHARSET),
                    'CONTENT_ROBOTS'           => $robots,
                    'CONTENT_SHOW_EXPERTMODE'  => $expertmodeValue,
                    'CONTENT_EXISTING_BLOCKS'             => $blocks[1],
                    'CONTENT_ASSIGNED_BLOCK'             => $blocks[0],
                ));
                unset($ed);
                $redirect = $objResult->fields['redirect'];
            }
            $objResult = $objDatabase->SelectLimit("SELECT module,
                               lang,
                               startdate,
                               enddate,
                               displaystatus,
                               cachingstatus,
                               catname,
                               catid,
                               target,
                               cmd,
                               protected,
                               themes_id,
                               css_name
                          FROM ".DBPREFIX."content_navigation
                         WHERE catid = ".$pageId.' AND `lang`='.$langId, 1);

            if ($objResult !== false && $objResult->RecordCount()>0) {
                $displaystatus = '';
                $cachingStatus = ($objResult->fields['cachingstatus'] == 1) ? 'checked' : '';
                $moduleId = $objResult->fields['module'];
                $startDate = $objResult->fields['startdate'];
                $endDate = $objResult->fields['enddate'];
                $cmd = $objResult->fields['cmd'];
                $catname = htmlentities($objResult->fields['catname'], ENT_QUOTES, CONTREXX_CHARSET);
                $themesId = $objResult->fields['themes_id'];

                if ($objResult->fields['displaystatus'] == "on" ) {
                    $displaystatus = "checked";
                }
                $target = $objResult->fields['target'];
                //$target = "xyz";

                $objTemplate->setVariable(array(
                    'CONTENT_MENU_NAME'       => $catname,
                    'CONTENT_CAT_MENU'          => $this->getPageMenu($objResult->fields['catid'], $langId),
                    'CONTENT_CAT_MENU_NEW_PAGE'    => !Permission::checkAccess(127, 'static', true) ? 'disabled="disabled" style="color:graytext;"' : null,
                    'CONTENT_TARGET'          => $target,
                    'CONTENT_SHOW_CMD'        => $cmd,
                    'CONTENT_MODULE_MENU'     => $this->_getModuleMenu($moduleId),
                    'CONTENT_DISPLAYSTATUS'   => $displaystatus,
                    'CONTENT_CACHING_STATUS'  => $cachingStatus,
                    'CONTENT_STARTDATE'          => $startDate,
                    'CONTENT_CATID'           => $pageId,
                    'CONTENT_ENDDATE'          => $endDate,
                    'CONTENT_THEMES_MENU'     => $this->_getThemesMenu($themesId),
                    'NAVIGATION_CSS_NAME'        => htmlentities($objResult->fields['css_name'], ENT_QUOTES, CONTREXX_CHARSET),
                ));
            }

            // Frontend Groups
            ////////////////////////////
            $arrAssignedFrontendGroups=$this->_getAssignedGroups('frontend', $pageId, $langId);
            foreach ($this->arrAllFrontendGroups as $id => $name) {
                if (in_array($id, $arrAssignedFrontendGroups)) {
                    $assignedGroups .= '<option value="'.$id.'">'.$name."</option>\n";
                } else {
                    $existingGroups .= '<option value="'.$id.'">'.$name."</option>\n";
                }
            }

            $activeProtectionStatus = $this->_getPageProtectionStatus($pageId, $langId);
            if ($activeProtectionStatus=="checked") {
                $inactiveProtectionStatus = '';
                $displayStatus = "block";
            } else {
                $inactiveProtectionStatus = "checked";
                $displayStatus = "none";
            }

            // Backend Groups
            ////////////////////////////
            $arrAssignedBackendGroups=$this->_getAssignedGroups('backend', $pageId, $langId);
            $_backendPermissions = false;
            foreach ($this->arrAllBackendGroups as $id => $name) {
                if (in_array($id, $arrAssignedBackendGroups)) {
                    $assignedBackendGroups .= '<option value="'.$id.'">'.$name."</option>\n";
                    $_backendPermissions = true;
                } else {
                    $existingBackendGroups .= '<option value="'.$id.'">'.$name."</option>\n";
                }
            }

            if ($_backendPermissions) {
                $activeBackendStatus = "checked";
                $inactiveBackendStatus = '';
                $displayBackendStatus = "block";
            } else {
                $inactiveBackendStatus = "checked";
                $activeBackendStatus = '';
                $displayBackendStatus = "none";
            }

            $objTemplate->setVariable(array(
                //// frontend
                'CONTENT_EXISTING_GROUPS'     => $existingGroups,
                'CONTENT_ASSIGNED_GROUPS'     => $assignedGroups,
                'CONTENT_PROTECTION_ACTIVE'   => $activeProtectionStatus,
                'CONTENT_PROTECTION_INACTIVE' => $inactiveProtectionStatus,
                'CONTENT_PROTECTION_DISPLAY'  => $displayStatus,
                //// backend
                'CONTENT_EXISTING_BACKEND_GROUPS'  => $existingBackendGroups,
                'CONTENT_ASSIGNED_BACKEND_GROUPS'  => $assignedBackendGroups,
                'CONTENT_CONTROL_BACKEND_ACTIVE'   => $activeBackendStatus,
                'CONTENT_CONTROL_BACKEND_INACTIVE' => $inactiveBackendStatus,
                'CONTENT_CONTROL_BACKEND_DISPLAY'  => $displayBackendStatus
            ));
        }

        $objTemplate->setVariable(array(
            'TXT_NO_REDIRECT'        => $redirect,
            'CONTENT_CATID'          => $pageId
        ));

        // History (Changelog)
        ////////////////////////////
        if ($this->boolHistoryEnabled) {
            $objResult = $objDatabase->Execute('SELECT    id,
                                                        themesname
                                                FROM    '.DBPREFIX.'skins
                                                ');
            $arrThemes[0] = $_CORELANG['TXT_STANDARD'];
            while (!$objResult->EOF) {
                $arrThemes[$objResult->fields['id']] = $objResult->fields['themesname'];
                $objResult->MoveNext();
            }

            $objResult = $objDatabase->Execute('SELECT    id,
                                                        name
                                                FROM    '.DBPREFIX.'modules
                                            ');
            while (!$objResult->EOF) {
                $arrModules[$objResult->fields['id']] = $objResult->fields['name'];
                $objResult->MoveNext();
            }
            $arrModules[0] = '-';

            $objResult = $objDatabase->Execute('SELECT    group_id,
                                                        group_name
                                                FROM    '.DBPREFIX.'access_user_groups
                                            ');
            $arrGroups[0] = '-';
            while (!$objResult->EOF) {
                $arrGroups[$objResult->fields['group_id']] = $objResult->fields['group_name'];
                $objResult->MoveNext();
            }

            $objResult = $objDatabase->Execute('SELECT        navTable.id                    AS navID,
                                                            navTable.catid                AS navPageId,
                                                            navTable.is_active            AS navActive,
                                                            navTable.catname            AS navCatname,
                                                            navTable.username            AS navUsername,
                                                            navTable.changelog            AS navChangelog,
                                                            navTable.startdate            AS navStartdate,
                                                            navTable.enddate            AS navEnddate,
                                                            navTable.cachingstatus        AS navCachingStatus,
                                                            navTable.themes_id            AS navTheme,
                                                            navTable.cmd                AS navCMD,
                                                            navTable.module                AS navModule,
                                                            navTable.frontend_access_id    AS navFAccess,
                                                            navTable.backend_access_id    AS navBAccess,
                                                            conTable.title                AS conTitle,
                                                            conTable.metatitle            AS conMetaTitle,
                                                            conTable.metadesc            AS conMetaDesc,
                                                            conTable.metakeys            AS conMetaKeywords,
                                                            conTable.css_name            AS conCssName,
                                                            conTable.redirect            AS conRedirect,
                                                            conTable.expertmode            AS conExpertMode,
                                                            logTable.is_validated        AS logValidated
                                                FROM        '.DBPREFIX.'content_navigation_history AS navTable
                                                INNER JOIN    '.DBPREFIX.'content_history AS conTable
                                                ON            conTable.id = navTable.id
                                                INNER JOIN    '.DBPREFIX.'content_logfile AS logTable
                                                ON            logTable.history_id = navTable.id
                                                WHERE         navTable.catid='.$pageId.' AND navTable.lang='.$langId.' AND
                                                            logTable.is_validated="1"
                                                ORDER BY    navChangelog DESC
                                            ');
            if ($objResult->RecordCount() > 0) {
                $objContentTree = new ContentTree($langId);
                $intRowCount = 0;

                while (!$objResult->EOF) {
                    $strBackendGroups     = '';
                    $strFrontendGroups     = '';

                    $strTree             = '';
                    $boolCheck             = false;
                    $intPageCategory     = $pageId;
                    while(!$boolCheck) {
                        $arrCategory = $objContentTree->getThisNode($intPageCategory);
                        if ($arrCategory['parcat'] == 0) {
                            $boolCheck = true;
                        } else {
                            $intPageCategory = $arrCategory['parcat'];
                        }
                        $strTree = ' &gt; '.$arrCategory['catname'].$strTree;
                    }
                    $strTree = substr($strTree,6);

                    if ($objResult->fields['navBAccess'] != 0) {
                        $objSubResult = $objDatabase->Execute('
                            SELECT group_id
                              FROM '.DBPREFIX.'access_group_dynamic_ids
                             WHERE access_id='.$objResult->fields['navBAccess']);
                        while (!$objSubResult->EOF) {
                            $strBackendGroups .= $arrGroups[$objSubResult->fields['group_id']].',';
                            $objSubResult->MoveNext();
                        }
                        $strBackendGroups = substr($strBackendGroups,0,strlen($strBackendGroups)-1);
                    } else {
                        $strBackendGroups = $arrGroups[0];
                    }

                    if ($objResult->fields['navFAccess'] != 0) {
                        $objSubResult = $objDatabase->Execute('
                            SELECT group_id
                              FROM '.DBPREFIX.'access_group_dynamic_ids
                             WHERE access_id='.$objResult->fields['navFAccess']);
                        while (!$objSubResult->EOF) {
                            $strFrontendGroups .= $arrGroups[$objSubResult->fields['group_id']].',';
                            $objSubResult->MoveNext();
                        }
                        $strFrontendGroups = substr($strFrontendGroups,0,strlen($strFrontendGroups)-1);
                    } else {
                        $strFrontendGroups = $arrGroups[0];
                    }

                    $objTemplate->setVariable(array(
                        'TXT_CL_PAGETITLE'                =>    $_CORELANG['TXT_PAGETITLE'],
                        'TXT_CL_CACHINGSTATUS'            =>    $_CORELANG['TXT_CACHING_STATUS'],
                        'TXT_CL_META_TITLE'                =>    $_CORELANG['TXT_META_TITLE'],
                        'TXT_CL_META_DESCRIPTION'        =>    $_CORELANG['TXT_META_DESCRIPTION'],
                        'TXT_CL_META_KEYWORD'            =>    $_CORELANG['TXT_META_KEYWORD'],
                        'TXT_CL_CATEGORY'                =>    $_CORELANG['TXT_CATEGORY'],
                        'TXT_CL_START_DATE'                =>    $_CORELANG['TXT_START_DATE'],
                        'TXT_CL_END_DATE'                =>    $_CORELANG['TXT_END_DATE'],
                        'TXT_CL_THEMES'                    =>    $_CORELANG['TXT_THEMES'],
                        'TXT_CL_OPTIONAL_CSS_NAME'        =>    $_CORELANG['TXT_OPTIONAL_CSS_NAME'],
                        'TXT_CL_MODULE'                    =>    $_CORELANG['TXT_MODULE'],
                        'TXT_CL_REDIRECT'                =>    $_CORELANG['TXT_REDIRECT'],
                        'TXT_CL_SOURCE_MODE'            =>    $_CORELANG['TXT_SOURCE_MODE'],
                        'TXT_CL_FRONTEND'                =>    $_CORELANG['TXT_WEB_PAGES'],
                        'TXT_CL_BACKEND'                =>    $_CORELANG['TXT_ADMINISTRATION_PAGES'],
                    ));

                    $objTemplate->setVariable(array(
                        'CHANGELOG_ROWCLASS'        =>    ($objResult->fields['navActive']) ? 'rowWarn' : (($intRowCount % 2 == 0) ? 'row1' : 'row0'),
                        'CHANGELOG_CHECKBOX'        =>    ($objResult->fields['navActive']) ? '' : '<input type="checkbox" name="selectedChangelogId[]" id="selectedChangelogId" value="'.$objResult->fields['navID'].'" />',
                        'CHANGELOG_ACTIVATE'        =>    ($objResult->fields['navActive']) ? '<img src="images/icons/pixel.gif" width="16" border="0" alt="space" />' : '<a href="javascript:activateHistory(\''.$objResult->fields['navID'].'\');"><img src="images/icons/import.gif" alt="'.$_CORELANG['TXT_ACTIVATE_HISTORY'].'" title="'.$_CORELANG['TXT_ACTIVATE_HISTORY'].'" border="0" /></a>',
                        'CHANGELOG_DELETE'            =>    ($objResult->fields['navActive']) ? '<img src="images/icons/pixel.gif" width="16" border="0" alt="space" />' : '<a href="javascript:deleteHistory(\''.$objResult->fields['navID'].'\');"><img src="images/icons/delete.gif" alt="'.$_CORELANG['TXT_DELETE'].'" title="'.$_CORELANG['TXT_DELETE'].'" border="0" /></a>',
                        'CHANGELOG_ID'                =>    $objResult->fields['navID'],
                        'CHANGELOG_DATE'            =>    date('d.m.Y H:i:s',$objResult->fields['navChangelog']),
                        'CHANGELOG_USER'            =>    $objResult->fields['navUsername'],
                        'CHANGELOG_TITLE'            =>    stripslashes($objResult->fields['navCatname']),
                        'CHANGELOG_PAGETITLE'        =>    stripslashes($objResult->fields['conTitle']),
                        'CHANGELOG_METATITLE'        =>    stripslashes($objResult->fields['conMetaTitle']),
                        'CHANGELOG_METADESC'        =>    stripslashes($objResult->fields['conMetaDesc']),
                        'CHANGELOG_METAKEY'            =>    stripslashes($objResult->fields['conMetaKeywords']),
                        'CHANGELOG_CATEGORY'        =>    $strTree,
                        'CHANGELOG_STARTDATE'        =>    $objResult->fields['navStartdate'],
                        'CHANGELOG_ENDDATE'            =>    $objResult->fields['navEnddate'],
                        'CHANGELOG_THEME'            =>    stripslashes($arrThemes[$objResult->fields['navTheme']]),
                        'CHANGELOG_OPTIONAL_CSS'    =>    (empty($objResult->fields['conCssName'])) ? '-' : stripslashes($objResult->fields['conCssName']),
                        'CHANGELOG_CMD'                =>    (empty($objResult->fields['navCMD'])) ? '-' : $objResult->fields['navCMD'],
                        'CHANGELOG_SECTION'            =>    $arrModules[$objResult->fields['navModule']],
                        'CHANGELOG_REDIRECT'        =>    (empty($objResult->fields['conRedirect'])) ? '-' : $objResult->fields['conRedirect'],
                        'CHANGELOG_SOURCEMODE'        =>    strtoupper($objResult->fields['conExpertMode']),
                        'CHANGELOG_CACHINGSTATUS'    =>    ($objResult->fields['navCachingStatus'] == 1) ? 'Y' : 'N',
                        'CHANGELOG_FRONTEND'        =>    stripslashes($strFrontendGroups),
                        'CHANGELOG_BACKEND'            =>    stripslashes($strBackendGroups)
                    ));
                    $objTemplate->parse('showChanges');
                    $objResult->MoveNext();
                    $intRowCount++;
                }
            } else {
                $objTemplate->hideBlock('showChanges');
            }
        }
    }


    /**
    * Update page content
    */
    function updatePage()
    {
        global $objDatabase, $objTemplate, $_CORELANG;

        header('Content-Type: application/json');

        $objFWUser = FWUser::getFWUserObject();
        $pageId = intval($_POST['pageId']);
        $langId = intval($_POST['langId']);
        $this->_checkModificationPermission($pageId, $langId);

        if ($_POST['formContent_HistoryMultiAction'] == 'delete') {
            if (is_array($_POST['selectedChangelogId'])) {
                require_once ASCMS_CORE_PATH.'/ContentWorkflow.class.php';
                $objWorkflow = new ContentWorkflow();
                foreach ($_POST['selectedChangelogId'] as $intHistoryId) {
                    $objWorkflow->deleteHistory(intval($intHistoryId));
                }
            }
        }

        $expertmode = "n";
        if (isset($_POST['expertmode']) && $_POST['expertmode']== "y") {
            $expertmode = "y";
        }
        $cachingStatus = 0;
        if (isset($_POST['cachingstatus']) && intval($_POST['cachingstatus']) == 1) {
            $cachingStatus = 1;
        }
        $displaystatus = "off";
        if ($_POST['displaystatus']== "on") {
            $displaystatus = "on";
        }
        $robotstatus = "noindex";
        if ($_POST['robots']== "index") {
            $robotstatus = "index";
        }

        $langName = $_POST['langName'];
        $catname = contrexx_addslashes(strip_tags($_POST['newpage']));
        $contenthtml = contrexx_addslashes($_POST['html']);
        $contenthtml = preg_replace('/\[\[([A-Z0-9_-]+)\]\]/', '{\\1}' ,$contenthtml);
        $contenttitle = contrexx_addslashes($_POST['title']);
        $metatitle = contrexx_addslashes(strip_tags($_POST['metatitle']));
        $contentdesc = contrexx_addslashes(strip_tags($_POST['desc']));
        $contentkey = contrexx_addslashes(strip_tags($_POST['key']));
        $command = contrexx_addslashes(strip_tags($_POST['command']));
        if($this->checkParcat($pageId,$_POST['category'])) {
            $parcat = $_POST['category'];
        } else {
            $parcat = $pageId;
        }
        $moduleId  = intval($_POST['selectmodule']);
        $themesId = intval($_POST['themesId']);
        $startdate = (!preg_match('/\d{4}-\d{2}-\d{2}/',$_POST['startdate'])) ? '0000-00-00' : $_POST['startdate'];
        $enddate = (!preg_match('/\d{4}-\d{2}-\d{2}/',$_POST['enddate'])) ? '0000-00-00' : $_POST['enddate'];
        $currentTime = time();
        $cssName = contrexx_addslashes(strip_tags($_POST['cssName']));
        $cssNameNav = contrexx_addslashes(strip_tags($_POST['cssNameNav']));
        $redirect = (!empty($_POST['TypeSelection']) && $_POST['TypeSelection'] == 'redirect') ? contrexx_addslashes(strip_tags($_POST['redirectUrl'])) : '';
	    if(preg_match('/\b(?:mailto:)?([\w\d\._%+-]+@(?:[\w\d-]+\.)+[\w]{2,6})\b/i', $redirect, $match)){
            $redirect = 'mailto:'.$match[1];
            $_POST['redirectTarget'] = '_blank';
        }
        $redirectTarget    = in_array($_POST['redirectTarget'], $this->_arrRedirectTargets) ? $_POST['redirectTarget'] : '';

        $contenthtml=$this->_getBodyContent($contenthtml);

        //make sure the user is allowed to update the content
        if ($this->boolHistoryEnabled) {
            if ($this->boolHistoryActivate) {
                $boolDirectUpdate = true;
            } else {
                $boolDirectUpdate = false;
            }
        } else {
            $boolDirectUpdate = true;
        }

        if ($boolDirectUpdate) {
            $objDatabase->Execute("INSERT INTO ".DBPREFIX."content (id, lang_id, content, title, metatitle, metadesc,
                                                                    metakeys, css_name,metarobots,redirect,expertmode)
                                  VALUES      ('".$pageId."',
                                               '".$langId."',
                                               '".$contenthtml."',
                                               '".$contenttitle."',
                                               '".$metatitle."',
                                               '".$contentdesc."',
                                               '".$contentkey."',
                                               '".$cssName."',
                                               '".$robotstatus."',
                                               '".$redirect."',
                                               '".$expertmode."')
                ON DUPLICATE KEY UPDATE        id='".$pageId."',
                                               lang_id='".$langId."',
                                               content='".$contenthtml."',
                                               title='".$contenttitle."',
                                               metatitle='".$metatitle."',
                                               metadesc='".$contentdesc."',
                                               metakeys='".$contentkey."',
                                               css_name='".$cssName."',
                                               metarobots='".$robotstatus."',
                                              redirect='".$redirect."',
                                               expertmode='".$expertmode."'"
                                   /*  WHERE     id=".$pageId.'
                                     AND       lang_id='.$langId*/);
        }

        if ($parcat!=$pageId) {
            //create copy of parcat (for history)
            $intHistoryParcat = $parcat;
            if ($boolDirectUpdate) {
                $objDatabase->Execute("INSERT INTO  ".DBPREFIX."content_navigation (catid, parcat, catname, target, displaystatus, cachingstatus,
                                                username, changelog, cmd, lang, module, startdate, enddate, themes_id, css_name)
                                      VALUES (  '".$pageId."'
                                                '".$parcat."',
                                                '".$catname."',
                                                '".$redirectTarget."',
                                                '".$displaystatus."',
                                                '".$cachingStatus."',
                                                '".$objFWUser->objUser->getUsername()."',
                                                '".$currentTime."',
                                                '".$command."',
                                                '".$langId."',
                                                '".$moduleId."',
                                                '".$startdate."',
                                                '".$enddate."',
                                                '".$themesId."',
                                                '".$cssNameNav."')
                ON DUPLICATE KEY     UPDATE     catid='".$pageId."'
                                                parcat='".$parcat."',
                                                catname='".$catname."',
                                                target='".$redirectTarget."',
                                                displaystatus='".$displaystatus."',
                                                cachingstatus='".$cachingStatus."',
                                                username='".$objFWUser->objUser->getUsername()."',
                                                changelog='".$currentTime."',
                                                cmd='".$command."',
                                                lang='".$langId."',
                                                module='".$moduleId."',
                                                startdate='".$startdate."',
                                                enddate='".$enddate."',
                                                themes_id='".$themesId."',
                                                css_name='".$cssNameNav."'"
                                          /*WHERE catid=".$pageId.'
                                          AND `lang`='.$langId*/);
            }
        } else {
            //create copy of parcat (for history)
            if ($boolDirectUpdate) {
                   $objDatabase->Execute("INSERT INTO  ".DBPREFIX."content_navigation (catid, catname, target, displaystatus, cachingstatus,
                                                username, changelog, cmd, lang, module, startdate, enddate, themes_id, css_name)
                                        VALUES  ( '".$pageId."',
                                                  '".$catname."',
                                                  '".$redirectTarget."',
                                                  '".$displaystatus."',
                                                  '".$cachingStatus."',
                                                  '".$objFWUser->objUser->getUsername()."',
                                                  '".$currentTime."',
                                                  '".$command."',
                                                  '".$langId."',
                                                  '".$moduleId."',
                                                  '".$startdate."',
                                                  '".$enddate."',
                                                  '".$themesId."',
                                                  '".$cssNameNav."')
                   ON DUPLICATE KEY   UPDATE      catid='".$pageId."',
                                                  catname='".$catname."',
                                                  target='".$redirectTarget."',
                                                  displaystatus='".$displaystatus."',
                                                  cachingstatus='".$cachingStatus."',
                                                  username='".$objFWUser->objUser->getUsername()."',
                                                  changelog='".$currentTime."',
                                                  cmd='".$command."',
                                                  lang='".$langId."',
                                                  module='".$moduleId."',
                                                  startdate='".$startdate."',
                                                  enddate='".$enddate."',
                                                  themes_id='".$themesId."',
                                                  css_name='".$cssNameNav."'"
                                  /*    WHERE     catid=".$pageId.'
                                        AND       lang='.$langId*/);
            }
        }


        if($err = $this->_set_default_alias($pageId, $_POST['alias'], $langId)) {
			$objTemplate->setVariable("ALIAS_STATUS", $err);
		}

        if (isset($_POST['themesRecursive']) && !empty($_POST['themesRecursive'])) {
            $objNavbar = new ContentSitemap(0);
            $catidarray = $objNavbar->getCurrentSonArray($pageId);

            foreach ($catidarray as $value) {
                if ($boolDirectUpdate) {
                    $objDatabase->Execute("UPDATE ".DBPREFIX."content_navigation SET themes_id='".$themesId."' WHERE catid=".$value.' AND `lang`='.$langId);
                    $objDatabase->Execute("UPDATE ".DBPREFIX."content SET css_name='".$cssName."' WHERE id=".$value.' AND `lang`='.$langId);
                }
            }
        }

        if ($boolDirectUpdate) {
            $needsValidation = 'false';
//            $this->strOkMessage =$_CORELANG['TXT_DATA_RECORD_UPDATED_SUCCESSFUL'];
        } else {
            $needsValidation = 'true';
//            $this->strErrMessage[] = $_CORELANG['TXT_DATA_RECORD_UPDATED_SUCCESSFUL_VALIDATE'];
        }

        $protect = (empty($_POST['protection']) ? false : true);
        $assignedGroups = isset($_POST['assignedGroups']) ? $_POST['assignedGroups'] : '';
        $recursive = isset($_POST['recursive']) ? (bool) $_POST['recursive'] : false;
        $this->_setPageProtection($pageId, $parcat, $protect, $assignedGroups, 'frontend', $recursive, $langId);

        $protect = (empty($_POST['backendPermission']) ? false : true);
        $assignedBackendGroups = isset($_POST['assignedBackendGroups']) ? $_POST['assignedBackendGroups'] : '';
        $backendInherit = isset($_POST['backendInherit']) ? (bool) $_POST['backendInherit'] : false;
        $this->_setPageProtection($pageId, $parcat, $protect, $assignedBackendGroups, 'backend', $backendInherit, $langId);

        //write caching-file, delete exisiting cache-files
        $objCache = new Cache();
        $objCache->writeCacheablePagesFile();

        // write xml sitemap
        if (($result = XMLSitemap::write()) !== true) {
            $this->strErrMessage[] = $result;
        }

        if (empty($command) && intval($moduleId) == 0) {
            $objCache->deleteSingleFile($pageId);
        } else {
            $objCache->deleteAllFiles();
        }

        //create backup for history
        if ($this->boolHistoryEnabled) {
            $objResult = $objDatabase->Execute('SELECT    parcat,
                                                        displayorder,
                                                        protected,
                                                        frontend_access_id,
                                                        backend_access_id
                                                FROM    '.DBPREFIX.'content_navigation
                                                WHERE    catid='.$pageId.'
                                                AND     `lang`='.$langId.'
                                                LIMIT    1
                                            ');
            if (!isset($intHistoryParcat)) {
                $intHistoryParcat = $objResult->fields['parcat'];
            }

            if ($boolDirectUpdate) {
                $objDatabase->Execute(/*'INSERT INTO '.DBPREFIX.'content_navigation_history (catid, lang, is_active)
                                        VALUES (catid, lang, "0")
                    ON DUPLICATE KEY */ 'UPDATE    '.DBPREFIX.'content_navigation_history
                                        SET        is_active="0"
                                        WHERE    catid='.$pageId.' AND `lang`='.$langId);
            }

            $objDatabase->Execute('    INSERT
                                    INTO    '.DBPREFIX.'content_navigation_history
                                    SET        is_active="'.(($boolDirectUpdate) ? 1 : 0).'",
                                            catid='.$pageId.',
                                            parcat="'.$intHistoryParcat.'",
                                            catname="'.$catname.'",
                                            target="'.$redirectTarget.'",
                                            displayorder='.intval($objResult->fields['displayorder']).',
                                            displaystatus="'.$displaystatus.'",
                                            cachingstatus="'.$cachingStatus.'",
                                            username="'.$objFWUser->objUser->getUsername().'",
                                            changelog="'.$currentTime.'",
                                            cmd="'.$command.'",
                                            lang="'.$langId.'",
                                            module="'.$moduleId.'",
                                            startdate="'.$startdate.'",
                                            enddate="'.$enddate.'",
                                            protected='.intval($objResult->fields['protected']).',
                                            frontend_access_id='.intval($objResult->fields['frontend_access_id']).',
                                            backend_access_id='.intval($objResult->fields['backend_access_id']).',
                                            themes_id="'.$themesId.'",
                                            css_name="'.$cssNameNav.'"
                                       ');
            $intHistoryId = $objDatabase->insert_id();
            $objDatabase->Execute('    INSERT
                                    INTO    '.DBPREFIX.'content_history
                                    SET     id='.$intHistoryId.',
                                            page_id='.$pageId.',
                                            lang_id='.$langId.',
                                               content="'.$contenthtml.'",
                                               title="'.$contenttitle.'",
                                               metatitle="'.$metatitle.'",
                                            metadesc="'.$contentdesc.'",
                                               metakeys="'.$contentkey.'",
                                               css_name="'.$cssName.'",
                                               metarobots="'.$robotstatus.'",
                                               redirect="'.$redirect.'",
                                              expertmode="'.$expertmode.'"'
                                    );
            $objDatabase->Execute('    INSERT
                                    INTO    '.DBPREFIX.'content_logfile
                                    SET        action="update",
                                            history_id='.$intHistoryId.',
                                            is_validated="'.(($boolDirectUpdate) ? 1 : 0).'"
                                ');
        }
        $this->modifyBlocks($_POST['assignedBlocks'], $pageId, $langId);
        die('{pageId: '.$pageId.', langName:"'.$langName.'", needsValidation: '.$needsValidation.'}');
    }

    static function mkurl($absolute_local_path) {
        global $_CONFIG;
        return ASCMS_PROTOCOL."://".$_CONFIG['domainUrl'].($_SERVER['SERVER_PORT'] == 80
            ? ""
            : ":".intval($_SERVER['SERVER_PORT'])
        ).ASCMS_PATH_OFFSET.$absolute_local_path;
    }


    /**
    * Adds a new page
    *
    * @global    ADONewConnection
    * @global    array      Core language
    * @global    HTML_Template_Sigma
    */
    function addPage()
    {
        global $objDatabase, $_CORELANG, $objTemplate;
        if (!empty($_POST['category'])) {
            $parcat = intval($_POST['category']);
        } else {
            Permission::checkAccess(127, 'static');
            $parcat = 0;
        }
        header('Content-Type: application/json');

        $pageId = intval($_POST['pageId']);
        $langId = intval($_POST['langId']);
        $langName = $_POST['langName'];
        $displaystatus = ( $_POST['displaystatus'] == "on" ) ? "on" : "off";
        $cachingstatus = (intval($_POST['cachingstatus']) == 1) ? 1 : 0;
        $expertmode = ( !empty($_POST['expertmode']) && $_POST['expertmode'] == "y" ) ? "y" : "n";
        $robotstatus = ( $_POST['robots'] == "index" ) ? "index" : "noindex";

        $catname =     strip_tags(contrexx_addslashes($_POST['newpage']));
        // Never used
        //$section = strip_tags(contrexx_addslashes($_POST['section']));
        $command =     strip_tags(contrexx_addslashes($_POST['command']));
        $contenthtml= contrexx_addslashes($_POST['html']);
        $contenthtml = preg_replace('/\[\[([A-Z0-9_-]+)\]\]/', '{\\1}' ,$contenthtml);
        $contenttitle = contrexx_addslashes($_POST['title']);
        $metatitle = contrexx_addslashes($_POST['metatitle']);
        $contentdesc =     strip_tags(contrexx_addslashes($_POST['desc']));
        $contentkey =     strip_tags(contrexx_addslashes($_POST['key']));

        $redirect = contrexx_addslashes(strip_tags($_POST['redirectUrl']));
        $redirectTarget = in_array($_POST['redirectTarget'], $this->_arrRedirectTargets) ? $_POST['redirectTarget'] : '';
        $cssName = contrexx_addslashes(strip_tags($_POST['cssName']));
        $cssNameNav = contrexx_addslashes(strip_tags($_POST['cssNameNav']));
        $modul = intval($_POST['selectmodule']);
        $startdate = (!preg_match('/\d{4}-\d{2}-\d{2}/',$_POST['startdate'])) ? '0000-00-00' : $_POST['startdate'];
        $enddate = (!preg_match('/\d{4}-\d{2}-\d{2}/',$_POST['enddate'])) ? '0000-00-00' : $_POST['enddate'];
        $themesId = intval($_POST['themesId']);
        $currentTime = time();

        if (!$this->_homeModuleCheck($modul,$command,'')==true) {
            $homemessage=$this->strOkMessage;
            $modul=intval($this->setModule);
            $command=$this->setCmd;
        }

        // Check if Expertmode is set for modules
        // module 15 is the home module (buggy test)
        if ($modul!=0 && $modul!=15) {
            $expertmode = 'y';
        }

        $protected=0;
        $objResult = $objDatabase->Execute("
            SELECT protected, themes_id, backend_access_id
              FROM ".DBPREFIX."content_navigation
             WHERE catid=".$parcat
        );
        if ($objResult !== false && $objResult->RecordCount()>0) {
            if ($objResult->fields['protected']) {
                $protected=1;
            }
            if ($themesId == 0) {
                $themesId = $objResult->fields['themes_id'];
            }
            $backendAccessId = $objResult->fields['backend_access_id'];
            if ($backendAccessId) {
                Permission::checkAccess($backendAccessId, 'dynamic');
            }
        }

        $objFWUser = FWUser::getFWUserObject();
        $contentredirect = $redirect;
        $contenthtml=$this->_getBodyContent($contenthtml);
        $q1 = "
                INSERT INTO ".DBPREFIX."content_navigation (
                catid, parcat, catname, target, displayorder,
                displaystatus, cachingstatus,
                username, changelog,
                cmd, lang, module,
                startdate, enddate,
                protected, themes_id, css_name
            ) VALUES (
                ".$pageId.", ".$parcat.", '".$catname."', '".$redirectTarget."', '1',
                '".$displaystatus."', '".$cachingstatus."',
                '".$objFWUser->objUser->getUsername()."', '".$currentTime."',
                '".$command."', '".$langId."', '".$modul."',
                '".$startdate."', '".$enddate."',
                '".$protected."', '".$themesId."', '".$cssNameNav."'
            )
ON DUPLICATE KEY
UPDATE          catid=".$pageId.",
                parcat=".$parcat.",
                catname='".$catname."',
                target='".$redirectTarget."',
                displayorder='1',
                displaystatus='".$displaystatus."',
                cachingstatus='".$cachingstatus."',
                username='".$objFWUser->objUser->getUsername()."',
                changelog='".$currentTime."',
                cmd='".$command."',
                lang='".$langId."',
                module='".$modul."',
                startdate='".$startdate."',
                enddate='".$enddate."',
                protected='".$protected."',
                themes_id='".$themesId."',
                css_name='".$cssNameNav."'

        ";
        $objDatabase->Execute($q1);

        if($err = $this->_set_default_alias($pageId, $_POST['alias'], $langId)) {
			$objTemplate->setVariable("ALIAS_STATUS", $err);
		}

        $q2 = "
            INSERT INTO ".DBPREFIX."content (
                id, lang_id,
                content, title, metatitle,
                metadesc, metakeys, css_name,
                metarobots, redirect, expertmode
            ) VALUES (
                $pageId, $langId,
                '".$contenthtml."', '".$contenttitle."', '".$metatitle."',
                '".$contentdesc."', '".$contentkey."', '".$cssName."',
                '".$robotstatus."', '".$contentredirect."', '".$expertmode."'
            )
ON DUPLICATE KEY
        UPDATE  id=".$pageId.",
                lang_id=".$langId.",
                content='".$contenthtml."',
                title='".$contenttitle."',
                metatitle='".$metatitle."',
                metadesc='".$contentdesc."',
                metakeys='".$contentkey."',
                css_name='".$cssName."',
                metarobots='".$robotstatus."',
                redirect='".$contentredirect."',
                expertmode='".$expertmode."'
        ";
        if ($objDatabase->Execute($q2) !== false) {
            $this->strOkMessage = $_CORELANG['TXT_DATA_RECORD_ADDED_SUCCESSFUL'];
            //frontend permissions
            $protect = (empty($_POST['protection']) ? false : true);
            $_POST['recursive'] = !empty($_POST['recursive']) ? $_POST['recursive'] : false;
            $_POST['assignedGroups'] = !empty($_POST['assignedGroups']) ? $_POST['assignedGroups'] : array();
            $this->_setPageProtection($pageId, $parcat, $protect, $_POST['assignedGroups'], 'frontend', $_POST['recursive'], $langId);

            //backend permissions
            $protect = (empty($_POST['backendPermission']) ? false : true);
            $_POST['backendInherit'] = !empty($_POST['backendInherit']) ? $_POST['backendInherit'] : false;
            $_POST['assignedBackendGroups'] = !empty($_POST['assignedBackendGroups']) ? $_POST['assignedBackendGroups'] : array();
            $this->_setPageProtection($pageId, $parcat, $protect, $_POST['assignedBackendGroups'], 'backend', $_POST['backendInherit'], $langId);
            $this->strOkMessage .= $homemessage;

            // Write cache file if enabled
            $objCache = new Cache();
            $objCache->writeCacheablePagesFile();

            // write xml sitemap
            if (($result = XMLSitemap::write()) !== true) {
                $this->strErrMessage[] = $result;
            }

            // Create backup for history
            if (!$this->boolHistoryActivate && $this->boolHistoryEnabled) {
                // User is not allowed to validate, so set if "off"
                $objDatabase->Execute('
                    UPDATE '.DBPREFIX.'content_navigation
                                        SET        is_validated="0",
                                                activestatus="0"
                     WHERE catid='.$pageId.' AND `lang`='.$langId
                );
                $this->strOkMessage = $_CORELANG['TXT_DATA_RECORD_ADDED_SUCCESSFUL_VALIDATE'];
            }

            if ($this->boolHistoryEnabled) {
                $objResult = $objDatabase->Execute('
                    SELECT displayorder, protected,
                           frontend_access_id, backend_access_id
                                                    FROM    '.DBPREFIX.'content_navigation
                                                    WHERE    catid='.$pageId.'
                                                ');
                $objDatabase->Execute('
                    INSERT INTO '.DBPREFIX.'content_navigation_history
                                        SET        is_active="1",
                                                catid='.$pageId.',
                                                parcat="'.$parcat.'",
                                                   catname="'.$catname.'",
                                                   target="'.$redirectTarget.'",
                                                   displayorder='.intval($objResult->fields['displayorder']).',
                                                   displaystatus="'.$displaystatus.'",
                                                   cachingstatus="'.$cachingstatus.'",
                                                   username="'.$objFWUser->objUser->getUsername().'",
                                                   changelog="'.$currentTime.'",
                                                   cmd="'.$command.'",
                                                   lang="'.$langId.'",
                                                   module="'.$modul.'",
                                                   startdate="'.$startdate.'",
                                                   enddate="'.$enddate.'",
                                                   protected='.intval($objResult->fields['protected']).',
                                                   frontend_access_id='.intval($objResult->fields['frontend_access_id']).',
                                                   backend_access_id='.intval($objResult->fields['backend_access_id']).',
                                                   themes_id="'.$themesId.'",
                                                   css_name="'.$cssNameNav.'"
                                           ');
                $intHistoryId = $objDatabase->insert_id();
                $objDatabase->Execute('
                    INSERT INTO '.DBPREFIX.'content_logfile
                    SET action="new",
                        history_id='.$intHistoryId.',
                        is_validated="'.($this->boolHistoryActivate ? 1 : 0).'"');
                $objDatabase->Execute('
                    INSERT INTO '.DBPREFIX.'content_history
                                        SET     id='.$intHistoryId.',
                                                page_id='.$pageId.',
                                                lang_id='.$langId.',
                                                   content="'.$contenthtml.'",
                                                   title="'.$contenttitle.'",
                                                   metatitle="'.$metatitle.'",
                                                metadesc="'.$contentdesc.'",
                                                   metakeys="'.$contentkey.'",
                                                   css_name="'.$cssName.'",
                                                   metarobots="'.$robotstatus.'",
                                                   redirect="'.$contentredirect.'",
                                                  expertmode="'.$expertmode.'"'
                                        );
            }
            $this->modifyBlocks($_POST['assignedBlocks'], $pageId, $langId);
            die('{pageId: '.$pageId.', langName: "'.$langName.'"}');
        } else {
            die('{pageId: -1}');
        }
        return $pageId;
    }


    /**
     * Delete page content (with all subcategories!)
     *
     * @global    ADONewConnection
     * @global    array      Core language
     */
    function deleteContent($pageId)
    {
        global $objDatabase, $_CORELANG;
        $pageId = intval($pageId);
        if ($pageId != 0) {
            $objResult = $objDatabase->Execute('
                SELECT catid
                                                FROM    '.DBPREFIX.'content_navigation
                 WHERE parcat='.$pageId.' AND `lang`='.$this->langId
            );
            if ($objResult->RecordCount() > 0) {
                while (!$objResult->EOF) {
                    $this->deleteContent($objResult->fields['catid']);
                    $objResult->MoveNext();
                }
            }

            $objResult = $objDatabase->Execute("
                SELECT parcat, catid, module
                          FROM ".DBPREFIX."content_navigation
                         WHERE ( parcat=".$pageId."
                            OR catid=".$pageId.")
                            AND `lang`=".$this->langId."
              ORDER BY catid
            ");

            if ($objResult !== false && $objResult->RecordCount()>0) {
                $moduleId = $objResult->fields['module'];
                // needed for recordcount
                while (!$objResult->EOF) {
                    $objResult->MoveNext();
                }
                if ($objResult->RecordCount()>1) {
                    $this->strErrMessage[] =
                        $_CORELANG['TXT_PAGE_NOT_DELETED_DELETE_SUBCATEGORIES_FIRST'];
                } else {
                    if (in_array($moduleId, $this->_requiredModules)) {
                        $this->strErrMessage[] =
                            $_CORELANG['TXT_NOT_DELETE_REQUIRED_MODULES'];
                    } else {
                        if ($this->boolHistoryEnabled) {
                            $objResult = $objDatabase->Execute('
                                       SELECT id
                                            FROM     '.DBPREFIX.'content_navigation_history
                                            WHERE    is_active="1" AND
                                       catid='.$pageId.' AND `lang`='.$this->langId
                            );
                            $objDatabase->Execute('
                                INSERT INTO '.DBPREFIX.'content_logfile
                                                    SET        action="delete",
                                                            history_id='.$objResult->fields['id'].',
                                       is_validated="'.($this->boolHistoryActivate ? 1 : 0).'"
                                                ');
                            $objDatabase->Execute('
                                UPDATE '.DBPREFIX.'content_navigation_history
                                                    SET        changelog='.time().'
                                                    WHERE    catid='.$pageId.' AND
                                                            is_active="1"
                                                    AND     `lang`='.$this->langId.'
                                                ');
                        }

                        $boolDelete = true;
                        if ($this->boolHistoryEnabled) {
                            if (!$this->boolHistoryActivate) {
                                $boolDelete = false;
                                $this->strOkMessage =
                                    $_CORELANG['TXT_DATA_RECORD_DELETED_SUCCESSFUL_VALIDATE'];
                            }
                        }

                        if ($boolDelete) {
                            $q1 = "DELETE FROM ".DBPREFIX."content WHERE id=".$pageId.' AND lang_id='.$this->langId;
                            $q2 = "DELETE FROM ".DBPREFIX."content_navigation WHERE catid=".$pageId.' AND `lang`='.$this->langId;

                            if ($objDatabase->Execute($q1) === false
                             || $objDatabase->Execute($q2) === false) {
                                $this->strErrMessage[] =
                                    $_CORELANG['TXT_DATABASE_QUERY_ERROR'];
                            } else {
                                 $this->strOkMessage =
                                    $_CORELANG['TXT_DATA_RECORD_DELETED_SUCCESSFUL'];

                                // write cache file if enabled
                                $objCache = new Cache();
                                $objCache->writeCacheablePagesFile();

                                // write xml sitemap
                                if (($result = XMLSitemap::write()) !== true) {
                                    $this->strErrMessage[] = $result;
                                }
                            }
                        }
                    }
                }
            }
        }
    }

    /**
    * Add page to repository
    *
    * @global    ADONewConnection
    * @global    array      Core language
    */
    function addToRepository()
    {
        global $objDatabase, $_CORELANG;

        $pageId = intval($_GET['pageid']);
        if ($pageId != '') {
            $objNavbar= new ContentSitemap(0);
            $catidarray=$objNavbar->getCurrentSonArray($pageId);
            array_unshift ($catidarray,$pageId);
            $paridarray = array();
            $justonce = false;

            $objModule = $objDatabase->SelectLimit('SELECT `module` FROM `'.DBPREFIX.'content_navigation` WHERE `lang_id`='.$this->langId.' AND `catid` = '.$pageId, 1);
            if ($objModule) {
                $moduleId = $objModule->fields['module'];
            }
            foreach ($catidarray as $value) {
// TODO: $arrSkipPages is set too late (see below)!
                $objResult = $objDatabase->Execute("
                    SELECT *
                      FROM ".DBPREFIX."content_navigation,
                           ".DBPREFIX."content
                     WHERE id=catid
                       AND catid=$value
                       AND module IN (0,$moduleId)".
                      (count($arrSkipPages)
                          ? " AND parcat NOT IN (".implode(',', $arrSkipPages).")"
                          : ''
                      )
                );
                if ($objResult !== false && $objResult->RecordCount() > 0) {
                    $repository['displayorder'] = $objResult->fields['displayorder'];
                    $repository['displaystatus'] = $objResult->fields['displaystatus'];
                    $repository['cmd'] = $objResult->fields['cmd'];
                    $repository['lang'] = $objResult->fields['lang'];
                    $repository['content'] = $objResult->fields['content'];
                    $repository['title'] = $objResult->fields['title'];
                    $repository['expertmode'] = $objResult->fields['expertmode'];
                    $repository['moduleid'] = intval($objResult->fields['module']);
                    $repository['lang'] = intval($objResult->fields['lang']);
                    $repository['username'] = $objResult->fields['username'];

                    if (!empty($paridarray[$objResult->fields['parcat']])) {
                        $repository['parid']= $paridarray[$objResult->fields['parcat']];
                    } else {
                        if (!$justonce) {
                            $objDatabase->Execute("
                                DELETE FROM ".DBPREFIX."module_repository
                                 WHERE moduleid='".$repository['moduleid']."'
                                 AND   lang    ='".$repository['lang']."'
                             ");
                        }
                        $justonce=true;
                        $repository['parid']= 0;
                    }
                    $query = "
                        INSERT INTO ".DBPREFIX."module_repository
                           SET displayorder ='".$repository['displayorder']."',
                                        displaystatus ='".$repository['displaystatus']."',
                                        username = '".addslashes($repository['username'])."',
                                        cmd = '".addslashes($repository['cmd'])."',
                                        content = '".addslashes($repository['content'])."',
                                        title = '".addslashes($repository['title'])."',
                                        expertmode ='".$repository['expertmode']."',
                                        moduleid ='".$repository['moduleid']."',
                                        lang ='".$repository['lang']."',
                                        parid ='".$repository['parid']."'";
                    if ($objDatabase->Execute($query) === false) {
                        $this->strErrMessage[] = $_CORELANG['TXT_DATABASE_QUERY_ERROR'];
                    }
                    $paridarray[$value] = $objDatabase->Insert_ID();
                } else {
                    $arrSkipPages[] = $value;
                }
            }
            $this->strOkMessage = $_CORELANG['TXT_DATA_RECORD_UPDATED_SUCCESSFUL'];
        }
    }

    /**
     * Verify the URI string provided and fix it if necessary.
    *
     * If the string argument is too short to be a valid URI,
     * returns the empty string.  The minimum length is five
     * characters, like in "sf.tv".
     * If the string does not start with one of the supported
     * protocols (http, https, ftp) plus the "://" separator,
     * prpends it with "http://" and returns the result.
     * @param   string  $redirect   Proposed redirect string
     * @return  string              Fixed redirect string
    */
    function checkRedirectUrl($redirect)
    {
        if (empty($redirect) || strlen($redirect) < 5) {
            return '';
            }
        if (!preg_match('/^(?:https?|ftp)\:\/\//', $redirect)) {
            return 'http://'.$redirect;
        }
        return $redirect;
    }


    /**
    * Gets the search option menus string
    *
    * @global    ADONewConnection
    * @param     string     optional $selectedOption
    * @return    string     $modulesMenu
    */
    function _getModuleMenu($selectedOption='')
    {
        global $objDatabase;

        $strMenu = '';
        $q = "SELECT * FROM ".DBPREFIX."modules WHERE 1 AND id<>0 ORDER BY id";
        $objResult = $objDatabase->Execute($q);
        if ($objResult !== false) {
            while (!$objResult->EOF) {
                $selected = ($selectedOption==$objResult->fields['id']) ? "selected" : '';
                $strMenu .=
                    '<option value="'.$objResult->fields['id'].'" '.
                    $selected.'>'.$objResult->fields['name']."</option>\n";
                $objResult->MoveNext();
            }
        }
        return $strMenu;
    }

    /**
    * Gets the body-content
    *
    * this function removes all the content outside the body tags
    *
    * @param     string     $fullContent      HTML-Content with more than BODY
    * @return    string     $content          HTML-Content between BODY-Tag
    */
    function _getBodyContent($fullContent)
    {
        $posBody=0;
        $posStartBodyContent=0;
        $arrayMatches = array();
        $res=preg_match_all("/<body[^>]*>/i", $fullContent, $arrayMatches);
        if ($res) {
            $bodyStartTag = $arrayMatches[0][0];
            // Position des Start-Tags holen
            $posBody = strpos($fullContent, $bodyStartTag, 0);
            // Beginn des Contents ohne Body-Tag berechnen
            $posStartBodyContent = $posBody + strlen($bodyStartTag);
        }
        $posEndTag=strlen($fullContent);
        $res = preg_match_all('/\<\/body\>/i', $fullContent, $arrayMatches);
        if ($res) {
            $bodyEndTag=$arrayMatches[0][0];
            // Position des End-Tags holen
            $posEndTag = strpos($fullContent, $bodyEndTag, 0);
            // Content innerhalb der Body-Tags auslesen
         }
         $content = substr($fullContent, $posStartBodyContent, $posEndTag  - $posStartBodyContent);
         return $content;
    }


    function _homeModuleCheck($section,$cmd,$pageId)
    {
        global $objDatabase, $_CORELANG;
        $lang=$this->langId;
        $section=intval($section);

        $objResult = $objDatabase->Execute("SELECT id FROM ".DBPREFIX."modules WHERE name='home'");
        if ($objResult !== false && $objResult->RecordCount()>0) {
            $homeModuleId = intval($objResult->fields['id']);
        } else {
            $this->strErrMessage[] = $_CORELANG['TXT_DATABASE_QUERY_ERROR'];
            return false;
        }

        $objResult = $objDatabase->Execute("SELECT catid FROM ".DBPREFIX."content_navigation WHERE  `lang`=".$lang." AND module=".$homeModuleId);
        if ($objResult !== false && $objResult->RecordCount()>0) {
            $objResult = $objDatabase->Execute("SELECT m.name
                              FROM ".DBPREFIX."content_navigation AS n,
                                   ".DBPREFIX."modules AS m
                             WHERE n.`lang`=".$lang."
                               AND n.module=".$section."
                               AND n.cmd='".$cmd."'
                               AND n.module>0
                               AND n.catid<>'".$pageId."'");

            if ($objResult !== false) {
                if ($objResult->RecordCount()>0) {
                    $sectionName = $objResult->fields['m.name'];
                    $this->strErrMessage[] = $_CORELANG['TXT_PAGE_WITH_SAME_MODULE_EXIST']." ".$sectionName." ".$cmd;
                    $this->setModule=$section;
                    $this->setCmd=$cmd+1;
                    return false;
                } else {
                    return true;
                }
            } elseif ($section==$homeModuleId) {
                return true;
            } else {
                $this->strOkMessage = $_CORELANG['TXT_CREATE_HOME_MODULE'];
                $this->setModule=$homeModuleId;
                return false;
            }
        }
        $this->strErrMessage[] = $_CORELANG['TXT_DATABASE_QUERY_ERROR'];
        return  false;
    }


    /*
    * Get dropdown menue
    *
    * Gets back a dropdown menu like  <option value='catid'>Catname</option>
    * @global   ADONewConnection
    * @param    integer  $selectedid
    * @return   string   $result
    */
    function getPageMenu($selectedid = 0, $langId = 0)
    {
        global $objDatabase;
        $langId = intval($langId);
        if($langId == 0){
            $langId = $this->firstActiveLang;
        }
        $objResult = $objDatabase->Execute("
            SELECT catid, parcat, catname, backend_access_id
                                            FROM ".DBPREFIX."content_navigation
                                            WHERE `lang`=".$langId."
          ORDER BY parcat ASC, displayorder ASC
        ");
        if ($objResult === false) {
            return "content::navigation() database error";
        }
        while (!$objResult->EOF) {
            $this->_navtable[$objResult->fields['parcat']][$objResult->fields['catid']] = array(
                'name'        => htmlentities($objResult->fields['catname'], ENT_QUOTES, CONTREXX_CHARSET),
                'access_id'    => $objResult->fields['backend_access_id']
            );
            $objResult->MoveNext();
        }
        $result = $this->_getNavigationMenu(0, 0, $selectedid);
        return $result;
    }


    /*
    * Do navigation dropdown
    *
    * @param    integer  $parcat
    * @param    integer  $level
    * @param    integer  $selectedid
    * @return   string   $result
    */
    function _getNavigationMenu($parcat=0,$level,$selectedid)
    {
        $result='';

        $list = $this->_navtable[$parcat];
        if (is_array($list)) {
            while (list($key,$val) = each($list)) {
                $output = str_repeat('...', $level);
                $selected = '';
                if ($selectedid==$key) {
                    $selected= 'selected="selected"';
                }
                $result.= '<option value="'.$key.'" '.$selected.($val['access_id'] && !Permission::checkAccess($val['access_id'], 'dynamic', true) ? ' disabled="disabled" style="color:graytext;"' : null).'>'.$output.$val['name'].'</option>'."\n";
                if (isset($this->_navtable[$key])) {
                    $result.= $this->_getNavigationMenu($key,$level+1,$selectedid);
                }
            }
        }
        return $result;
    }


    /**
    * Change page protection
    *
    * @global    ADONewConnection
    * @global    array      Core language
    */
    function changeProtection()
    {
        global $objDatabase, $_CORELANG;

        $this->pagetitle = $_CORELANG['TXT_CONTENT_MANAGER'];
        $loginModuleId = 18;

        if (!empty($_REQUEST['Id'])) {
            $pageId = intval($_REQUEST['Id']);
            $objResult = $objDatabase->Execute("SELECT protected FROM ".DBPREFIX."content_navigation WHERE catid=".$pageId);
            if ($objResult !== false && $objResult->RecordCount()>0) {
                $newprotected = ($objResult->fields["protected"]) ? 0:1;
                $objNavbar= new ContentSitemap(0);
                // Never used
                //$moduleId = intval($objResult->fields["protected"]);
                $catidarray=$objNavbar->getCurrentSonArray($pageId);
                $objDatabase->Execute("UPDATE ".DBPREFIX."content_navigation SET protected = ".$newprotected." WHERE catid=".$pageId);
                foreach ($catidarray as $value) {
                    $objDatabase->Execute("UPDATE ".DBPREFIX."content_navigation SET protected = ".$newprotected." WHERE catid=".$value);
                }
                // Login Module must be unprotected!
                $objDatabase->Execute("UPDATE ".DBPREFIX."content_navigation SET protected=0 WHERE module=".$loginModuleId);

                //write caching-file, delete exisiting cache-files
                $objCache = new Cache();
                $objCache->writeCacheablePagesFile();

                // write xml sitemap
                if (($result = XMLSitemap::write()) !== true) {
                    $this->strErrMessage[] = $result;
                }
                $this->strOkMessage = $_CORELANG['TXT_DATA_RECORD_UPDATED_SUCCESSFUL'];
            } else {
                $this->strErrMessage[] = $_CORELANG['TXT_DATABASE_QUERY_ERROR'];
            }
        }
    }


    /**
    * Change page status
    *
    * @global    array      Core language
    */
    function changeStatus($langId=0)
    {
        global $objDatabase, $_CORELANG;
        $langId = intval($langId);
        if($langId == 0){ $langId = $this->langId; }
        if (isset($_REQUEST['pageId']) && !empty($_REQUEST['pageId'])) {
            $currentTime = time();
            $pageId = intval($_REQUEST['pageId']);

            $objResult = $objDatabase->SelectLimit("SELECT backend_access_id FROM ".DBPREFIX."content_navigation WHERE catid=".$pageId." AND `lang`=".$langId." AND backend_access_id!=0", 1);
            if ($objResult !== false) {
                if ($objResult->RecordCount() == 1 && !Permission::checkAccess($objResult->fields['backend_access_id'], 'dynamic')) {
                    header('Location: index.php?cmd=noaccess');
                    exit;
                };
            } else {
                header('Location: index.php?cmd=noaccess');
                exit;
            }


            $objResult = $objDatabase->Execute("SELECT displaystatus FROM ".DBPREFIX."content_navigation WHERE catid=".$pageId." AND `lang`=".$langId);
            if ($objResult !== false && $objResult->RecordCount()>0) {
                if ($objResult->fields['displaystatus']=='on') {
                    $newstatus='off';
                } else {
                    $newstatus='on';
                }

                $objFWUser = FWUser::getFWUserObject();

                $objDatabase->Execute("
                    UPDATE ".DBPREFIX."content_navigation
                                          SET displaystatus = '".$newstatus."',
                                              username='".$objFWUser->objUser->getUsername()."',
                                              changelog='".$currentTime."'
                     WHERE catid=".$pageId." AND `lang`=".$langId
                );

// TODO: This is nonsense!  $value is never used.
// Should $pageId be replaced by $value in the query?
//                $objNavbar = new ContentSitemap(0);
//                $catidarray = $objNavbar->getCurrentSonArray($pageId);
//                foreach ($catidarray as $value) {
                    $objDatabase->Execute("
                        UPDATE ".DBPREFIX."content_navigation
                           SET displaystatus='".$newstatus."'
                         WHERE catid=".$pageId." AND `lang`=".$langId
                    );
//                }

                // write xml sitemap
                if (($result = XMLSitemap::write()) !== true) {
                    $this->strErrMessage[] = $result;
                }

                $this->strOkMessage = $_CORELANG['TXT_DATA_RECORD_UPDATED_SUCCESSFUL'];
            } else {
                $this->strErrMessage[] = $_CORELANG['TXT_DATABASE_QUERY_ERROR'];
            }
        }
    }

    function _setPageProtection($pageId, $parentPageId, $protect, $arrGroups, $type, $recursive = false, $langId = 0)
    {
        global $objDatabase, $_CONFIG;

        if($langId == 0){ $langId = $this->langId; }
        $loginModuleId = 18;
        $rightId = 0;
        $pageIsProtected = false;
        $protectionString = '';
        $lastRightId = $_CONFIG['lastAccessId'];

        if (!$protect && $parentPageId != 0 && $parentPageId != $pageId) {
            $arrGroups = array();
            $objResult = $objDatabase->Execute('SELECT n.`'.$type.'_access_id`, a.`group_id` FROM `'.DBPREFIX.'content_navigation` AS n LEFT JOIN `'.DBPREFIX.'access_group_dynamic_ids` AS a ON a.`access_id`=n.`'.$type.'_access_id` WHERE n.`catid`='.$parentPageId.' AND `n`.`lang`='.$langId);
            if ($objResult !== false && $objResult->RecordCount() > 0) {
                if ($objResult->fields[$type.'_access_id'] > 0) {
                    $protect = true;

                    while (!$objResult->EOF) {
                        array_push($arrGroups, $objResult->fields['group_id']);
                        $objResult->MoveNext();
                    }
                }

            }

            if (count($arrGroups)>0) {
                $protect = true;
            }
        }

        if ($type == 'frontend') {
            $protectionString = "protected=".($protect ? "1" : "0").",";
        }

        if ($recursive) {
            $objNavbar = new ContentSitemap(0);
            $arrSubPageIds = $objNavbar->getCurrentSonArray($pageId);
        }

        // get page protection info
        $objResult = $objDatabase->Execute("SELECT ".$type."_access_id AS access_id FROM ".DBPREFIX."content_navigation WHERE catid=".$pageId.' AND `lang`='.$langId);
        if ($objResult !== false) {
            if (!empty($objResult->fields['access_id'])) {
                $pageIsProtected = true;
                $rightId = $objResult->fields['access_id'];
            }
        }

        if ($protect) {
            if ($pageIsProtected) { // page was already protected, so update only the group permissions
                // remove old group permissions
                $objDatabase->Execute("DELETE FROM ".DBPREFIX."access_group_dynamic_ids WHERE access_id=".$rightId);

                // add new group permissions
                foreach ($arrGroups as $groupId) {
                    $objDatabase->Execute("INSERT INTO ".DBPREFIX."access_group_dynamic_ids (`access_id`,`group_id`) VALUES (".$rightId.", ".intval($groupId).")");
                }
            } else { // the page wasn't protected, so protect the page and set the group permissions
                $lastRightId++;
                if ($objDatabase->Execute("UPDATE ".DBPREFIX."content_navigation SET ".$protectionString." ".$type."_access_id=".$lastRightId." WHERE catid=".$pageId.' AND `lang`='.$langId) !== false) {
                    foreach ($arrGroups as $groupId) {
                        $objDatabase->Execute("INSERT INTO ".DBPREFIX."access_group_dynamic_ids (`access_id`, `group_id`) VALUES (".$lastRightId.", ".intval($groupId).")");
                    }
                } else {
                    $lastRightId--;
                }
            }

            if ($recursive) {
                foreach ($arrSubPageIds as $subPageId) {
                    $objResult = $objDatabase->Execute("SELECT ".$type."_access_id AS access_id FROM ".DBPREFIX."content_navigation WHERE catid=".$subPageId.' AND `lang`='.$langId);
                    if ($objResult !== false) {
                        if (!empty($objResult->fields['access_id'])) { // page was already protected, so update only the group permissions
                            $objDatabase->Execute("DELETE FROM ".DBPREFIX."access_group_dynamic_ids WHERE access_id=".$objResult->fields['access_id']);
                            foreach ($arrGroups as $groupId) {
                                $objDatabase->Execute("INSERT INTO ".DBPREFIX."access_group_dynamic_ids (`access_id`, `group_id`) VALUES (".$objResult->fields['access_id'].", ".intval($groupId).")");
                            }
                        } else { // the page wasn't protected, so protect the page and set the group permissions
                            $lastRightId++;
                            if ($objDatabase->Execute("UPDATE ".DBPREFIX."content_navigation SET ".$protectionString." ".$type."_access_id=".$lastRightId." WHERE catid=".$subPageId.' AND `lang`='.$langId) !== false) {
                                foreach ($arrGroups as $groupId) {
                                    $objDatabase->Execute("INSERT INTO ".DBPREFIX."access_group_dynamic_ids (`access_id`, `group_id`) VALUES (".$lastRightId.", ".intval($groupId).")");
                                }
                            } else {
                                $lastRightId--;
                            }
                        }
                    } else {
                        // the page $subPageId couldn't be protected
                    }
                }
            }

            $objFWUser = FWUser::getFWUserObject();
            $objFWUser->objUser->getDynamicPermissionIds(true);
        } else {
            // remove protection
            if ($pageIsProtected) {
                $objDatabase->Execute("UPDATE ".DBPREFIX."content_navigation SET ".$protectionString." ".$type."_access_id=NULL WHERE catid=".$pageId.' AND `lang`='.$langId);
                $objDatabase->Execute("DELETE FROM ".DBPREFIX."access_group_dynamic_ids WHERE access_id=".$rightId);
            }

            if ($recursive) {
                // remove protection from sub pages
                foreach ($arrSubPageIds as $subPageId) {
                    $objResult = $objDatabase->Execute("SELECT ".$type."_access_id AS access_id FROM ".DBPREFIX."content_navigation WHERE catid=".$subPageId.' AND `lang`='.$langId);
                    if ($objResult != false) {
                        if (!empty($objResult->fields['access_id'])) {
                            $objDatabase->Execute("UPDATE ".DBPREFIX."content_navigation SET ".$protectionString." ".$type."_access_id=NULL WHERE catid=".$subPageId.' AND `lang`='.$langId);
                            $objDatabase->Execute("DELETE FROM ".DBPREFIX."access_group_dynamic_ids WHERE access_id=".$objResult->fields['access_id']);
                        }
                    }
                }
            }
        }

        // Login Module must be unprotected!
        $objDatabase->Execute("UPDATE ".DBPREFIX."content_navigation SET protected=0 WHERE module=".$loginModuleId);

        if ($lastRightId > $_CONFIG['lastAccessId']) {
            $_CONFIG['lastAccessId'] = $lastRightId;
            $objDatabase->Execute("UPDATE ".DBPREFIX."settings SET setvalue=".$lastRightId." WHERE setname='lastAccessId'");

            require_once(ASCMS_CORE_PATH.'/settings.class.php');
            $objSettings = new settingsManager();
            $objSettings->writeSettingsFile();
        }
    }

    /**
    * Do navigation dropdown
    *
    * @global    ADONewConnection
    * @global    array      Core language
    * @param    integer  $parcat
    * @param    integer  $level
    * @param    integer  $selectedid
    * @return   string   $result
    */
    function _getThemesMenu($id=null) {
        global $objDatabase, $_CORELANG;

        $themesId = intval($id);

        $return = "<option value='0' selected>(".$_CORELANG['TXT_STANDARD'].")</option>\n";
        $objResult = $objDatabase->Execute("SELECT id,themesname FROM ".DBPREFIX."skins ORDER BY id");
        if ($objResult !== false) {
            while (!$objResult->EOF) {
                $selected= ($objResult->fields['id']==$themesId) ? " selected" : '';
                $return .="<option value='".$objResult->fields['id']."'$selected>".$objResult->fields['themesname']."</option>\n";
                $objResult->MoveNext();
            }
        }
        return $return;
    }


    /**
    * Change the "activestatus"-flag of a page
    *
    * @global    ADONewConnection
    * @param    integer      $intPageId: The page with this id will be changed
    */
    function changeActiveStatus($intPageId,$intNewStatus='') {
        global $objDatabase;

        $intPageId = intval($intPageId);

        $objResult = $objDatabase->SelectLimit("SELECT backend_access_id FROM ".DBPREFIX."content_navigation WHERE catid=".$intPageId." AND backend_access_id!=0 AND `lang`=".$this->langId, 1);
        if ($objResult !== false) {
            if ($objResult->RecordCount() == 1 && !Permission::checkAccess($objResult->fields['backend_access_id'], 'dynamic')) {
                header('Location: index.php?cmd=noaccess');
                exit;
            };
        } else {
            header('Location: index.php?cmd=noaccess');
            exit;
        }

        if ($intPageId != 0) {
            if (empty($intNewStatus)) {
                $objResult = $objDatabase->Execute('SELECT    activestatus
                                                    FROM    '.DBPREFIX.'content_navigation
                                                    WHERE    catid='.$intPageId.' AND `lang`='.$this->langId.'
                                                    LIMIT    1
                                                ');
                if ($objResult->fields['activestatus'] == 1) {
                    $intNewStatus = 0;
                } else {
                    $intNewStatus = 1;
                }
            } else {
                $intNewStatus = intval($intNewStatus);
            }

            $objResult = $objDatabase->Execute('SELECT    catid
                                                FROM    '.DBPREFIX.'content_navigation
                                                WHERE    parcat='.$intPageId.' AND `lang`='.$this->langId);
            if ($objResult->RecordCount() > 0) {
                while (!$objResult->EOF) {
                    $this->changeActiveStatus($objResult->fields['catid'],$intNewStatus);
                    $objResult->MoveNext();
                }
            }

            $objDatabase->Execute('    UPDATE    '.DBPREFIX.'content_navigation
                                    SET        activestatus="'.$intNewStatus.'"
                                    WHERE    catid='.$intPageId.' AND `lang`='.$this->langId.'
                                    LIMIT    1
                                ');
            $objDatabase->Execute('UPDATE  '.DBPREFIX.'content_navigation_history
                                    SET    changelog='.time().',
                                           activestatus='.$intNewStatus.'
                                    WHERE  catid='.$intPageId.' AND `lang`='.$this->langId.'
                                      AND  is_active="1"
                                    LIMIT  1');
        }
    }

    /**
     * Avoid circular references in categories.
     *
     * Note: This method is called recursively.
     * @global  ADONewConnection
     * @param   integer     $intPageId      The page ID to be checked
     * @param   integer     $intPid         The new parent category ID
     * @param   boolean     $boolFirst      Has to be true for the first call
     * @return  boolean     True if the parent category ID is valid,
     *                      false otherwise (circular reference detected)
     */
    function checkParcat($intPageId,$intPid,$boolFirst=true)
    {
        global $objDatabase;

        $intPageId     = intval($intPageId);
        $intPid     = intval($intPid);

        if ($boolFirst) {
            if ($intPageId == $intPid) {
                // Category hasn't changed, return true
                return true;
            }
        }

        if ($intPid == 0 && $boolFirst && !Permission::checkAccess(127, 'static', true)) {
            // user is not allowed to create a new page on the first level
            return false;
        } elseif ($intPid != 0) {
            if ($intPageId == $intPid) {
                // The new category is a subcategory of itself;
                // do not allow that.
                return false;
            } else {
                // Subcategory, go ahead
                $objResult = $objDatabase->Execute('
                    SELECT    parcat, backend_access_id
                                                        FROM    '.DBPREFIX.'content_navigation
                     WHERE catid='.$intPid
                );
                if ($objResult->RecordCount() != 0) {
                    $row = $objResult->FetchRow();
                    if ($boolFirst && $row['backend_access_id'] && !Permission::checkAccess($row['backend_access_id'], 'dynamic', true)) {
                        return false;
                    }
                    return $this->checkParcat($intPageId,$row['parcat'],false);
                }
            }
        }
        // Root category
        return true;
    }


    /**
     * The function collects all categories without an existing parcat and assigns it to "lost and found"
     *
     * @global     ADONewConnection
     */
    function collectLostPages() {
        global $objDatabase;

        $objResult = $objDatabase->Execute('    SELECT    catid,
                                                        parcat,
                                                        lang
                                                FROM    '.DBPREFIX.'content_navigation
                                                WHERE    parcat <> 0
                                        ');
        if ($objResult->RecordCount() > 0) {
            // Subcategories have been found
            $row = $objResult->FetchRow();
            while ($row) {
                $objSubResult = $objDatabase->Execute('
                    SELECT 1
                                                        FROM    '.DBPREFIX.'content_navigation
                                                        WHERE    catid='.$row['parcat'].' AND `lang`='.$this->langId.'
                                                    ');
                if ($objSubResult->RecordCount() != 1) {
                    // This is a "lost" category.
                    // Assign it to "lost and found"
                    $objSubSubResult = $objDatabase->SelectLimit('
                        SELECT catid
                                                                FROM    '.DBPREFIX.'content_navigation
                                                                WHERE    module=1 AND
                                                                        cmd="lost_and_found" AND
                               `lang`='.$row['lang'],
                        1
                    );
                    $subSubRow = $objSubSubResult->FetchRow();
                    $objDatabase->Execute('
                        UPDATE '.DBPREFIX.'content_navigation
                                            SET        parcat='.$subSubRow['catid'].'
                         WHERE catid='.$row['catid'].' AND lang='.$this->langId
                    );
                }
                $row = $objResult->FetchRow();
            }
        }
    }


    function getBlocks($pageId = null, $langId = 0) {
        global $objDatabase;

        if($langId == 0){ $langId = $this->firstActiveLang; }

        $blocks = array('', ''); // initialize to empty strings to avoid notice
        $arrBlocks = array();
        $arrRelationBlocks = array();

        //get blocks
        $objResult = $objDatabase->Execute('SELECT    id, name
                                            FROM    '.DBPREFIX.'module_block_blocks
                                            WHERE    active=1
                                        ');

        if ($objResult->RecordCount() > 0) {
            while (!$objResult->EOF) {
                $arrBlocks[$objResult->fields['id']] = array('id' => $objResult->fields['id'], 'name' => $objResult->fields['name']);
                $objResult->MoveNext();
            }
        }

        //block relation
        $objResult = $objDatabase->Execute('SELECT    block_id
                                            FROM    '.DBPREFIX.'module_block_rel_pages
                                            WHERE    page_id='.$pageId.' AND lang_id='.$langId.'
                                        ');


        if ($objResult !== false) {
            while (!$objResult->EOF) {
                $arrRelationBlocks[$objResult->fields['block_id']] = '';
                $objResult->MoveNext();
            }
        }

        foreach ($arrBlocks as $arrData) {
            if (array_key_exists($arrData['id'],$arrRelationBlocks)) {
                $blocks[0] .= '<option value="'.$arrData['id'].'">'.$arrData['name'].' ('.$arrData['id'].') </option>'."\n";
            } else {
                $blocks[1] .= '<option value="'.$arrData['id'].'">'.$arrData['name'].' ('.$arrData['id'].') </option>'."\n";
            }
        }

        return $blocks;
    }

	/**
	 * Returns an alias that has only valid characters.
	 */
	function _fix_alias($txt) {
		// this is kinda of a duplicate of the javascript function aliasText()
		// in cadmin/template/ascms/content_editor.html

		// Sanitize most latin1 characters.
		// there's more to come. maybe there's
		// a generic function for this?
		$txt = str_replace(
			array('ä', 'ö', 'ü', 'à','ç','è','é'),
			array('ae','oe','ue','a','c','e','e'),
			strtolower($txt)
		);

		$txt = preg_replace( '/[\+\/\(\)=,;%&]+/', '-', $txt); // interpunction etc.
		$txt = preg_replace( '/[\'<>\\\~$!"]+/',     '',  $txt); // quotes and other special characters

		// Fallback for everything we didn't catch by now
		$txt = preg_replace('/[^\sa-z_-]+/i',  '_', $txt);
		$txt = preg_replace('/[_-]{2,}/',    '_', $txt);
		$txt = preg_replace('/^[_\.\/\-]+/', '',  $txt);
        $txt = str_replace(array(' ', '\\\ '), '\\\\ ', $txt);
		return $txt;
	}

	/**
	 * Sets default alias for a given page id. If an empty alias is given and the
	 * page already has a default alias, it will be removed.
	 *
     * Returns false on SUCCESS. On failure, returns an appropriate error message.
	 * @param pageid  the local URL to the page ("?page=xx" or "?section=..." alike stuff)
	 * @param alias   the alias to install for the page. if it is empty or null,
	 *                no change will happen.
	 */
    function _set_default_alias($pageId, $alias, $langId = 0) {
        if($langId == 0){ $langId = $this->langId; }
        $alias    = $this->_fix_alias($alias);
		//////////////////////////////////////////////////////////////
		// aliasLib has some handy stuff for us here..
		global $objDatabase, $_ARRAYLANG;
		require_once(ASCMS_CORE_MODULE_PATH .'/alias/lib/aliasLib.class.php');
		$util = new aliasLib($langId);

		// check if there is already an alias present for the page
        $aliasId = intval($this->_has_default_alias($pageId, $langId));
        if (($arrAlias = $util->_getAlias($aliasId)) == false) {
            $arrAlias = array(
                'type'      => 'local',
                'url'       => $pageId,
                'pageUrl'   => '',
                'sources'   => array()
            );
            $aliasId = 0;
        }

        if ($alias == '') {
        	// Remove alias if it's empty.
            $aliasRemoved = false;
            for ($i = 0; $i < count($arrAlias['sources']); $i++) {
                if ($arrAlias['sources'][$i]['isdefault']) {
                    $aliasRemoved = true;
                    unset($arrAlias['sources'][$i]);
                    break;
                }
            }
            if ($aliasRemoved) {
                if (!count($arrAlias['sources'])) {
                    // no other alias for this page are left, so let's remove the whole alias
                    $util->_deleteAlias($aliasId);
                } else {
					// update the alias with the removed source entry
                    $util->_updateAlias($aliasId, $arrAlias);
                }
            }
            return false;
        } elseif (!$util->is_alias_valid($alias)) {
            return sprintf($_ARRAYLANG['TXT_ALIAS_MUST_NOT_BE_A_FILE'], htmlentities($alias, ENT_QUOTES, CONTREXX_CHARSET));
        } else {
            // check if we are going to update or add an alias source
            $aliasNr = null;
            for ($i = 0; $i < count($arrAlias['sources']); $i++) {
                if ($arrAlias['sources'][$i]['isdefault']) {
                    $aliasNr = $i;
                    break;
                }
            }

            // check if the defined alias source is unique
            if (!$util->_isUniqueAliasSource($alias, $pageId, $arrAlias['pageUrl'], isset($aliasNr) ? $arrAlias['sources'][$aliasNr]['id'] : 0)) {
                return sprintf($_ARRAYLANG['TXT_ALIAS_ALREADY_IN_USE'], htmlentities($alias, ENT_QUOTES, CONTREXX_CHARSET));
            }


            if (isset($aliasNr)) {
                // updating the current standard alias source
                $arrAlias['sources'][$aliasNr]['url'] = $alias;
            } else {
                // adding a new alias source
                $arrAlias['sources'][] = array('url' => $alias, 'isdefault' => 1);
            }

            if (($aliasId ? $util->_updateAlias($aliasId, $arrAlias) : $util->_addAlias($arrAlias))) {
                return false;
            } else {
                return $aliasId ? $_ARRAYLANG['TXT_ALIAS_ALIAS_UPDATE_FAILED'] : $_ARRAYLANG['TXT_ALIAS_ALIAS_ADD_FAILED'];
            }
        }
	}

	/**
	 * Returns the alias source id if the given pageid
	 * has a default alias defined. false otherwise.
	 */
	function _has_default_alias($pageid, $langId) {
		global $objDatabase;
		$check_update = "
			SELECT a_s.url, a_t.id
			FROM            ".DBPREFIX."module_alias_target AS a_t
			LEFT OUTER JOIN ".DBPREFIX."module_alias_source AS a_s
				  ON  a_t.id        = a_s.target_id
				  AND a_s.isdefault = 1
			WHERE a_t.url = '$pageid' AND a_s.lang_id = ".$langId;
		$check_update_res = $objDatabase->Execute($check_update);
		if ($check_update_res->RecordCount()){
			return $check_update_res->fields['id'];
		}
		return false;
	}
    function modifyBlocks($associatedBlockIds, $pageId, $langId = 0)
    {
        global $objDatabase, $_FRONTEND_LANGID;

        if($langId == 0){ $langId = $_FRONTEND_LANGID; }

        $objResult = $objDatabase->Execute("
            DELETE FROM ".DBPREFIX."module_block_rel_pages
             WHERE page_id=".$pageId.' AND lang_id='.$langId
        );
        if ($objResult) {
            foreach ($associatedBlockIds as $blockId) {
                $objDatabase->Execute('
                    INSERT INTO '.DBPREFIX.'module_block_rel_pages
                       SET block_id='.$blockId.',
                           page_id='.$pageId.',
                           lang_id='.$langId
                );
            }
        }

        if (!empty($associatedBlockIds)) {
            $objResult = $objDatabase->Execute('
                SELECT all_pages
                 FROM '.DBPREFIX.'module_block_rel_lang
                 WHERE block_id='.$blockId.'
                   AND lang_id='.$langId
            );
            if (!$objResult->RecordCount() > 0) {
                $objDatabase->Execute('
                    INSERT INTO '.DBPREFIX.'module_block_rel_lang
                       SET block_id='.$blockId.',
                           lang_id='.$langId.',
                           all_pages=0
                                    ');
            } else {
                $query = "
                    UPDATE ".DBPREFIX."module_block_rel_lang
                       SET all_pages='0'
                     WHERE block_id='".$blockId."'
                       AND lang_id='".$langId."'
                       AND all_pages='1'
                ";
                $objDatabase->Execute($query);
            }
        }
    }

    function createJSON(){
        global $objDatabase;
        $data   = $_GET['data'];
        $pageId = intval($_REQUEST['page']);
        $langId = intval($_REQUEST['lang']);
        $langName = $_REQUEST['langName'];
        switch($data){
            case 'inputText':
       			 $objRS = $objDatabase->SelectLimit("
          			      SELECT a_s.url AS alias_url
                            FROM ".DBPREFIX."content AS c
                 LEFT OUTER JOIN ".DBPREFIX."module_alias_target AS a_t ON a_t.url = c.id
                 LEFT OUTER JOIN ".DBPREFIX."module_alias_source AS a_s
                              ON  (a_s.target_id = a_t.id AND a_s.lang_id = c.lang_id)
                             AND a_s.isdefault = 1
                           WHERE c.id =".$pageId.' AND c.lang_id='.$langId, 1);
                $alias = $objRS->fields['alias_url'];
                $query =  'SELECT `c`.`content`, `c`.`title`, `c`.`metatitle`, `c`.`metadesc` AS `desc`, `c`.`redirect` AS `redirectUrl`,
                                          `c`.`metakeys` AS `key`, `c`.`css_name` AS cssName, `n`.`cmd` AS `command`,
                                          `n`.`catname` AS `newpage`, `n`.`startdate`, `n`.`enddate`, `n`.`css_name` AS cssName
                                     FROM `'.DBPREFIX.'content` AS `c`
                               INNER JOIN `'.DBPREFIX.'content_navigation` AS `n`
                                       ON (`c`.`id` = `n`.`catid` AND `c`.`lang_id` = `n`.`lang`)
                                    WHERE `c`.`id` = '.$pageId.'
                                      AND `c`.`lang_id` = '.$langId;
                $objRS = $objDatabase->SelectLimit($query, 1);
                $objRS->fields['langName'] = $langName;
                $objRS->fields['alias']    = $alias;
                die(json_encode($objRS->fields));
            break;

            case 'inputRadio':
                $protection = $this->_getPageProtectionStatus($pageId, $langId) == 'checked' ? 1 : 0;  // frontend protection
                $arrAssignedBackendGroups = $this->_getAssignedGroups('backend', $pageId, $langId);    // backend protection
                $backendPermission = 0;
                foreach ($this->arrAllBackendGroups as $id => $name) {
                    if (in_array($id, $arrAssignedBackendGroups)) {
                        $backendPermission = true;
                        break;
                    }
                }
                $query =  'SELECT `c`.`redirect` AS `TypeSelection`
                                     FROM `'.DBPREFIX.'content` AS `c`
                                    WHERE `c`.`id` = '.$pageId.'
                                      AND `c`.`lang_id` = '.$langId;
                $objRS = $objDatabase->SelectLimit($query, 1);
                if(empty($objRS->fields['TypeSelection'])){
                    $objRS->fields['TypeSelection'] = 'content';
                }else{
                    $objRS->fields['TypeSelection'] = 'redirect';
                }
                $objRS->fields['protection']        = $protection;
                $objRS->fields['backendPermission'] = $backendPermission;
                $objRS->fields['langName']          = $langName;
                die(json_encode($objRS->fields));
            break;

            case 'navMenu':
                global $_CORELANG;
                $navMenu['value']    = $pageId;
                $navMenu['options' ] = '<option value="" selected="selected" '.(!Permission::checkAccess(127, 'static', true)
                                                                              ? 'disabled="disabled" style="color:graytext;"'
                                                                              : '').'>'.$_CORELANG['TXT_NEW_CATEGORY'].'</option>
                                      '.$this->getPageMenu($pageId, $langId);
                $navMenu['langName'] = $langName;
                die(json_encode($navMenu));
            break;
            case 'inputCheckbox':
                $query =  'SELECT `c`.`expertmode`, `c`.`metarobots` AS `robots`,
                                  `n`.`displaystatus`, `n`.`cachingstatus`
                             FROM `'.DBPREFIX.'content` AS `c`
                       INNER JOIN `'.DBPREFIX.'content_navigation` AS `n`
                               ON (`c`.`id` = `n`.`catid` AND `c`.`lang_id` = `n`.`lang`)
                            WHERE `c`.`id` = '.$pageId.'
                              AND `c`.`lang_id` = '.$langId;
                $objRS = $objDatabase->SelectLimit($query, 1);
                $objRS->fields['displaystatus']   = $objRS->fields['displaystatus'] == 'on'    ? 'checked' : false;
                $objRS->fields['cachingstatus']   = $objRS->fields['cachingstatus'] == 1       ? 'checked' : false;
                $objRS->fields['expertmode']      = $objRS->fields['expertmode']    == "y"     ? 'checked' : false;
                $objRS->fields['robots']          = $objRS->fields['robots']        == "index" ? 'checked' : false;
                $objRS->fields['themesRecursive'] = false;
                $objRS->fields['backendInherit']  = false;
                $objRS->fields['recursive']       = false;
                $objRS->fields['langName']        = $langName;
                die(json_encode($objRS->fields));
            break;
            case 'select':
                global $_CORELANG;

                $assignedGroups = $existingGroups = $assignedBackendGroups = $existingBackendGroups = '';
                $blocks = $this->getBlocks($langId);
                // Frontend Groups
                $arrAssignedFrontendGroups = $this->_getAssignedGroups('frontend', $pageId, $langId);
                foreach ($this->arrAllFrontendGroups as $id => $name) {
                    if (in_array($id, $arrAssignedFrontendGroups)) {
                        $assignedGroups .= '<option value="'.$id.'">'.$name."</option>\n";
                    } else {
                        $existingGroups .= '<option value="'.$id.'">'.$name."</option>\n";
                    }
                }
                // Backend Groups
                $arrAssignedBackendGroups = $this->_getAssignedGroups('backend', $pageId, $langId);
                foreach ($this->arrAllBackendGroups as $id => $name) {
                    if (in_array($id, $arrAssignedBackendGroups)) {
                        $assignedBackendGroups .= '<option value="'.$id.'">'.$name."</option>\n";
                    } else {
                        $existingBackendGroups .= '<option value="'.$id.'">'.$name."</option>\n";
                    }
                }

                $objRS = $objDatabase->SelectLimit("
                    SELECT module, target, themes_id
                      FROM ".DBPREFIX."content_navigation
                     WHERE catid = ".$pageId." AND `lang`=".$langId, 1);
                $targets = '<option value="'.$objRS->fields['target'].'" selected="selected">'.$objRS->fields['target'].'</option>';
                if(empty($target)){ $targets = '<option value=""></option>'; }
                foreach ($this->_arrRedirectTargets as $target){
                    if(empty($target)){ continue; }
                    $targets .= '<option value="'.$target.'">'.$_CORELANG['TXT_TARGET'.strtoupper($target)].' ('.$target.')</option>';
                }
                $selects['themesId']['options']                 = $this->_getThemesMenu($objRS->fields['themes_id']);
                $selects['themesId']['value']                   = $objRS->fields['themes_id'];
                $selects['selectmodule']['options']             = '<option value="">'.$_CORELANG['TXT_NO_MODULE'].'</option>'.$this->_getModuleMenu($objRS->fields['module']);
                $selects['selectmodule']['value']               = $objRS->fields['module'];
                $selects['redirectTarget']['options']           = $targets;
                $selects['redirectTarget']['value']             = $objRS->fields['target'];
                $selects['existingGroups[]']['options']         = $existingGroups;
                $selects['assignedGroups[]']['options']         = $assignedGroups;
                $selects['existingBackendGroups[]']['options']  = $existingBackendGroups;
                $selects['assignedBackendGroups[]']['options']  = $assignedBackendGroups;
                $selects['existingBlocks[]']['options']         = $blocks[0];
                $selects['assignedBlocks[]']['options']         = $blocks[1];
                $selects['langName']                            = $langName;
                die(json_encode($selects));
            break;
            default:
        }
    }
}

if (!function_exists('json_encode')){
	function json_encode($a=false)	{
		if (is_null($a)) return 'null';
		if ($a === false) return 'false';
		if ($a === true) return 'true';
		if (is_scalar($a)){
			if (is_float($a)){
				// Always use "." for floats.
				return floatval(str_replace(",", ".", strval($a)));
			}
			if (is_string($a)){
				static $jsonReplaces = array(array("\\", "/", "\n", "\t", "\r", "\b", "\f", '"'), array('\\\\', '\\/', '\\n', '\\t', '\\r', '\\b', '\\f', '\"'));
				return '"' . str_replace($jsonReplaces[0], $jsonReplaces[1], $a) . '"';
			}else{
				return $a;
			}
        }
		$isList = true;
		for ($i = 0, reset($a); $i < count($a); $i++, next($a)){
			if (key($a) !== $i){
				$isList = false;
				break;
			}
		}
		$result = array();
		if ($isList){
			foreach ($a as $v) $result[] = json_encode($v);
			return '[' . join(',', $result) . ']';
		} else {
			foreach ($a as $k => $v) $result[] = json_encode($k).':'.json_encode($v);
			return '{' . join(',', $result) . '}';
		}
	}
}
?>
