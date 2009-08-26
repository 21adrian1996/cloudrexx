<?php

/**
 * Admin CP navigation
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team <info@comvation.com>
 * @version     1.0.0
 * @package     contrexx
 * @subpackage  core
 * @todo        Edit PHP DocBlocks!
 */

/**
 * Class for the Admin CP navigation
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team <info@comvation.com>
 * @access      public
 * @version     1.0.0
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


    /**
     * Constructor
     */
    function __construct()
    {
        $this->init();
    }


    function getAdminNavbar()
    {
        global $objTemplate;

        $this->getMenu();
        $objTemplate->setVariable('STATUS_MESSAGE',trim($this->statusMessage));
    }


    function init()
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

        $objResult = $objDatabase->Execute("SELECT areas.area_id AS area_id,
                           areas.parent_area_id AS parent_area_id,
                           areas.area_name AS area_name,
                           areas.type AS type,
                           areas.uri AS uri,
                           areas.target AS target,
                           modules.name AS module_name
                      FROM  ".DBPREFIX."backend_areas AS areas
                      INNER JOIN ".DBPREFIX."modules AS modules
                      ON modules.id=areas.module_id
                     WHERE is_active=1 AND (type = 'group' OR type = 'navigation')
                       ".$sqlWhereString."
                  ORDER BY areas.order_id ASC");
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
     * @global object $objModules
     */
    function getMenu()
    {
        global $objModules, $_CORELANG, $objTemplate;

        $objTemplate->addBlockfile('NAVIGATION_OUTPUT', 'navigation_output', 'index_navigation.html');
        reset($this->arrMenuItems);

        foreach ( $this->arrMenuGroups as $group_id => $group_data ) {
            // Module group menu and module check!
// TODO:
// $objModules->existsModuleFolders is bollocks.  Should be ignored.
            if ($group_id==2 && !$objModules->existsModuleFolders) {
                continue;
            }

            $navigation = '';
            foreach ($this->arrMenuItems as $link_data) {
                // checks if the links are childs of this area ID
                if ($link_data[0] == $group_id) {
                    if ($this->moduleExists($link_data[4])) {
                        $navigation.= "<li><a href='".strip_tags($link_data[2])."' title='".htmlentities($link_data[1], ENT_QUOTES, CONTREXX_CHARSET)."' target='".$link_data[3]."'>&raquo;&nbsp;".htmlentities($link_data[1], ENT_QUOTES, CONTREXX_CHARSET)."</a></li>\n";
                    }
                }
            }

            if (!empty($navigation)) {
                $objTemplate->setVariable(array(
                    'NAVIGATION_GROUP_NAME'    => htmlentities($_CORELANG[$group_data], ENT_QUOTES, CONTREXX_CHARSET),
                    'NAVIGATION_ID'            => $group_id,
                    'NAVIGATION_MENU'        => $navigation,
                    'NAVIGATION_STYLE'        => isset($_COOKIE['navigation_'.$group_id]) ? $_COOKIE['navigation_'.$group_id] : 'none'
                ));
                $objTemplate->parse('navigationRow');
        }
        }

        $objTemplate->setVariable('TXT_LOGOUT', $_CORELANG['TXT_LOGOUT']);
        $objTemplate->parse('navigation_output');
    }


    function moduleExists($moduleFolderName)
    {
        global $objModules;

        if (empty($moduleFolderName)) {
            return true;
        }
        return $objModules->getModuleStatusByName($moduleFolderName);
    }

}

?>
