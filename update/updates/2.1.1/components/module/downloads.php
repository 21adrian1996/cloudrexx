<?php
function _downloadsUpdate()
{
	global $objDatabase, $_ARRAYLANG, $_CORELANG;

	/************************************************
	* EXTENSION:	Initial creation of the         *
    *               database tables                 *
	* ADDED:		Contrexx v2.1.0					*
	************************************************/
    $arrTables  = $objDatabase->MetaTables('TABLES');
    if (!sizeof($arrTables)) {
        setUpdateMsg($_ARRAYLANG['TXT_UNABLE_DETERMINE_DATABASE_STRUCTURE']);
        return false;
    }

    $tables = array(
        DBPREFIX.'module_downloads_category' => "CREATE TABLE `".DBPREFIX."module_downloads_category` (
             `id` int(11) unsigned NOT NULL auto_increment,
             `parent_id` int(11) unsigned NOT NULL default '0',
             `is_active` tinyint(1) unsigned NOT NULL default '1',
             `visibility` tinyint(1) unsigned NOT NULL default '1',
             `owner_id` int(5) unsigned NOT NULL default '0',
             `order` int(3) unsigned NOT NULL default '0',
             `deletable_by_owner` tinyint(1) unsigned NOT NULL default '1',
             `modify_access_by_owner` tinyint(1) unsigned NOT NULL default '1',
             `read_access_id` int(11) unsigned NOT NULL default '0',
             `add_subcategories_access_id` int(11) unsigned NOT NULL default '0',
             `manage_subcategories_access_id` int(11) unsigned NOT NULL default '0',
             `add_files_access_id` int(11) unsigned NOT NULL default '0',
             `manage_files_access_id` int(11) unsigned NOT NULL default '0',
             `image` varchar(255) NOT NULL default '',
              PRIMARY KEY (`id`),
              KEY `is_active` (`is_active`),
              KEY `visibility` (`visibility`)
            ) ENGINE=MyISAM",
        #################################################################################
        DBPREFIX.'module_downloads_category_locale' => "CREATE TABLE `".DBPREFIX."module_downloads_category_locale` (
             `lang_id` int(11) unsigned NOT NULL default '0',
             `category_id` int(11) unsigned NOT NULL default '0',
             `name` varchar(255) NOT NULL default '',
             `description` text NOT NULL,
              PRIMARY KEY (`lang_id`,`category_id`),
              FULLTEXT KEY `name` (`name`),
              FULLTEXT KEY `description` (`description`)
            ) ENGINE=MyISAM",
        #################################################################################
        DBPREFIX.'module_downloads_download' => "CREATE TABLE `".DBPREFIX."module_downloads_download` (
             `id` int(10) unsigned NOT NULL auto_increment,
             `type` enum('file','url') NOT NULL default 'file',
             `mime_type` enum('image','document','pdf','media','archive','application','link') NOT NULL default 'image',
             `source` varchar(255) NOT NULL default '',
             `source_name` varchar(255) NOT NULL default '',
             `icon` enum('_blank','avi','bmp','css','doc','dot','exe','fla','gif','htm','html','inc','jpg','js','mp3','nfo','pdf','php','png','pps','ppt','rar','swf','txt','wma','xls','zip') NOT NULL default '_blank',
             `size` int(10) unsigned NOT NULL default '0',
             `image` varchar(255) NOT NULL default '',
             `owner_id` int(5) unsigned NOT NULL default '0',
             `access_id` int(10) unsigned NOT NULL default '0',
             `license` varchar(255) NOT NULL default '',
             `version` varchar(10) NOT NULL default '',
             `author` varchar(100) NOT NULL default '',
             `website` varchar(255) NOT NULL default '',
             `ctime` int(14) unsigned NOT NULL default '0',
             `mtime` int(14) unsigned NOT NULL default '0',
             `is_active` tinyint(3) unsigned NOT NULL default '0',
             `visibility` tinyint(1) unsigned NOT NULL default '1',
             `order` int(3) unsigned NOT NULL default '0',
             `views` int(10) unsigned NOT NULL default '0',
             `download_count` int(10) unsigned NOT NULL default '0',
              PRIMARY KEY (`id`),
              KEY `is_active` (`is_active`),
              KEY `visibility` (`visibility`)
            ) ENGINE=MyISAM",
        #################################################################################
        DBPREFIX.'module_downloads_download_locale' => "CREATE TABLE `".DBPREFIX."module_downloads_download_locale` (
             `lang_id` int(11) unsigned NOT NULL default '0',
             `download_id` int(11) unsigned NOT NULL default '0',
             `name` varchar(255) NOT NULL default '',
             `description` text NOT NULL,
              PRIMARY KEY (`lang_id`,`download_id`),
              FULLTEXT KEY `name` (`name`),
              FULLTEXT KEY `description` (`description`)
            ) ENGINE=MyISAM",
        #################################################################################
        DBPREFIX.'module_downloads_rel_download_category' => "CREATE TABLE `".DBPREFIX."module_downloads_rel_download_category` (
             `download_id` int(10) unsigned NOT NULL default '0',
             `category_id` int(10) unsigned NOT NULL default '0',
             `order` int(3) unsigned NOT NULL default '0',
              PRIMARY KEY (`download_id`,`category_id`)
            ) ENGINE=MyISAM",
        #################################################################################
        DBPREFIX.'module_downloads_rel_download_download' => "CREATE TABLE `".DBPREFIX."module_downloads_rel_download_download` (
             `id1` int(10) unsigned NOT NULL default '0',
             `id2` int(10) unsigned NOT NULL default '0',
              PRIMARY KEY (`id1`,`id2`)
            ) ENGINE=MyISAM",
        #################################################################################
        DBPREFIX.'module_downloads_settings' => "CREATE TABLE `".DBPREFIX."module_downloads_settings` (
             `id` int(11) NOT NULL auto_increment,
             `name` varchar(32) NOT NULL default '',
             `value` varchar(255) NOT NULL default '',
              PRIMARY KEY (`id`)
            ) ENGINE=MyISAM"
   );

    foreach ($tables as $name => $query) {
        #print_r($arrTables);
        if (in_array($name, $arrTables)) {
            continue;
        }
        if ($objDatabase->Execute($query) === false) {
            return _databaseError($query, $objDatabase->ErrorMsg());
        }
    }





	/************************************************
	* EXTENSION:	Initial adding of the           *
    *               settings values                 *
	* ADDED:		Contrexx v2.1.0					*
	************************************************/
    $arrSettings = array(
        'overview_cols_count'           => '2',
        'overview_max_subcats'          => '5',
        'use_attr_size'                 => '1',
        'use_attr_license'              => '1',
        'use_attr_version'              => '1',
        'use_attr_author'               => '1',
        'use_attr_website'              => '1',
        'most_viewed_file_count'        => '5',
        'most_downloaded_file_count'    => '5',
        'most_popular_file_count'       => '5',
        'newest_file_count'             => '5',
        'updated_file_count'            => '5',
        'new_file_time_limit'           => '604800',
        'updated_file_time_limit'       => '604800'
    );

    foreach ($arrSettings as $name => $value) {
        $query = "SELECT 1 FROM `".DBPREFIX."module_downloads_settings` WHERE `name` = '".$name."'";
        $objResult = $objDatabase->SelectLimit($query, 1);
        if ($objResult) {
            if ($objResult->RecordCount() == 0) {
                $query = "INSERT INTO `".DBPREFIX."module_downloads_settings` (`name`, `value`) VALUES ('".$name."', '".$value."')";
                if ($objDatabase->Execute($query) === false) {
                    return _databaseError($query, $objDatabase->ErrorMsg());
                }
            }
        } else {
            return _databaseError($query, $objDatabase->ErrorMsg());
        }
    }





	/************************************************
	* BUGFIX:	Set write access to the upload dir  *
	************************************************/
	require_once ASCMS_FRAMEWORK_PATH.'/File.class.php';
	$objFile = new File();
	if (is_writeable(ASCMS_DOWNLOADS_IMAGES_PATH) || $objFile->setChmod(ASCMS_DOWNLOADS_IMAGES_PATH, ASCMS_DOWNLOADS_IMAGES_WEB_PATH, '')) {
    	if ($mediaDir = @opendir(ASCMS_DOWNLOADS_IMAGES_PATH)) {
    		while($file = readdir($mediaDir)) {
    			if ($file != '.' && $file != '..') {
    				if (!is_writeable(ASCMS_DOWNLOADS_IMAGES_PATH.'/'.$file) && !$objFile->setChmod(ASCMS_DOWNLOADS_IMAGES_PATH.'/', ASCMS_DOWNLOADS_IMAGES_WEB_PATH.'/', $file)) {
    					setUpdateMsg(sprintf($_ARRAYLANG['TXT_SET_WRITE_PERMISSON_TO_FILE'], ASCMS_DOWNLOADS_IMAGES_PATH.'/'.$file, $_CORELANG['TXT_UPDATE_TRY_AGAIN']), 'msg');
    					return false;
    				}
    			}
			}
    	} else {
    		setUpdateMsg(sprintf($_ARRAYLANG['TXT_SET_WRITE_PERMISSON_TO_DIR_AND_CONTENT'], ASCMS_DOWNLOADS_IMAGES_PATH.'/', $_CORELANG['TXT_UPDATE_TRY_AGAIN']), 'msg');
    		return false;
		}
    } else {
    	setUpdateMsg(sprintf($_ARRAYLANG['TXT_SET_WRITE_PERMISSON_TO_DIR_AND_CONTENT'], ASCMS_DOWNLOADS_IMAGES_PATH.'/', $_CORELANG['TXT_UPDATE_TRY_AGAIN']), 'msg');
    	return false;
    }

    return true;
}
?>
