<?php

/**
 * User Group Object
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team <info@comvation.com>
 * @version     1.0.0
 * @package     contrexx
 * @subpackage  lib_framework
 */

/**
 * User Group Object
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team <info@comvation.com>
 * @version     1.0.0
 * @package     contrexx
 * @subpackage  lib_framework
 */
class UserGroup {

    var $id;
    var $name;
    var $description;
    var $is_active;
    var $type;
    var $homepage;

    var $arrLoadedGroups = array();
    var $arrCache = array();

    var $arrAttributes = array(
        'group_id',
        'group_name',
        'group_description',
        'is_active',
        'type',
        'homepage'
    );

    var $arrTypes = array(
        'frontend',
        'backend'
    );

    var $arrUsers;
    var $arrStaticPermissions;
    var $arrDynamicPermissions;

    var $defaultType = 'frontend';

    var $EOF;

    /**
     * Contains the message if an error occurs
     *
     * @var unknown_type
     */
    var $error_msg;

    function UserGroup()
    {
        $this->__construct();
    }

    function __construct()
    {
        $this->clean();
    }

    public function getGroups(
        $filter=null, $arrSort=null, $arrAttributes=null,
        $limit=null, $offset=null)
    {
        $objGroup = clone $this;
        $objGroup->arrCache = &$this->arrCache;
        $objGroup->loadGroups($filter, $arrSort, $arrAttributes, $limit, $offset);
        return $objGroup;
    }

    private function loadGroups(
        $filter=null, $arrSort=null, $arrAttributes=null,
        $limit=null, $offset=null)
    {
        global $objDatabase;

        $this->arrLoadedGroups = array();
        $arrWhereExpressions = array('conditions' => array(), 'joins' => array());
        $arrSortExpressions = array();
        $arrSelectExpressions = array();

        // set filter
        if (is_array($filter)) {
            $arrWhereExpressions = $this->parseFilterConditions($filter);
        } elseif (!empty($filter)) {
            $arrWhereExpressions['conditions'][] =
                '`tblG`.`group_id`='.intval($filter);
        }

        // set sort order
        if (is_array($arrSort)) {
            foreach ($arrSort as $attribute => $direction) {
                if (   in_array($attribute, $this->arrAttributes)
                    && in_array(strtolower($direction), array('asc', 'desc'))) {
                    $arrSortExpressions[] = 'tblG.`'.$attribute.'` '.$direction;
                }
            }
        }

        // set field list
        if (!is_array($arrAttributes)) {
            $arrAttributes = $this->arrAttributes;
        }
        foreach ($arrAttributes as $attribute) {
            if (   in_array($attribute, $this->arrAttributes)
                && !in_array($attribute, $arrSelectExpressions)) {
                $arrSelectExpressions[] = '`tblG`.`'.$attribute.'`';
            }
        }
        if (!in_array('`tblG`.`group_id`', $arrSelectExpressions)) {
            $arrSelectExpressions[] = '`tblG`.`group_id`';
        }

        $query = '
            SELECT '.implode(', ', $arrSelectExpressions).'
              FROM `'.DBPREFIX.'access_user_groups` AS tblG'
            .(count($arrWhereExpressions['joins'])
                ? implode(' ', $arrWhereExpressions['joins']) : '')
            .(count($arrWhereExpressions['conditions'])
                ? ' WHERE '.implode(' AND ', $arrWhereExpressions['conditions'])
                : '')
            .(count($arrSortExpressions)
                ? ' ORDER BY '.implode(', ', $arrSortExpressions) : '');

        if (empty($limit)) {
            $objGroup = $objDatabase->Execute($query);
        } else {
            $objGroup = $objDatabase->SelectLimit($query, $limit, intval($offset));
        };

        if ($objGroup !== false && $objGroup->RecordCount() > 0) {
            while (!$objGroup->EOF) {
                $this->arrCache[$objGroup->fields['group_id']] = $this->arrLoadedGroups[$objGroup->fields['group_id']] = $objGroup->fields;
                $objGroup->MoveNext();
            }
            $this->first();
            return true;
        } else {
            $this->clean();
            return false;
        }
    }


    private function parseFilterConditions($arrFilter)
    {
        $arrConditions = array('conditions' => array(), 'joins' => array());
        foreach ($arrFilter as $attribute => $condition) {
            switch ($attribute) {
                case 'group_name':
                case 'group_description':
                    $arrConditions['conditions'][] = "tblG.`".$attribute."` LIKE '%".addslashes($condition)."%'";
                    break;

                case 'is_active':
                    $arrConditions['conditions'][] = 'tblG.`'.$attribute.'` = '.intval($condition);
                    break;

                case 'type':
                    $arrConditions['conditions'][] = "tblG.`".$attribute."` = '".addslashes($condition)."'";
                   break;

                case 'static':
                case 'dynamic':
                    $arrConditions['conditions'][] = 'tbl'.$attribute.'.`access_id` = '.intval($condition);
                    $arrConditions['joins'][] = ' INNER JOIN `'.DBPREFIX.'access_group_'.$attribute.'_ids` as tbl'.$attribute.' USING (`group_id`)';
                    break;
            }
        }

        return $arrConditions;
    }

