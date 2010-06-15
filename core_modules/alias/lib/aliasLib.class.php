<?php
/**
 * Alias library
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author        Comvation Development Team <info@comvation.com>
 * @version        1.0.0
 * @package     contrexx
 * @subpackage  core_module_alias
 * @todo        Edit PHP DocBlocks!
 */
/**
 * @ignore
 */
require_once ASCMS_FRAMEWORK_PATH.'/File.class.php';
/**
 * @ignore
 */
require_once ASCMS_FRAMEWORK_PATH.'/FWHtAccess.class.php';

/**
 * Alias library
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author        Comvation Development Team <info@comvation.com>
 * @version        1.0.0
 * @package     contrexx
 * @subpackage  core_module_alias
 * @todo        Edit PHP DocBlocks!
 */
class aliasLib
{
    var $_arrAliasTypes = array(
        'local',
        'url'
    );

    var $langId;

    var $_arrConfig = null;

    var $objFWHtAccess;

    function __construct($langId = 0)
    {
        $this->objFWHtAccess = new FWHtAccess();
        if (($result = $this->objFWHtAccess->loadHtAccessFile('/.htaccess')) !== true) {;
            $this->arrStatusMsg['error'][] = $result;
        }
        $this->langId = intval($langId) > 0 ? $langId : FRONTEND_LANG_ID;
    }

    function _getConfig()
    {
        if (!is_array($this->_arrConfig)) {
            $this->_initConfig();
        }

        return $this->_arrConfig;
    }

    function _initConfig()
    {
        global $objDatabase;

        $objConfig = $objDatabase->Execute('SELECT `setname`, `setvalue` FROM `'.DBPREFIX.'settings` WHERE `setmodule` = 41');
        if ($objConfig !== false) {
            $this->_arrConfig = array();
            while (!$objConfig->EOF) {
                $this->_arrConfig[$objConfig->fields['setname']] = $objConfig->fields['setvalue'];
                $objConfig->MoveNext();
            }
        }
    }

    function _getAliases($limit = null, $allLanguages = false)
    {
        global $objDatabase, $_CONFIG;

        $arrAliases = array();
        $arrLocalAliases = array();
        $pos = isset($_GET['pos']) ? intval($_GET['pos']) : 0;

        $query = "
            SELECT
                t.`id`        AS targetId,
                t.`type`      AS targetType,
                t.`url`       AS targetUrl,
                s.`id`        AS sourceId,
                s.`isdefault` AS isdefault,
                s.`url`       AS sourceUrl,
                s.`lang_id`   AS langId
            FROM `".DBPREFIX."module_alias_target` AS t
            INNER JOIN `".DBPREFIX."module_alias_source` AS s ON s.`target_id` = t.`id`"
            .(!$allLanguages ? "WHERE s.`lang_id` = ".$this->langId : ' ')."
            ORDER BY sourceUrl ASC";

        if (!empty($limit)) {
            $objAlias = $objDatabase->SelectLimit($query, $_CONFIG['corePagingLimit'], $pos);
        } else {
            $objAlias = $objDatabase->Execute($query);
        }

        if ($objAlias !== false) {
            while (!$objAlias->EOF) {
                if (!isset($arrAliases[$objAlias->fields['langId']][$objAlias->fields['targetId']])) {
                    $arrAliases[$objAlias->fields['langId']][$objAlias->fields['targetId']] = array(
                        'type'        => $objAlias->fields['targetType'],
                        'url'        => $objAlias->fields['targetUrl'],
                        'sources'    => array()
                    );

                    if ($objAlias->fields['targetType'] == 'local') {
                        $arrLocalAliases[intval($objAlias->fields['targetUrl'])] = $objAlias->fields['targetId'];
                    }
                }
                array_push($arrAliases[$objAlias->fields['langId']][$objAlias->fields['targetId']]['sources'], array(
                    'id'        => $objAlias->fields['sourceId'],
                    'isdefault' => $objAlias->fields['isdefault'],
                    'url'        => $objAlias->fields['sourceUrl']
                ));

                $objAlias->MoveNext();
            }

            if (count($arrLocalAliases)) {
                $arrLocalAliasIds = array_keys($arrLocalAliases);
                $objAlias = $objDatabase->Execute("
                    SELECT
                        n.`catid`,
                        n.`catname`,
                        n.`cmd`,
                        n.`lang`,
                        m.`name`
                    FROM
                        `".DBPREFIX."content_navigation` AS n
                    LEFT OUTER JOIN `".DBPREFIX."modules` AS m ON m.`id` = n.`module`
                    WHERE (n.`catid` = ".implode(' OR n.`catid` = ', $arrLocalAliasIds).")".(!$allLanguages ? " AND n.`lang` = ".$this->langId : ' ')
                );
                if ($objAlias !== false) {
                    while (!$objAlias->EOF) {
                        if (isset($arrAliases[$objAlias->fields['lang']][$arrLocalAliases[$objAlias->fields['catid']]])) {
                            $arrAliases[$objAlias->fields['lang']][$arrLocalAliases[$objAlias->fields['catid']]]['title'] = $objAlias->fields['catname'];
                            $arrAliases[$objAlias->fields['lang']][$arrLocalAliases[$objAlias->fields['catid']]]['pageUrl'] = ASCMS_PATH_OFFSET
                                .($_CONFIG['useVirtualLanguagePath'] == 'on' ? '/'.FWLanguage::getLanguageParameter($objAlias->fields['lang'], 'lang') : null)
                                .'/'.CONTREXX_DIRECTORY_INDEX
                                .(!empty($objAlias->fields['name']) ? '?section='.$objAlias->fields['name'] : '?page='.$objAlias->fields['catid'])
                                .(empty($objAlias->fields['cmd']) ? '' : '&cmd='.$objAlias->fields['cmd']);
                        }

                        $objAlias->MoveNext();
                    }
                }
            }
        }

        return $arrAliases;
    }

