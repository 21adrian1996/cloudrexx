<?php
/**
 * Admin CP navigation
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author		Comvation Development Team <info@comvation.com>
 * @version		1.0.0
 * @package     contrexx
 * @subpackage  core
 * @todo        Edit PHP DocBlocks!
 */

/**
 * Admin CP navigation
 *
 * Class for the Admin CP navigation
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author		Comvation Development Team <info@comvation.com>
 * @access		public
 * @version		1.0.0
 * @package     contrexx
 * @subpackage  core
 * @todo        Edit PHP DocBlocks!
 */
class adminMenu
{
    var $arrMenuItems = array();
    var $arrMenuGroups = array();
    var $statusMessage;
    var $arrUserRights = array();
    var $arrUserGroups = array();


    /**
     * Constructor
     */
    function adminMenu()
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

        $sqlWhereString = "";

        if (!isset($_SESSION['auth']['is_admin']) || $_SESSION['auth']['is_admin']!=1) {
            if (count($_SESSION['auth']['static_access_ids'])>0) {
                foreach ($_SESSION['auth']['static_access_ids'] as $rightId) {
                    $sqlWhereString .= "areas.access_id=".intval($rightId)." OR ";
                }
                $sqlWhereString = " AND (".substr($sqlWhereString, 0, strlen($sqlWhereString)-4).") ";
            } else {
                $sqlWhereString = " AND areas.access_id='' ";
            }
        }

        $objResult = $objDatabase->Execute("SELECT areas.area_id AS area_id,
                           areas.parent_area_id AS parent_area_id,
                           areas.area_name AS area_name,
                           areas.module_id AS module_id,
                           areas.type AS type,
                           areas.uri AS uri,
                           areas.target AS target,
                           modules.id AS id,
                           modules.name AS module_name
                      FROM  ".DBPREFIX."backend_areas AS areas,
                            ".DBPREFIX."modules AS modules
                     WHERE is_active=1
                       AND modules.id=areas.module_id
                       ".$sqlWhereString."
                  ORDER BY areas.order_id ASC");
        if ($objResult !== false) {
            while (!$objResult->EOF) {
                if ($objResult->fields['type'] == "group") {
                    $this->arrMenuGroups[$objResult->fields['area_id']] = $objResult->fields['area_name'];
                }
                $this->arrMenuItems[$objResult->fields['area_id']] = array($objResult->fields['parent_area_id'],
                    $_CORELANG[$objResult->fields['area_name']],
                    $objResult->fields['uri'],
                    $objResult->fields['target'],
                    $objResult->fields['module_name']
                );
                $objResult->MoveNext();
            }
        }
    }

	/**
	 * gets the administration menu by user rights
	 *
	 * creates the navigation by userright
	 *
	 * @global array $_CORELANG
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
            if ($group_id==2 && !$objModules->existsModuleFolders) {
                continue;
            }

            $navigation = '';
            foreach ($this->arrMenuItems as $areaId => $link_data) {
                // checks if the links are childs of this area ID
                if ($link_data[0] == $group_id) {
                    if ($this->moduleExists($link_data[4])) {
                        $navigation .= "<li><a href='".strip_tags($link_data[2])."' title='".htmlentities($link_data[1], ENT_QUOTES, CONTREXX_CHARSET)."' target='".$link_data[3]."'>&raquo;&nbsp;".htmlentities($link_data[1], ENT_QUOTES, CONTREXX_CHARSET)."</a></li>\n";
                    }
                }
            }

            if (!empty($navigation)) {
				$objTemplate->setVariable(array(
					'NAVIGATION_GROUP_NAME'	=> htmlentities($_CORELANG[$group_data], ENT_QUOTES, CONTREXX_CHARSET),
					'NAVIGATION_ID'			=> $group_id,
					'NAVIGATION_MENU'		=> $navigation,
					'NAVIGATION_STYLE'		=> isset($_COOKIE['navigation_'.$group_id]) ? $_COOKIE['navigation_'.$group_id] : 'none'
				));
	            $objTemplate->parse('navigationRow');
            }
        }

		$objTemplate->setVariable('TXT_LOGOUT', $_CORELANG['TXT_LOGOUT']);
		$objTemplate->parse('navigation_output');
    }

    /**
     * check the user session and returns the group ids as an array
     */
    function _getUserGroups()
    {
        if (isset($_SESSION['auth']['groups']) && !empty($_SESSION['auth']['groups'])) {
            $this->arrUserGroups = $_SESSION['auth']['groups'];
        }
    }


    function moduleExists($moduleFolderName)
    {
        global $objModules;
        if (empty($moduleFolderName)) {
            return true;
        } else {
            return $objModules->getModuleStatusByName($moduleFolderName);
        }
    }
}
?>