    public function getGroup($id)
    {
        $objGroup = clone $this;
        $objGroup->arrCache = &$this->arrCache;
        $objGroup->load($id);
        return $objGroup;
    }

    private function load($id)
    {
        if ($id) {
            if (!isset($this->arrCache[$id])) {
                return $this->loadGroups($id);
            } else {
                $this->id = $this->arrCache[$id]['group_id'];
                $this->name = isset($this->arrCache[$id]['group_name']) ? $this->arrCache[$id]['group_name'] : '';
                $this->description = isset($this->arrCache[$id]['group_description']) ? $this->arrCache[$id]['group_description'] : '';
                $this->is_active = isset($this->arrCache[$id]['is_active']) ? (bool)$this->arrCache[$id]['is_active'] : false;
                $this->type = isset($this->arrCache[$id]['type']) ? $this->arrCache[$id]['type'] : $this->defaultType;
                $this->homepage = isset($this->arrCache[$id]['homepage']) ? $this->arrCache[$id]['homepage'] : $this->defaultType;
                $this->arrDynamicPermissions = null;
                $this->arrStaticPermissions = null;
                $this->arrUsers = null;
                $this->EOF = false;
                return true;
            }
        } else {
            $this->clean();
        }
    }

    private function loadUsers()
    {
        global $objDatabase;

        $arrUsers = array();

        $objUser = $objDatabase->Execute('
            SELECT
                tblRel.`user_id`
            FROM
                `'.DBPREFIX.'access_rel_user_group` AS tblRel
            INNER JOIN `'.DBPREFIX.'access_users` AS tblUser
            ON tblUser.`id` = tblRel.`user_id`
            WHERE tblRel.`group_id` = '.$this->id.'
            ORDER BY tblUser.`username`'
        );
        if ($objUser) {
            while (!$objUser->EOF) {
                array_push($arrUsers, $objUser->fields['user_id']);
                $objUser->MoveNext();
            }

            return $arrUsers;
        } else {
            return false;
        }
    }

    private function loadPermissions($type)
    {
        global $objDatabase;

        $arrRightIds = array();

        $objResult = $objDatabase->Execute('SELECT `access_id` FROM `'.DBPREFIX.'access_group_'.$type.'_ids` WHERE `group_id`='.$this->id);
        if ($objResult !== false) {
            while (!$objResult->EOF) {
                array_push($arrRightIds, $objResult->fields['access_id']);
                $objResult->MoveNext();
            }
            return $arrRightIds;
        } else {
            return false;
        }
    }

    private function loadDynamicPermissions()
    {
        return $this->loadPermissions('dynamic');
    }

    private function loadStaticPermissions()
    {
        return $this->loadPermissions('static');
    }

    /**
     * Store user account
     *
     * This stores the metadata of the user, which includes the username,
     * password, email, language ID, activ status and the administration status,
     * to the database.
     * If it is a new user, it also sets the registration time to the current time.
     *
     * @global ADONewConnection
     * @global array
     * @return boolean
     */
    public function store()
    {
        global $objDatabase, $_CORELANG;

        if (!$this->isUniqueGroupName() || !$this->isValidGroupName()) {
            return false;
        }


        if ($this->id) {
            if ($objDatabase->Execute("
                UPDATE `".DBPREFIX."access_user_groups`
                SET
                    `group_name` = '".addslashes($this->name)."',
                    `group_description` = '".addslashes($this->description)."',
                    `is_active` = ".intval($this->is_active).",
                    `homepage` = '".addslashes($this->homepage)."'
                WHERE `group_id`=".$this->id
            ) === false) {
                $this->error_msg = $_CORELANG['TXT_ACCESS_FAILED_TO_UPDATE_GROUP'];
                return false;
            }
        } else {
            if ($objDatabase->Execute("
                INSERT INTO `".DBPREFIX."access_user_groups` (
                    `group_name`,
                    `group_description`,
                    `is_active`,
                    `type`,
                    `homepage`
                ) VALUES (
                    '".addslashes($this->name)."',
                    '".addslashes($this->description)."',
                    ".intval($this->is_active).",
                    '".$this->type."',
                    '".addslashes($this->homepage)."'
                )"
            ) !== false) {
                $this->id = $objDatabase->Insert_ID();
            } else {
                $this->error_msg = $_CORELANG['TXT_ACCESS_FAILED_TO_CREATE_GROUP'];
                return false;
            }
        }

        if (!$this->storeUserAssociations()) {
            $this->error_msg = $_CORELANG['TXT_ACCESS_COULD_NOT_SET_USER_ASSOCIATIONS'];
            return false;
        }

        if (!$this->storePermissions()) {
            $this->error_msg = $_CORELANG['TXT_ACCESS_COULD_NOT_SET_PERMISSIONS'];
            return false;
        }

        return true;
    }

