<?php
/**
 * Admin CP navigation
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team <info@comvation.com>
 * @version     2.0.0
 * @package     contrexx
 * @subpackage  core
 * @todo        Edit PHP DocBlocks!
 */

/**
 * Class for the Admin CP navigation
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team <info@comvation.com>
 * @access      public
 * @version     2.0.0
 * @package     contrexx
 * @subpackage  core
 * @todo        Edit PHP DocBlocks!
 */
class adminMenu
{
    public $arrMenuItems = array();
    public $arrMenuGroups = array();
    public $statusMessage;
    public $arrUserRights = array();
    public $arrUserGroups = array();
    private $activeCmd = null;


    public function __construct($activeCmd)
    {
        $this->activeCmd = $activeCmd;
        $this->init();
    }


    public function getAdminNavbar()
    {
        global $objTemplate;

        $this->getMenu();
        $objTemplate->setVariable('STATUS_MESSAGE',trim($this->statusMessage));
    }


    private function init()
    {
        global $_CORELANG, $objDatabase;

        $objFWUser = FWUser::getFWUserObject();
        $sqlWhereString = "";

        if (!$objFWUser->objUser->getAdminStatus()) {
            if (count($objFWUser->objUser->getStaticPermissionIds()) > 0) {
                $sqlWhereString = " AND (areas.access_id = ".implode(' OR areas.access_id = ', $objFWUser->objUser->getStaticPermissionIds()).")";
            } else {
                $sqlWhereString = " AND areas.access_id='' ";
            }
        }

        $query = "
            SELECT
                areas.area_id AS area_id,
                areas.parent_area_id AS parent_area_id,
                areas.area_name AS area_name,
                areas.type AS type,
                areas.uri AS uri,
                areas.target AS target,
                modules.name AS module_name
            FROM
                ".DBPREFIX."backend_areas AS areas
            INNER JOIN
                ".DBPREFIX."modules AS modules
            ON
                modules.id=areas.module_id
            WHERE
                areas.is_active=1
                AND (areas.type = 'group' OR areas.type = 'navigation')
                AND modules.is_active = 1
                ".$sqlWhereString."
            ORDER BY
                areas.order_id ASC
        ";
        $objResult = $objDatabase->Execute($query);
        // ADD A JOIN TO MODULE TABLE HERE TO SEE IF THE MODULE IS ACTIVE
        if ($objResult !== false) {
            while (!$objResult->EOF) {
                if ($objResult->fields['type'] == "group") {
                    $this->arrMenuGroups[$objResult->fields['area_id']] = $objResult->fields['area_name'];
                }
                $this->arrMenuItems[$objResult->fields['area_id']] =
                array(
                    $objResult->fields['parent_area_id'],
                    (isset($_CORELANG[$objResult->fields['area_name']])
                      ? $_CORELANG[$objResult->fields['area_name']] : ''),
                    $objResult->fields['uri'],
                    $objResult->fields['target'],
                    $objResult->fields['module_name']
                );
                $objResult->MoveNext();
            }
        }
    }