    function is_alias_valid($alias) {
        return !file_exists(ASCMS_DOCUMENT_ROOT.'/'.$alias);
    }

    function _getAliasesCount()
    {
        global $objDatabase;

        $objAlias = $objDatabase->Execute("
            SELECT SUM(1) AS aliasCount
            FROM `".DBPREFIX."module_alias_target` AS t
            INNER JOIN `".DBPREFIX."module_alias_source` AS s ON s.`target_id` = t.`id`
            WHERE s.`lang_id`=".$this->langId);

        if ($objAlias !== false) {
            return $objAlias->fields['aliasCount'];
        } else {
            return 0;
        };
    }

    function _getAlias($aliasId)
    {
        global $objDatabase;

        $objAlias = $objDatabase->Execute("
            SELECT
                t.`type` AS targetType,
                t.`url` AS targetUrl,
                s.`id` AS sourceId,
                s.`isdefault` AS isdefault,
                s.`url` AS sourceUrl,
                s.`lang_id` AS langId
            FROM `".DBPREFIX."module_alias_target` AS t
            LEFT OUTER JOIN `".DBPREFIX."module_alias_source` AS s ON s.`target_id` = t.`id`
            WHERE t.`id` = ".$aliasId." AND s.`lang_id` = ".$this->langId."
            ORDER BY sourceUrl ASC"
        );

        if ($objAlias !== false && $objAlias->RecordCount() > 0) {
            while (!$objAlias->EOF) {
                if (!isset($arrAlias)) {
                    $arrAlias = array(
                        'type'       => $objAlias->fields['targetType'],
                        'url'        => $objAlias->fields['targetUrl'],
                        'sources'    => array(),
                        'lang'       => $objAlias->fields['langId']
                    );
                }

                array_push($arrAlias['sources'], array(
                    'id'        => $objAlias->fields['sourceId'],
                    'isdefault' => $objAlias->fields['isdefault'],
                    'url'        => $objAlias->fields['sourceUrl']
                ));

                $objAlias->MoveNext();
            }

            $this->_setAliasTarget($arrAlias);

            return $arrAlias;
        } else {
            return false;
        }
    }

    function _setAliasTarget(&$arrAlias)
    {
        global $objDatabase, $_CONFIG;

        if ($arrAlias['type'] == 'local') {
            $objAlias = $objDatabase->SelectLimit("
                SELECT
                    n.`catid`,
                    n.`catname`,
                    n.`cmd`,
                    m.`name`
                FROM
                    `".DBPREFIX."content_navigation` AS n
                LEFT OUTER JOIN `".DBPREFIX."modules` AS m ON m.`id` = n.`module`
                WHERE n.`catid` = ".intval($arrAlias['url']), 1
            );
            if ($objAlias !== false && $objAlias->RecordCount() == 1) {
                $arrAlias['title'] = $objAlias->fields['catname'];
                $arrAlias['pageUrl'] = ASCMS_PATH_OFFSET
                    .($_CONFIG['useVirtualLanguagePath'] == 'on' ? '/'.FWLanguage::getLanguageParameter($arrAlias['lang'], 'lang') : null)
                    .'/'.CONTREXX_DIRECTORY_INDEX
                    .(!empty($objAlias->fields['name']) ? '?section='.$objAlias->fields['name'] : '?page='.$objAlias->fields['catid'])
                    .(empty($objAlias->fields['cmd']) ? '' : '&cmd='.$objAlias->fields['cmd']);
            }
        }
    }

    function _addAlias($arrAlias)
    {
        global $objDatabase;

        $objRS = $objDatabase->SelectLimit("
            SELECT `id` FROM `".DBPREFIX."module_alias_target`
            WHERE `url` = '".addslashes($arrAlias['url'])."'", 1);
        if ($objRS !== false && $objRS->RecordCount() == 1){
            $aliasId = $objRS->fields['id'];
        } else {
            if ($objDatabase->Execute("
                INSERT INTO `".DBPREFIX."module_alias_target` (`type`, `url`)
                VALUES ('".addslashes($arrAlias['type'])."','".addslashes($arrAlias['url'])."')
                ON DUPLICATE KEY
                UPDATE `type`='".addslashes($arrAlias['type'])."', `url`='".addslashes($arrAlias['url'])."'") !== false) {
                $aliasId = $objDatabase->Insert_ID();
            } else {
                return false;
            }
        }
        return $this->_setAliasSources($aliasId, $arrAlias);
    }

    function _updateAlias($aliasId, $arrAlias)
    {
        global $objDatabase;

        $upd_query = "
            UPDATE `".DBPREFIX."module_alias_target`
            SET `type`      = '".addslashes($arrAlias['type'])."',
                `url`       = '".addslashes($arrAlias['url'])."'
            WHERE `id` =       ".intval    ($aliasId);

        if ($objDatabase->Execute($upd_query) !== false) {
            return $this->_setAliasSources($aliasId, $arrAlias);
        } else {
            return false;
        }
    }

    function _setAliasSources($aliasId, $arrAlias)
    {
        global $objDatabase;

        $error = false;

        if (($arrOldAlias = $this->_getAlias($aliasId)) !== false) {
            foreach ($arrOldAlias['sources'] as $arrOldSource) {
                $stillPresent = false;
                foreach ($arrAlias['sources'] as $arrSource) {
                    if (!empty($arrSource['id']) && $arrSource['id'] == $arrOldSource['id']) {
                        if (($arrSource['isdefault'] != $arrOldSource['isdefault'] ) or  ($arrSource['url'] != $arrOldSource['url'])) {
                            $qry_update = "
                                UPDATE `".DBPREFIX."module_alias_source`
                                    SET `url`       = '".addslashes($arrSource['url'])      ."',
                                        `isdefault` = '".intval    ($arrSource['isdefault'])."',
                                        `lang_id`   = ".$this->langId."
                                WHERE `id` = ".intval($arrSource['id'])."
                                    AND `target_id` = ".intval($aliasId)
                                ;

                            if ($objDatabase->Execute($qry_update) === false) {
                                $error = true;
                            }
                        }
                        $stillPresent = true;
                        break;
                    }
                }

                if (!$stillPresent) {
                    if ($objDatabase->Execute("DELETE FROM `".DBPREFIX."module_alias_source` WHERE `id` = ".intval($arrOldSource['id'])." AND `target_id` = ".intval($aliasId).' AND `lang_id`='.$this->langId) === false) {
                        $error = true;
                    }
                }
            }
        }

        foreach ($arrAlias['sources'] as $arrSource) {
            if (empty($arrSource['id'])) {
                $alias_id  = intval($aliasId);
                $isdefault = intval($arrSource['isdefault']);
                $url       = addslashes($arrSource['url']);
                $qry_insert = "
                    INSERT INTO `".DBPREFIX."module_alias_source` (`target_id`, `url`, `isdefault`, `lang_id`)
                    VALUES ($alias_id, '$url', $isdefault, $this->langId)
                    ";
                if ($objDatabase->Execute($qry_insert) === false) {
                    $error = true;
                }
            }
        }

        if (!$error) {
            if ($arrAlias['type'] == 'local') {
                $target = $arrAlias['pageUrl'];
            } else {
                $target = $arrAlias['url'];
            }

            return $this->_activateRewriteEngine();
        } else {
            return false;
        }
    }

    function _getRewriteInfo()
    {
        $arrRewriteInfo = array();
        $arrRules = $this->objFWHtAccess->getSection('core_modules__alias');

        foreach ($arrRules as $directive) {
            if (preg_match('#^\s*RewriteRule\s+\^(.+)\$\s+(.+)\s+.*$#', $directive, $arrRewriteRule)) {
                $arrRewriteInfo[$arrRewriteRule[2]][] = $arrRewriteRule[1];
            }
        }

        return $arrRewriteInfo;
    }

    function _isModRewriteInUse()
    {
        return $this->objFWHtAccess->isRewriteEngineInUse();
    }

    function _escapeStringForRegex($string) {
        $string = str_replace(array(' ', '\\\ '), '\\ ', $string);
        return str_replace(
            array('\\', '^',    '$',    '.',    '[',    ']',    '|',    '(',    ')',    '?',    '*',    '+',    '{',    '}',    ':'),
            array('\\\\', '\^',    '\$',    '\.',    '\[',    '\]',    '\|',    '\(',    '\)',    '\?',    '\*',    '\+',    '\{',    '\}',    '\:'),
            $string
        );
    }

    function _activateRewriteEngine()
    {
        $arrRewriteRules = array();
        $limit = null;
        $allLanguages = true;
        $arrDefinedAliasesByLang = $this->_getAliases($limit, $allLanguages);

        foreach ($arrDefinedAliasesByLang as $langId => $arrDefinedAliases) {
            foreach ($arrDefinedAliases as $arrDefinedAlias) {
                if ($arrDefinedAlias['type'] == 'local') {
                    if (!empty($arrDefinedAlias['pageUrl'])) {
                        $target = $arrDefinedAlias['pageUrl'];
                    } else {
                        continue;
                    }
                } else {
                    $target = $arrDefinedAlias['url'];
                }

                foreach ($arrDefinedAlias['sources'] as $arrSource) {
                    $arrRewriteRules[] = 'RewriteRule ^'.$arrSource['url'].'$    '.$target.' [L,NC]';
                }
            }
        }

        $this->objFWHtAccess->setSection('core_modules__alias', $arrRewriteRules);
        return $this->objFWHtAccess->write();
    }

    function _deactivateRewriteEngine()
    {
        $this->objFWHtAccess->removeSection('core_modules__alias');
        return $this->objFWHtAccess->write();
    }

    function _isUniqueAliasSource($url, $target, $oldTarget, $sourceId = 0)
    {
        global $objDatabase;

        if (($arrUsedAliasesInHtaccessFile = $this->_getUsedAlisesInHtaccessFile()) === false || (isset($arrUsedAliasesInHtaccessFile[$url]) && $arrUsedAliasesInHtaccessFile[$url] != $target && $arrUsedAliasesInHtaccessFile[$url] != $oldTarget)) {
            return false;
        }
        $objResult = $objDatabase->SelectLimit("SELECT 1 FROM `".DBPREFIX."module_alias_source` WHERE `id` != ".intval($sourceId)." AND `url` = '".addslashes($url)."' AND `lang_id`=".$this->langId, 1);
        if ($objResult !== false && $objResult->RecordCount() == 0) {
            return true;
        } else{
            return false;
        }
    }

    function _isUniqueAliasTarget($url, $targetId = 0)
    {
        global $objDatabase;

        $objResult = $objDatabase->SelectLimit("
            SELECT 1 FROM `".DBPREFIX."module_alias_target` AS `t`
            LEFT JOIN `".DBPREFIX."module_alias_source` AS `s` ON (`s`.`target_id` = `t`.`id`)
            WHERE `t`.`id` != ".intval($targetId)."
            AND `t`.`url` = '".addslashes($url)."'
            AND `s`.`lang_id`=".$this->langId, 1);
        if ($objResult !== false && $objResult->RecordCount() == 0) {
            return true;
        } else{
            return false;
        }
    }

    function _deleteAlias($aliasId)
    {
        global $objDatabase, $_CONFIG;

        if (($arrAlias = $this->_getAlias($aliasId)) !== false) {
            if ($objDatabase->Execute("DELETE s,t FROM `".DBPREFIX."module_alias_source` AS s INNER JOIN `".DBPREFIX."module_alias_target` AS t ON t.`id` = s.`target_id` WHERE s.`target_id` = ".intval($aliasId).' AND s.`lang_id`='.$this->langId) !== false && $this->_activateRewriteEngine()) {
                if ($_CONFIG['xmlSitemapStatus'] == 'on' && ($result = XMLSitemap::write()) !== true) {
                    $this->arrStatusMsg['error'][] = $result;
                }
                return true;
            }
            return false;
        } else {
            return false;
        }
    }

    function _getUsedAlisesInHtaccessFile()
    {
        static $arrUsedAliases;

        if (!is_array($arrUsedAliases)) {
            $objHtAccess = new File_HtAccess(ASCMS_DOCUMENT_ROOT.'/.htaccess');
            if ($objHtAccess->load() !== true) {
                return false;
            } else {
                $arrAddition = $objHtAccess->getAdditional('array');
                $arrUsedAliases = array();

                foreach ($arrAddition as $directive) {
                    if (preg_match('#^\s*RewriteRule\s+\^(.+)\$\s+(.+)\s+.*$#', $directive, $arrAlias)) {
                        $arrUsedAliases[$arrAlias[1]] = $arrAlias[2];
                    }
                }
            }
        }
        return $arrUsedAliases;
    }

    /**
     * get all alias_source entries that don't have a corresponding alias_target entry
     *
     * @param boolean $cleanUp whether to delete the aimless source entries
     */
    function _getUnusedTargets($cleanUp = false){
        global $objDatabase;

        $arrUnused = array();
        $query = "SELECT `s`.`id`
                    FROM `".DBPREFIX."module_alias_source` AS `s`
                    LEFT JOIN `".DBPREFIX."module_alias_target` AS `t` ON `t`.`id` = `s`.`target_id`
                    WHERE `t`.`id` IS NULL";
        if(($objRS = $objDatabase->Execute($query)) !== false){
            while(!$objRS->EOF){
                $arrUnused[] = $objRS->fields['id'];
                $objRS->MoveNext();
            }
        }
        if($cleanUp){
            $query = "DELETE `s`
                        FROM `".DBPREFIX."module_alias_source` AS `s`
                        LEFT JOIN `".DBPREFIX."module_alias_target` AS `t` ON `t`.`id` = `s`.`target_id`
                        WHERE `t`.`id` IS NULL";
            $objDatabase->Execute($query);
        }
        return $arrUnused;
    }

}

?>