    /**
     * Store user associations
     *
     * Stores the user associations of the loaded group.
     * Returns TRUE no success, FALSE on failure.
     *
     * @global ADONewConnection
     * @return boolean
     */
    private function storeUserAssociations()
    {
        global $objDatabase;

        $status = true;
        $arrCurrentUsers = $this->loadUsers();
        $arrAddedUsers = array_diff($this->getAssociatedUserIds(), $arrCurrentUsers);
        $arrRemovedUsers = array_diff($arrCurrentUsers, $this->getAssociatedUserIds());

        foreach ($arrRemovedUsers as $userId) {
            if ($objDatabase->Execute('DELETE FROM `'.DBPREFIX.'access_rel_user_group` WHERE `group_id` = '.$this->id.' AND `user_id` = '.$userId) === false) {
                $status = false;
            }
        }

        foreach ($arrAddedUsers as $userId) {
            if ($objDatabase->Execute('INSERT INTO `'.DBPREFIX.'access_rel_user_group` (`user_id`, `group_id`) VALUES ('.$userId.', '.$this->id.')') === false) {
                $status = false;
            }
        }

        return $status;
    }

    private function storePermissions()
    {
        global $objDatabase;

        $status = true;
        foreach (array('Static', 'Dynamic') as $type) {
            $arrCurrentIds = $this->{'load'.$type.'Permissions'}();
            $arrAddedRightIds = array_diff($this->{$ids = 'arr'.$type.'Permissions'}, $arrCurrentIds);
            $arrRemovedRightIds = array_diff($arrCurrentIds, $this->$ids);
            $table = DBPREFIX.'access_group_'.strtolower($type).'_ids';

            foreach ($arrRemovedRightIds as $rightId) {
                if ($objDatabase->Execute('DELETE FROM `'.$table.'` WHERE `access_id`='.$rightId.' AND `group_id`='.$this->id) === false) {
                    $status = false;
                }
            }

            foreach ($arrAddedRightIds as $rightId) {
                if ($objDatabase->Execute('INSERT INTO `'.$table.'` (`access_id` , `group_id`) VALUES ('.$rightId.','.$this->id.')') === false) {
                    $status = false;
                }
            }
        }

        return $status;
    }

    private function clean()
    {
        $this->id = 0;
        $this->name = '';
        $this->description = '';
        $this->is_active = false;
        $this->type = $this->defaultType;
        $this->homepage = '';
        $this->arrDynamicPermissions = null;
        $this->arrStaticPermissions = null;
        $this->arrUsers = null;
        $this->EOF = true;
    }

    public function delete()
    {
        global $objDatabase, $_CORELANG;

        if ($objDatabase->Execute('DELETE FROM `'.DBPREFIX.'access_rel_user_group` WHERE `group_id` = '.$this->id) !== false && $objDatabase->Execute('DELETE FROM `'.DBPREFIX.'access_user_groups` WHERE `group_id` = '.$this->id) !== false) {
            return true;
        } else {
            $this->error_msg = sprintf($_CORELANG['TXT_ACCESS_GROUP_DELETE_FAILED'], $this->name);
            return false;
        }
    }

    /**
     * Load first group
     *
     */
    function first()
    {
        if (reset($this->arrLoadedGroups) === false || !$this->load(key($this->arrLoadedGroups))) {
            $this->EOF = true;
        } else {
            $this->EOF = false;
        }
    }

    /**
     * Load next group
     *
     */
    public function next()
    {
        if (next($this->arrLoadedGroups) === false || !$this->load(key($this->arrLoadedGroups))) {
            $this->EOF = true;
        }
    }


    public function setName($name)
    {
        $this->name = $name;
    }

    public function setDescription($description)
    {
        $this->description = $description;
    }

    public function setActiveStatus($status)
    {
        $this->is_active = (bool)$status;
    }

    public function setType($type)
    {
        $this->type = in_array($type, $this->arrTypes) ? $type : $this->defaultType;
    }

    public function setHomepage($homepage)
    {
        $this->homepage = $homepage;
    }