    /**
     * Creates the administration navigation
     *
     * Considers the users' rights and only shows what he's
     * allowed to see
     * @global array  $_CORELANG
     * @global object $objTemplate
     */
    private function getMenu()
    {
        global $_CORELANG, $objTemplate;

        $objTemplate->addBlockfile('NAVIGATION_OUTPUT', 'navigation_output', 'index_navigation.html');

        reset($this->arrMenuItems);

        if (empty($_GET['cmd'])) {
            setcookie('navigation_level_2_active', 'home');
            $_COOKIE['navigation_level_2_active'] = 'home';
        } else {
            foreach ($this->arrMenuItems as $menuItem) {
                if (preg_match('/cmd=(.+?)(?:&amp;(.+))?$/', $menuItem[2], $arrMatch)) {
                    if ($arrMatch[1] == $this->activeCmd) {
                        setcookie('navigation_level_2_active', $menuItem[0]);
                        $_COOKIE['navigation_level_2_active'] = $menuItem[0];
                    }
                }
            }
        }

        foreach ( $this->arrMenuGroups as $group_id => $group_data ) {
            // Module group menu and module check!
            $navigation = '';

            //used to remember items in current menu group
            $arrMatchingItems = array();
            //used to remember captions of the current menu group
            //later used to perform array_multisort
            $arrMatchingItemCaptions = array();

            //(1/3) find entries of current menu group
            foreach ($this->arrMenuItems as $link_data) {
                // checks if the links are childs of this area ID
                if ($link_data[0] == $group_id) {
                    $arrMatchingItems[] = $link_data;
                    $arrMatchingItemCaptions[] = $link_data[1];
                }
            }
            if($group_id == 2) {  //modules group
                //(2/3) sort entries by captions
                array_multisort($arrMatchingItemCaptions, $arrMatchingItems);
            }

            //(3/3) display a nice ordered menu.
            foreach ($arrMatchingItems as $link_data) {
                if ($group_id != 2 ||  $this->moduleExists($link_data[4])) {

                    // active exceptions for media and content module
                    // ask: thomas.daeppen@comvation.com
                    $linkCmd = '';
                    $linkCmdSection = '';
                    if (preg_match('/cmd=(.+?)(?:&amp;(.+))?$/', $link_data[2], $arrMatch)) {
                        $linkCmd = $arrMatch[1];
                        if (isset($arrMatch[2])) {
                            $linkCmdSection = $arrMatch[2];
                        }

                        switch ($linkCmd) {
                            case 'content';
                                if (   $this->activeCmd == 'content'
                                    && (   empty($_REQUEST['act']) && !empty($linkCmdSection)
                                        || !empty($_REQUEST['act']) && empty($linkCmdSection))
                                ) {

                                    $linkCmd = '';
                                }
                                break;
                            case 'stats':
                                $cssClass = 'inactive';
                                if ($this->activeCmd == 'stats') {
                                    if (!empty($_REQUEST['stat']) && !empty($linkCmdSection) && (strpos($linkCmdSection, $_REQUEST['stat']) !== false)) {
                                        $cssClass = 'active';
                                    }
                                }
                                break;
                            case 'server':
                                if ($this->activeCmd == 'nettools') {
                                    $cssClass = 'active';
                                }
                                break;
                            case 'skins':
                                if ($this->activeCmd == 'skins') {
                                    break;
                                }
                                $linkCmdSection = 'archive=themes';
                            case 'media':
                                if ($this->activeCmd != 'media') {
                                    break;
                                }

                                $isRequestedMediaArchive = false;
                                $requestedArchive = '';
                                if (isset($_REQUEST['archive'])) {
                                    $requestedArchive = preg_replace('/\d+$/', '', $_REQUEST['archive']);
                                }

                                switch ($requestedArchive) {
                                    case 'shop':
                                        $requestedArchive = 'content';
                                        break;
                                    case 'themes':
                                        $linkCmd = 'media';
                                        break;
                                    case 'archive':
                                        $requestedArchive = 'archive';
                                        break;
                                }

                                if (!empty($requestedArchive)) {
                                    if (preg_match('/archive=(.+?)\d*$/', $linkCmdSection, $arrMatch)) {
                                        $mediaArchive = $arrMatch[1];
                                        if ($mediaArchive == $requestedArchive) {
                                            $isRequestedMediaArchive = true;
                                        }
                                    }

                                }

                                if (!$isRequestedMediaArchive) {
                                    $linkCmd = '';
                                }

                                break;
                            default:
                                break;
                        }
                    }

                    if (empty($cssClass)) {
                        if ((!empty($this->activeCmd) && !empty($linkCmd) && ($this->activeCmd == $linkCmd)) &&
                            (!empty($_REQUEST['act']) && !empty($linkCmdSection) && (strpos($linkCmdSection, $_REQUEST['act']) !== false))
                        ) {
                            $cssClass = 'active';
                        } else if (!empty($this->activeCmd) && !empty($linkCmd) && ($this->activeCmd == $linkCmd)) {
                            if (!empty($_REQUEST['act']) && !empty($linkCmdSection) && (strpos($linkCmdSection, $_REQUEST['act']) == false)) {
                                $cssClass = 'inactive';
                            } else  {
                                $cssClass = 'active';
                            }
                        } else {
                            $cssClass = 'inactive';
                        }
                    }
                    $navigation.= "<li class='$cssClass'><a href='".strip_tags($link_data[2])."' title='".htmlentities($link_data[1], ENT_QUOTES, CONTREXX_CHARSET)."' target='".$link_data[3]."'>".htmlentities($link_data[1], ENT_QUOTES, CONTREXX_CHARSET)."</a></li>\n";
                }
                
                $cssClass = '';
            }

            if (!empty($navigation)) {
                $objTemplate->setVariable(array(
                    'NAVIGATION_GROUP_NAME' => htmlentities($_CORELANG[$group_data], ENT_QUOTES, CONTREXX_CHARSET),
                    'NAVIGATION_ID'         => $group_id,
                    'NAVIGATION_MENU'       => $navigation,
                    'NAVIGATION_CLASS'      => !empty($_COOKIE['navigation_level_2_active']) && ($_COOKIE['navigation_level_2_active'] == $group_id) ? 'active' : 'inactive',
                ));
                $objTemplate->parse('navigationRow');
            }
        }

        $objTemplate->setVariable(array(
            'TXT_SEARCH'                    => $_CORELANG['TXT_SEARCH'],
            'TXT_HOME_LINKNAME'             => $_CORELANG['TXT_HOME'],
            'TXT_DASHBOARD_LINKNAME'        => $_CORELANG['TXT_DASHBOARD'],
            'NAVIGATION_HOME_CLASS'         => (isset($_COOKIE['navigation_level_2_active'])) && ($_COOKIE['navigation_level_2_active'] == 'home') ? 'active' : 'inactive',
            'NAVIGATION_DASHBOARD_CLASS'    => empty($_GET['cmd']) ? 'active' : 'inactive',
        ));
        $objTemplate->parse('navigation_output');
    }


    private function moduleExists($moduleFolderName)
    {
        if (empty($moduleFolderName)) {
            return true;
        }

        return contrexx_isModuleActive($moduleFolderName);
    }
}
