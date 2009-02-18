<?php
function _statsUpdate()
{
	global $objDatabase, $_ARRAYLANG;

	// remove redundancies
	if (!isset($_SESSION['contrexx_update']['update']['update_stats'])) {
		$_SESSION['contrexx_update']['update']['update_stats'] = array();
	}

	foreach (array(
		'stats_browser' => array(
			'obsoleteIndex'	=> 'name',
			'unique' => array('name'),
			'change' => "`name` `name` VARCHAR(255) BINARY NOT NULL DEFAULT ''"
		),
		'stats_colourdepth' => array(
			'obsoleteIndex'	=> 'depth',
			'unique' => array('depth')
		),
		'stats_country' => array(
			'obsoleteIndex'	=> 'country',
			'unique' => array('country'),
			'change' => "`country` `country` VARCHAR(100) BINARY NOT NULL DEFAULT ''"
		),
		'stats_hostname' => array(
			'obsoleteIndex'	=> 'hostname',
			'unique' => array('hostname'),
			'change' => "`hostname` `hostname` VARCHAR(255) BINARY NOT NULL DEFAULT ''"
		),
		'stats_operatingsystem' => array(
			'obsoleteIndex'	=> 'name',
			'unique' => array('name'),
			'change' => "`name` `name` VARCHAR(255) BINARY NOT NULL DEFAULT ''"
		),
		'stats_referer' => array(
			'obsoleteIndex'	=> 'uri',
			'unique' => array('uri'),
			'change' => "`uri` `uri` VARCHAR(255) BINARY NOT NULL DEFAULT ''"
		),
		'stats_requests' => array(
			'obsoleteIndex'	=> 'page',
			'unique' => array('page'),
			'count' => 'visits',
			'change' => "`page` `page` VARCHAR(255) BINARY NOT NULL DEFAULT ''"
		),
		'stats_requests_summary' => array(
			'obsoleteIndex'	=> 'type',
			'unique' => array('type', 'timestamp')
		),
		'stats_screenresolution' => array(
			'obsoleteIndex'	=> 'resolution',
			'unique' => array('resolution')
		),
		'stats_search' => array(
			'unique' => array('name', 'external'),
			'change' => "`name` `name` VARCHAR(100) BINARY NOT NULL DEFAULT ''"
		),
		'stats_spiders' => array(
			'obsoleteIndex'	=> 'page',
			'unique' => array('page'),
			'change' => "`page` `page` VARCHAR(100) BINARY DEFAULT NULL"
		),
		'stats_spiders_summary'	=> array(
			'obsoleteIndex'	=> 'unqiue',
			'unique' => array('name'),
			'change' => "`name` `name` VARCHAR(255) BINARY NOT NULL DEFAULT ''"
		),
		'stats_visitors_summary' => array(
			'obsoleteIndex'	=> 'type',
			'unique' => array('type', 'timestamp')
		)
	) as $table => $arrUnique) {
		do {
			if (in_array($table, $_SESSION['contrexx_update']['update']['update_stats'])) {
				break;
			} elseif (!checkTimeoutLimit()) {
				return false;
			}

			if (isset($arrUnique['change'])) {
				$query = 'ALTER TABLE `'.DBPREFIX.$table.'` CHANGE '.$arrUnique['change'];
				if ($objDatabase->Execute($query) === false) {
                    return _databaseError($query, $objDatabase->ErrorMsg());
				}
			}

			$arrIndexes = $objDatabase->MetaIndexes(DBPREFIX.$table);
			if ($arrIndexes !== false) {
				if (isset($arrIndexes['unique'])) {
					$_SESSION['contrexx_update']['update']['update_stats'][] = $table;
					break;
				} elseif (isset($arrIndexes[$arrUnique['obsoleteIndex']])) {
					$query = 'ALTER TABLE `'.DBPREFIX.$table.'` DROP INDEX `'.$arrUnique['obsoleteIndex'].'`';
					if ($objDatabase->Execute($query) === false) {
						return _databaseError($query, $objDatabase->ErrorMsg());
					}
				}
			} else {
				setUpdateMsg(sprintf($_ARRAYLANG['TXT_UNABLE_GETTING_DATABASE_TABLE_STRUCTURE'], DBPREFIX.$table));
				return false;
			}

			$query = 'SELECT `'.implode('`,`', $arrUnique['unique']).'`, COUNT(`id`) AS redundancy FROM `'.DBPREFIX.$table.'` GROUP BY `'.implode('`,`', $arrUnique['unique']).'` ORDER BY redundancy DESC';
			$objEntry = $objDatabase->SelectLimit($query, 10);
			if ($objEntry !== false) {
				while (!$objEntry->EOF) {
					if (!checkTimeoutLimit()) {
						return false;
					}
					$lastRedundancyCount = $objEntry->fields['redundancy'];
					if ($objEntry->fields['redundancy'] > 1) {
						$where = array();
						foreach ($arrUnique['unique'] as $unique) {
							$where[] = "`".$unique."` = '".addslashes($objEntry->fields[$unique])."'";
						}
						$query = 'DELETE FROM `'.DBPREFIX.$table.'` WHERE '.implode(' AND ', $where).' ORDER BY `'.(isset($arrUnique['count']) ? $arrUnique['count'] : 'count').'` LIMIT '.($objEntry->fields['redundancy']-1);
						if ($objDatabase->Execute($query) === false) {
							return _databaseError($query, $objDatabase->ErrorMsg());
						}
					} else {
						break;
					}
					$objEntry->MoveNext();
				}
			} else {
				return _databaseError($query, $objDatabase->ErrorMsg());
			}

			if ($objEntry->RecordCount() == 0 || $lastRedundancyCount < 2) {
				$query = 'ALTER TABLE `'.DBPREFIX.$table.'` ADD UNIQUE `unique` (`'.implode('`,`', $arrUnique['unique']).'`)';
				if ($objDatabase->Execute($query) == false) {
					return _databaseError($query, $objDatabase->ErrorMsg());
				}
				$_SESSION['contrexx_update']['update']['update_stats'][] = $table;
				break;
			}
		} while ($objEntry->RecordCount() > 1);
	}

	if(empty($_SESSION['contrexx_update']['update']['update_stats']['utf8'])){
        $query = "ALTER TABLE `".DBPREFIX."stats_search` CHANGE `name` `name` VARCHAR( 100 ) CHARACTER SET latin1 COLLATE latin1_bin NOT NULL";
        if($objDatabase->Execute($query)){
            $_SESSION['contrexx_update']['update']['update_stats']['utf8'] = 1;
            $query = "ALTER TABLE `".DBPREFIX."stats_search` CHANGE `name` `name` VARCHAR( 100 ) CHARACTER SET binary NOT NULL";
            if($_SESSION['contrexx_update']['update']['update_stats']['utf8'] == 1 && $objDatabase->Execute($query)){
                $_SESSION['contrexx_update']['update']['update_stats']['utf8'] = 2;
                $query = "ALTER TABLE `".DBPREFIX."stats_search` CHANGE `name` `name` VARCHAR( 100 ) CHARACTER SET utf8 COLLATE utf8_bin NOT NULL";
                if($_SESSION['contrexx_update']['update']['update_stats']['utf8'] == 2 && $objDatabase->Execute($query)){
                    $_SESSION['contrexx_update']['update']['update_stats']['utf8'] = 3;
                }else{
                    return _databaseError($query, $objDatabase->ErrorMsg());
                }
            }else{
                return _databaseError($query, $objDatabase->ErrorMsg());
            }
        }else{
            return _databaseError($query, $objDatabase->ErrorMsg());
        }
    }

	return true;
}
?>