    /**
     * Set ID's of users which should belong to this group
     *
     * @param array $arrUsers
     * @see User, User::getUser()
     * @return void
     */
    public function setUsers($arrUsers)
    {
        $objFWUser = FWUser::getFWUserObject();
        $this->arrUsers = array();
        foreach ($arrUsers as $userId)
        {
            //if ($objFWUser->objUser->getUser($userId)) {
                $this->arrUsers[] = $userId;
            //}
        }
    }

    public function setDynamicPermissionIds($arrPermissionIds)
    {
        $this->arrDynamicPermissions = array_map('intval', $arrPermissionIds);
    }

    public function setStaticPermissionIds($arrPermissionIds)
    {
        $this->arrStaticPermissions = array_map('intval', $arrPermissionIds);
    }

    public function getLoadedGroupCount()
    {
        return count($this->arrLoadedGroups);
    }

    public function getLoadedGroupIds()
    {
        return array_keys($this->arrLoadedGroups);
    }

    public function getGroupCount($arrFilter = null)
    {
        global $objDatabase;

        $arrWhereExpressions = is_array($arrFilter) ? $this->parseFilterConditions($arrFilter) : array('joins' => array(), 'conditions' => array());

        $objGroupCount = $objDatabase->SelectLimit('
            SELECT SUM(1) AS `group_count`
            FROM `'.DBPREFIX.'access_user_groups` AS tblG'
            .(count($arrWhereExpressions['joins']) ? implode(' ', $arrWhereExpressions['joins']) : '')
            .(count($arrWhereExpressions['conditions']) ? ' WHERE '.implode(' AND ', $arrWhereExpressions['conditions']) : ''),
            1
        );

        if ($objGroupCount !== false) {
            return $objGroupCount->fields['group_count'];
        } else {
            return false;
        }
    }

    public function getUserCount($onlyActive = false)
    {
        global $objDatabase;

        $objCount = $objDatabase->SelectLimit('SELECT COUNT(1) AS `user_count` FROM `'.DBPREFIX.'access_users` AS tblUser'
            .($this->id ? ' INNER JOIN `'.DBPREFIX.'access_rel_user_group` AS tblRel ON tblRel.`user_id` = tblUser.`id` WHERE tblRel.`group_id` = '.$this->id : '')
            .($onlyActive ? (!$this->id ? ' WHERE' : ' AND').' tblUser.`active` = 1 ' : ''), 1);
        if ($objCount) {
            return $objCount->fields['user_count'];
        } else {
            return false;
        }
    }

    public function getAssociatedUserIds()
    {
        if (!isset($this->arrUsers)) {
            $this->arrUsers = $this->loadUsers();
        }
        return $this->arrUsers;
    }

    public function getDynamicPermissionIds()
    {
        if (!isset($this->arrDynamicPermissions)) {
            $this->arrDynamicPermissions = $this->loadDynamicPermissions();
        }
        return $this->arrDynamicPermissions;
    }

    public function getStaticPermissionIds()
    {
        if (!isset($this->arrStaticPermissions)) {
            $this->arrStaticPermissions = $this->loadStaticPermissions();
        }
        return $this->arrStaticPermissions;
    }

    public function getId()
    {
        return $this->id;
    }

    public function getName()
    {
        return $this->name;
    }

    public function getDescription()
    {
        return $this->description;
    }

    public function getActiveStatus()
    {
        return $this->is_active;
    }

    public function getType()
    {
        return $this->type;
    }

    public function getHomepage()
    {
        return $this->homepage;
    }

    public function getTypes()
    {
        return $this->arrTypes;
    }

    public function getErrorMsg()
    {
        return $this->error_msg;
    }


    /**
     * Is unique group name
     *
     * Checks if the group name specified by $name is unique in the system.
     *
     * @param string $name
     * @param integer $id
     * @return boolean
     */
    function isUniqueGroupName()
    {
        global $objDatabase, $_CORELANG;

        $objResult = $objDatabase->SelectLimit("SELECT 1 FROM ".DBPREFIX."access_user_groups WHERE `group_name`='".addslashes($this->name)."' AND `group_id` != ".$this->id, 1);

        if ($objResult && $objResult->RecordCount() == 0) {
            return true;
        } else {
            $this->error_msg = $_CORELANG['TXT_ACCESS_DUPLICATE_GROUP_NAME'];
            return false;
        }
    }

    function isValidGroupName()
    {
        global $_CORELANG;

        if (!empty($this->name)) {
            return true;
        } else {
            $this->error_msg = $_CORELANG['TXT_ACCESS_EMPTY_GROUP_NAME'];
            return false;
        }
    }
}

?>
