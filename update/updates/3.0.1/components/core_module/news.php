<?php

////////////////////////////////////////////////////
//BEGIN OF NEWS CONVERTING STUFF
/*
    this was c&ped together from news/admin.class.php and news/lib/newsLib.class.php 
*/

class HackyFeedRepublisher {

    protected $arrSettings = array();

    public function runRepublishing() {
        $this->initRepublishing();
    
        FWLanguage::init();

        $langIds = array_keys(FWLanguage::getLanguageArray());
        
        foreach($langIds as $id) {
            $this->createRSS($id);
        }
    }

    protected function initRepublishing()
    {
        global  $_ARRAYLANG, $objInit, $objTemplate, $_CONFIG;

        require_once(ASCMS_CORE_PATH.'/validator.inc.php');
        require_once(ASCMS_FRAMEWORK_PATH.'/Language.class.php');

        //getSettings
        global $objDatabase;
        $query = "SELECT name, value FROM ".DBPREFIX."module_news_settings";
        $objResult = $objDatabase->Execute($query);
        while (!$objResult->EOF) {
            $this->arrSettings[$objResult->fields['name']] = $objResult->fields['value'];
            $objResult->MoveNext();
        }
    }

    protected function createRSS($langId){
        global $_CONFIG, $objDatabase; 
        $_FRONTEND_LANGID = $langId;


        if (intval($this->arrSettings['news_feed_status']) == 1) {
            $arrNews = array();
            require_once(ASCMS_FRAMEWORK_PATH.'/RSSWriter.class.php');
            $objRSSWriter = new RSSWriter();

            $objRSSWriter->characterEncoding = CONTREXX_CHARSET;
            $objRSSWriter->channelTitle = $this->arrSettings['news_feed_title'];
            $objRSSWriter->channelLink = 'http://'.$_CONFIG['domainUrl'].($_SERVER['SERVER_PORT'] == 80 ? "" : ":".intval($_SERVER['SERVER_PORT'])).ASCMS_PATH_OFFSET.'/'.FWLanguage::getLanguageParameter($_FRONTEND_LANGID, 'lang').'/'.CONTREXX_DIRECTORY_INDEX.'?section=news';
            $objRSSWriter->channelDescription = $this->arrSettings['news_feed_description'];
            $objRSSWriter->channelLanguage = FWLanguage::getLanguageParameter($_FRONTEND_LANGID, 'lang');
            $objRSSWriter->channelCopyright = 'Copyright '.date('Y').', http://'.$_CONFIG['domainUrl'];

            if (!empty($this->arrSettings['news_feed_image'])) {
                $objRSSWriter->channelImageUrl = 'http://'.$_CONFIG['domainUrl'].($_SERVER['SERVER_PORT'] == 80 ? "" : ":".intval($_SERVER['SERVER_PORT'])).$this->arrSettings['news_feed_image'];
                $objRSSWriter->channelImageTitle = $objRSSWriter->channelTitle;
                $objRSSWriter->channelImageLink = $objRSSWriter->channelLink;
            }
            $objRSSWriter->channelWebMaster = $_CONFIG['coreAdminEmail'];

            $itemLink = "http://".$_CONFIG['domainUrl'].($_SERVER['SERVER_PORT'] == 80 ? "" : ":".intval($_SERVER['SERVER_PORT'])).ASCMS_PATH_OFFSET.'/'.FWLanguage::getLanguageParameter($_FRONTEND_LANGID, 'lang').'/'.CONTREXX_DIRECTORY_INDEX.'?section=news&amp;cmd=details&amp;newsid=';

            $query = "
                SELECT      tblNews.id,
                            tblNews.date,
                            tblNews.title,
                            tblNews.text,
                            tblNews.redirect,
                            tblNews.source,
                            tblNews.catid AS categoryId,
                            tblNews.teaser_frames AS teaser_frames,
                            tblNews.teaser_text,
                            tblCategory.name AS category
                FROM        ".DBPREFIX."module_news AS tblNews
                INNER JOIN  ".DBPREFIX."module_news_categories AS tblCategory
                USING       (catid)
                WHERE       tblNews.status=1
                    AND     tblNews.lang = ".$_FRONTEND_LANGID."
                    AND     (tblNews.startdate <= CURDATE() OR tblNews.startdate = '0000-00-00 00:00:00')
                    AND     (tblNews.enddate >= CURDATE() OR tblNews.enddate = '0000-00-00 00:00:00')"
                    .($this->arrSettings['news_message_protection'] == '1' ? " AND tblNews.frontend_access_id=0 " : '')
                            ."ORDER BY tblNews.date DESC";

            if (($objResult = $objDatabase->SelectLimit($query, 20)) !== false && $objResult->RecordCount() > 0) {
                while (!$objResult->EOF) {
                    if (empty($objRSSWriter->channelLastBuildDate)) {
                        $objRSSWriter->channelLastBuildDate = date('r', $objResult->fields['date']);
                    }
                    $arrNews[$objResult->fields['id']] = array(
                        'date'          => $objResult->fields['date'],
                        'title'         => $objResult->fields['title'],
                        'text'          => empty($objResult->fields['redirect']) ? (!empty($objResult->fields['teaser_text']) ? nl2br($objResult->fields['teaser_text']).'<br /><br />' : '').$objResult->fields['text'] : (!empty($objResult->fields['teaser_text']) ? nl2br($objResult->fields['teaser_text']) : ''),
                        'redirect'      => $objResult->fields['redirect'],
                        'source'        => $objResult->fields['source'],
                        'category'      => $objResult->fields['category'],
                        'teaser_frames' => explode(';', $objResult->fields['teaser_frames']),
                        'categoryId'    => $objResult->fields['categoryId']
                    );
                    $objResult->MoveNext();
                }
            }

            // create rss feed
            $objRSSWriter->xmlDocumentPath = ASCMS_FEED_PATH.'/news_'.FWLanguage::getLanguageParameter($_FRONTEND_LANGID, 'lang').'.xml';
            foreach ($arrNews as $newsId => $arrNewsItem) {
                $objRSSWriter->addItem(
                    contrexx_raw2xml($arrNewsItem['title']),
                    (empty($arrNewsItem['redirect'])) ? ($itemLink.$newsId.(isset($arrNewsItem['teaser_frames'][0]) ? '&amp;teaserId='.$arrNewsItem['teaser_frames'][0] : '')) : htmlspecialchars($arrNewsItem['redirect'], ENT_QUOTES, CONTREXX_CHARSET),
                    contrexx_raw2xml($arrNewsItem['text']),
                    '',
                    array('domain' => "http://".$_CONFIG['domainUrl'].($_SERVER['SERVER_PORT'] == 80 ? "" : ":".intval($_SERVER['SERVER_PORT'])).ASCMS_PATH_OFFSET.'/'.FWLanguage::getLanguageParameter($_FRONTEND_LANGID, 'lang').'/'.CONTREXX_DIRECTORY_INDEX.'?section=news&amp;category='.$arrNewsItem['categoryId'], 'title' => $arrNewsItem['category']),
                    '',
                    '',
                    '',
                    $arrNewsItem['date'],
                    array('url' => htmlspecialchars($arrNewsItem['source'], ENT_QUOTES, CONTREXX_CHARSET), 'title' => contrexx_raw2xml($arrNewsItem['title']))
               );
            }
            $status = $objRSSWriter->write();

            // create headlines rss feed
            $objRSSWriter->removeItems();
            $objRSSWriter->xmlDocumentPath = ASCMS_FEED_PATH.'/news_headlines_'.FWLanguage::getLanguageParameter($_FRONTEND_LANGID, 'lang').'.xml';
            foreach ($arrNews as $newsId => $arrNewsItem) {
                $objRSSWriter->addItem(
                    contrexx_raw2xml($arrNewsItem['title']),
                    $itemLink.$newsId.(isset($arrNewsItem['teaser_frames'][0]) ? "&amp;teaserId=".$arrNewsItem['teaser_frames'][0] : ""),
                    '',
                    '',
                    array('domain' => 'http://'.$_CONFIG['domainUrl'].($_SERVER['SERVER_PORT'] == 80 ? '' : ':'.intval($_SERVER['SERVER_PORT'])).ASCMS_PATH_OFFSET.'/'.FWLanguage::getLanguageParameter($_FRONTEND_LANGID, 'lang').'/'.CONTREXX_DIRECTORY_INDEX.'?section=news&amp;category='.$arrNewsItem['categoryId'], 'title' => $arrNewsItem['category']),
                    '',
                    '',
                    '',
                    $arrNewsItem['date']
                );
            }
            $statusHeadlines = $objRSSWriter->write();

            $objRSSWriter->feedType = 'js';
            $objRSSWriter->xmlDocumentPath = ASCMS_FEED_PATH.'/news_'.FWLanguage::getLanguageParameter($_FRONTEND_LANGID, 'lang').'.js';
            $objRSSWriter->write();

            /*
            if (count($objRSSWriter->arrErrorMsg) > 0) {
                $this->strErrMessage .= implode('<br />', $objRSSWriter->arrErrorMsg);
            }
            if (count($objRSSWriter->arrWarningMsg) > 0) {
                $this->strErrMessage .= implode('<br />', $objRSSWriter->arrWarningMsg);
            }
            */
        } else {
            @unlink(ASCMS_FEED_PATH.'/news_'.FWLanguage::getLanguageParameter($_FRONTEND_LANGID, 'lang').'.xml');
            @unlink(ASCMS_FEED_PATH.'/news_headlines_'.FWLanguage::getLanguageParameter($_FRONTEND_LANGID, 'lang').'.xml');
            @unlink(ASCMS_FEED_PATH.'/news_'.FWLanguage::getLanguageParameter($_FRONTEND_LANGID, 'lang').'.js');
        }
    }
}
//END OF NEWS CONVERTING STUFF

function _newsUpdate() {
    global $objDatabase, $_CONFIG, $objUpdate, $_ARRAYLANG;


    /************************************************
    * EXTENSION:	Placeholder NEWS_LINK replaced	*
    *				by NEWS_LINK_TITLE				*
    * ADDED:		Contrexx v2.1.0					*
    ************************************************/
    if ($objUpdate->_isNewerVersion($_CONFIG['coreCmsVersion'], '2.1.0')) {
        try {
            \Cx\Lib\UpdateUtil::migrateContentPage(8, null, '{NEWS_LINK}', '{NEWS_LINK_TITLE}', '2.1.0');
        } catch (\Cx\Lib\UpdateException $e) {
            return \Cx\Lib\UpdateUtil::DefaultActionHandler($e);
        }
    }



    /************************************************
    * EXTENSION:	Front- and backend permissions  *
    * ADDED:		Contrexx v2.1.0					*
    ************************************************/
    $query = "SELECT 1 FROM `".DBPREFIX."module_news_settings` WHERE `name` = 'news_message_protection'";
    $objResult = $objDatabase->SelectLimit($query, 1);
    if ($objResult) {
        if ($objResult->RecordCount() == 0) {
            $query = "INSERT INTO `".DBPREFIX."module_news_settings` (`name`, `value`) VALUES ('news_message_protection', '1')";
            if ($objDatabase->Execute($query) === false) {
                return _databaseError($query, $objDatabase->ErrorMsg());
            }
        }
    } else {
        return _databaseError($query, $objDatabase->ErrorMsg());
    }

    $query = "SELECT 1 FROM `".DBPREFIX."module_news_settings` WHERE `name` = 'news_message_protection_restricted'";
    $objResult = $objDatabase->SelectLimit($query, 1);
    if ($objResult) {
        if ($objResult->RecordCount() == 0) {
            $query = "INSERT INTO `".DBPREFIX."module_news_settings` (`name`, `value`) VALUES ('news_message_protection_restricted', '1')";
            if ($objDatabase->Execute($query) === false) {
                return _databaseError($query, $objDatabase->ErrorMsg());
            }
        }
    } else {
        return _databaseError($query, $objDatabase->ErrorMsg());
    }

    $arrColumns = $objDatabase->MetaColumnNames(DBPREFIX.'module_news');
    if ($arrColumns === false) {
        setUpdateMsg(sprintf($_ARRAYLANG['TXT_UNABLE_GETTING_DATABASE_TABLE_STRUCTURE'], DBPREFIX.'module_news'));
        return false;
    }

    if (!in_array('frontend_access_id', $arrColumns)) {
        $query = "ALTER TABLE `".DBPREFIX."module_news` ADD `frontend_access_id` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `validated`";
        if ($objDatabase->Execute($query) === false) {
            return _databaseError($query, $objDatabase->ErrorMsg());
        }
    }
    if (!in_array('backend_access_id', $arrColumns)) {
        $query = "ALTER TABLE `".DBPREFIX."module_news` ADD `backend_access_id` INT(10) UNSIGNED NOT NULL DEFAULT '0' AFTER `frontend_access_id`";
        if ($objDatabase->Execute($query) === false) {
            return _databaseError($query, $objDatabase->ErrorMsg());
        }
    }



    /************************************************
    * EXTENSION:	Thunbmail Image                 *
    * ADDED:		Contrexx v2.1.0					*
    ************************************************/
    $arrColumns = $objDatabase->MetaColumnNames(DBPREFIX.'module_news');
    if ($arrColumns === false) {
        setUpdateMsg(sprintf($_ARRAYLANG['TXT_UNABLE_GETTING_DATABASE_TABLE_STRUCTURE'], DBPREFIX.'module_news'));
        return false;
    }

    if (!in_array('teaser_image_thumbnail_path', $arrColumns)) {
        $query = "ALTER TABLE `".DBPREFIX."module_news` ADD `teaser_image_thumbnail_path` TEXT NOT NULL AFTER `teaser_image_path`";
        if ($objDatabase->Execute($query) === false) {
            return _databaseError($query, $objDatabase->ErrorMsg());
        }
    }



    try{
        // delete obsolete table  contrexx_module_news_access
        \Cx\Lib\UpdateUtil::drop_table(DBPREFIX.'module_news_access');
        # fix some ugly NOT NULL without defaults
        \Cx\Lib\UpdateUtil::table(
            DBPREFIX . 'module_news',
            array(
                'id'                         => array('type'=>'INT(6) UNSIGNED','notnull'=>true,  'primary'     =>true,   'auto_increment' => true),
                'date'                       => array('type'=>'INT(14)',            'notnull'=>false, 'default_expr'=>'NULL'),
                'title'                      => array('type'=>'VARCHAR(250)',       'notnull'=>true,  'default'     =>''),
                'text'                       => array('type'=>'MEDIUMTEXT',         'notnull'=>true),
                'redirect'                   => array('type'=>'VARCHAR(250)',       'notnull'=>true,  'default'     =>''),
                'source'                     => array('type'=>'VARCHAR(250)',       'notnull'=>true,  'default'     =>''),
                'url1'                       => array('type'=>'VARCHAR(250)',       'notnull'=>true,  'default'     =>''),
                'url2'                       => array('type'=>'VARCHAR(250)',       'notnull'=>true,  'default'     =>''),
                'catid'                      => array('type'=>'INT(2) UNSIGNED',    'notnull'=>true,  'default'     =>0),
                'lang'                       => array('type'=>'INT(2) UNSIGNED',    'notnull'=>true,  'default'     =>0),
                'userid'                     => array('type'=>'INT(6) UNSIGNED',    'notnull'=>true,  'default'     =>0),
                'startdate'                  => array('type'=>'DATETIME',           'notnull'=>true,  'default'     =>'0000-00-00 00:00:00'),
                'enddate'                    => array('type'=>'DATETIME',           'notnull'=>true,  'default'     =>'0000-00-00 00:00:00'),
                'status'                     => array('type'=>'TINYINT(4)',         'notnull'=>true,  'default'     =>1),
                'validated'                  => array('type'=>"ENUM('0','1')",      'notnull'=>true,  'default'     =>0),
                'frontend_access_id'         => array('type'=>'INT(10) UNSIGNED',   'notnull'=>true,  'default'     =>0),
                'backend_access_id'          => array('type'=>'INT(10) UNSIGNED',   'notnull'=>true,  'default'     =>0),
                'teaser_only'                => array('type'=>"ENUM('0','1')",      'notnull'=>true,  'default'     =>0),
                'teaser_frames'              => array('type'=>'TEXT',               'notnull'=>true),
                'teaser_text'                => array('type'=>'TEXT',               'notnull'=>true),
                'teaser_show_link'           => array('type'=>'TINYINT(1) UNSIGNED','notnull'=>true,  'default'     =>1),
                'teaser_image_path'          => array('type'=>'TEXT',               'notnull'=>true),
                'teaser_image_thumbnail_path'=> array('type'=>'TEXT',               'notnull'=>true),
                'changelog'                  => array('type'=>'INT(14)',            'notnull'=>true,  'default'     =>0),
            ),
            array(#indexes
                'newsindex' =>array ('type' => 'FULLTEXT', 'fields' => array('text','title','teaser_text'))
            )
        );

    }
    catch (\Cx\Lib\UpdateException $e) {
        // we COULD do something else here..
        DBG::trace();
        return \Cx\Lib\UpdateUtil::DefaultActionHandler($e);
    }

    //encoding was a little messy in 2.1.4. convert titles and teasers to their raw representation
    if($_CONFIG['coreCmsVersion'] == "2.1.4") {
        try{
            $res = \Cx\Lib\UpdateUtil::sql('SELECT `id`, `title`, `teaser_text` FROM `'.DBPREFIX.'module_news` WHERE `changelog` > '.mktime(0,0,0,12,15,2010));
            while($res->MoveNext()) {
                $title = $res->fields['title'];
                $teaserText = $res->fields['teaser_text'];
                $id = $res->fields['id'];

                //title is html entity style
                $title = html_entity_decode($title, ENT_QUOTES, CONTREXX_CHARSET);
                //teaserText is html entity style, but no contrexx was specified on encoding
                $teaserText = html_entity_decode($teaserText);

                \Cx\Lib\UpdateUtil::sql('UPDATE `'.DBPREFIX.'module_news` SET `title`="'.addslashes($title).'", `teaser_text`="'.addslashes($teaserText).'" where `id`='.$id);
            }

            $hfr = new HackyFeedRepublisher();
            $hfr->runRepublishing();
        }
        catch (\Cx\Lib\UpdateException $e) {
            DBG::trace();
            return \Cx\Lib\UpdateUtil::DefaultActionHandler($e);
        }
    }


    /****************************
    * ADDED:    Contrexx v3.0.0 *
    *****************************/
    try {
        \Cx\Lib\UpdateUtil::table(
            DBPREFIX.'module_news_locale',
            array(
                'news_id'        => array('type' => 'INT(11)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'primary' => true),
                'lang_id'        => array('type' => 'INT(11)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'primary' => true, 'after' => 'news_id'),
                'is_active'      => array('type' => 'INT(1)', 'unsigned' => true, 'notnull' => true, 'default' => '1', 'after' => 'lang_id'),
                'title'          => array('type' => 'VARCHAR(250)', 'notnull' => true, 'default' => '', 'after' => 'is_active'),
                'text'           => array('type' => 'mediumtext', 'notnull' => true, 'after' => 'title'),
                'teaser_text'    => array('type' => 'text', 'notnull' => true, 'after' => 'text')
            ),
            array(
                'newsindex'      => array('fields' => array('text', 'title', 'teaser_text'), 'type' => 'FULLTEXT')
            )
        );

        \Cx\Lib\UpdateUtil::table(
            DBPREFIX.'module_news_categories_locale',
            array(
                'category_id'    => array('type' => 'INT(11)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'primary' => true),
                'lang_id'        => array('type' => 'INT(11)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'primary' => true, 'after' => 'category_id'),
                'name'           => array('type' => 'VARCHAR(100)', 'notnull' => true, 'default' => '', 'after' => 'lang_id')
            ),
            array(
                'name'           => array('fields' => array('name'), 'type' => 'FULLTEXT')
            )
        );

        \Cx\Lib\UpdateUtil::table(
            DBPREFIX.'module_news_types',
            array(
                'typeid'     => array('type' => 'INT(2)', 'unsigned' => true, 'notnull' => true, 'primary' => true, 'auto_increment' => true)
            )
        );


        \Cx\Lib\UpdateUtil::table(
            DBPREFIX.'module_news_types_locale',
            array(
                'lang_id'    => array('type' => 'INT(11)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'primary' => true),
                'type_id'    => array('type' => 'INT(11)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'primary' => true, 'after' => 'lang_id'),
                'name'       => array('type' => 'VARCHAR(100)', 'notnull' => true, 'default' => '', 'after' => 'type_id')
            ),
            array(
                'name'       => array('fields' => array('name'), 'type' => 'FULLTEXT')
            )
        );

        \Cx\Lib\UpdateUtil::table(
            DBPREFIX.'module_news_settings_locale',
            array(
                'name'       => array('type' => 'VARCHAR(50)', 'notnull' => true, 'default' => '', 'primary' => true),
                'lang_id'    => array('type' => 'INT(11)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'primary' => true, 'after' => 'name'),
                'value'      => array('type' => 'VARCHAR(250)', 'notnull' => true, 'default' => '', 'after' => 'lang_id')
            ),
            array(
                'name'       => array('fields' => array('name'), 'type' => 'FULLTEXT')
            )
        );

        \Cx\Lib\UpdateUtil::table(
            DBPREFIX.'module_news_comments',
            array(
                'id'             => array('type' => 'INT(11)', 'unsigned' => true, 'notnull' => true, 'primary' => true, 'auto_increment' => true),
                'title'          => array('type' => 'VARCHAR(250)', 'notnull' => true, 'default' => '', 'after' => 'id'),
                'text'           => array('type' => 'mediumtext', 'notnull' => true, 'after' => 'title'),
                'newsid'         => array('type' => 'INT(6)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'text'),
                'date'           => array('type' => 'INT(14)', 'notnull' => false, 'default' => NULL,'after' => 'newsid'),
                'poster_name'    => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => '', 'after' => 'date'),
                'userid'         => array('type' => 'INT(5)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'poster_name'),
                'ip_address'     => array('type' => 'VARCHAR(15)', 'notnull' => true, 'default' => '0.0.0.0', 'after' => 'userid'),
                'is_active'      => array('type' => 'ENUM(\'0\',\'1\')', 'notnull' => true, 'default' => '1', 'after' => 'ip_address')
            )
        );

        \Cx\Lib\UpdateUtil::table(
            DBPREFIX.'module_news_stats_view',
            array(
                'user_sid'       => array('type' => 'CHAR(32)', 'notnull' => true),
                'news_id'        => array('type' => 'INT(6)', 'unsigned' => true, 'notnull' => true, 'after' => 'user_sid'),
                'time'           => array('type' => 'timestamp', 'notnull' => true, 'default_expr' => 'CURRENT_TIMESTAMP', 'on_update' => 'CURRENT_TIMESTAMP', 'after' => 'news_id')
            ),
            array(
                'idx_user_sid'   => array('fields' => array('user_sid')),
                'idx_news_id'    => array('fields' => array('news_id'))
            )
        );

        \Cx\Lib\UpdateUtil::table(
            DBPREFIX.'module_news',
            array(
                'id'                             => array('type' => 'INT(6)', 'unsigned' => true, 'notnull' => true, 'primary' => true, 'auto_increment' => true),
                'date'                           => array('type' => 'INT(14)', 'notnull' => false, 'default' => NULL, 'after' => 'id'),
                'title'                          => array('type' => 'VARCHAR(250)', 'notnull' => true, 'default' => '', 'after' => 'date'),
                'text'                           => array('type' => 'mediumtext', 'notnull' => true, 'after' => 'title'),
                'redirect'                       => array('type' => 'VARCHAR(250)', 'notnull' => true, 'default' => '', 'after' => 'text'),
                'source'                         => array('type' => 'VARCHAR(250)', 'notnull' => true, 'default' => '', 'after' => 'redirect'),
                'url1'                           => array('type' => 'VARCHAR(250)', 'notnull' => true, 'default' => '', 'after' => 'source'),
                'url2'                           => array('type' => 'VARCHAR(250)', 'notnull' => true, 'default' => '', 'after' => 'url1'),
                'catid'                          => array('type' => 'INT(2)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'url2'),
                'lang'                           => array('type' => 'INT(2)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'catid'),
                'typeid'                         => array('type' => 'INT(2)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'lang'),
                'publisher'                      => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => '', 'after' => 'typeid'),
                'publisher_id'                   => array('type' => 'INT(5)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'publisher'),
                'author'                         => array('type' => 'VARCHAR(255)', 'notnull' => true, 'default' => '', 'after' => 'publisher_id'),
                'author_id'                      => array('type' => 'INT(5)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'author'),
                'userid'                         => array('type' => 'INT(6)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'author_id'),
                'startdate'                      => array('type' => 'timestamp', 'notnull' => true, 'default' => '0000-00-00 00:00:00', 'after' => 'userid'),
                'enddate'                        => array('type' => 'timestamp', 'notnull' => true, 'default' => '0000-00-00 00:00:00', 'after' => 'startdate'),
                'status'                         => array('type' => 'TINYINT(4)', 'notnull' => true, 'default' => '1', 'after' => 'enddate'),
                'validated'                      => array('type' => 'ENUM(\'0\',\'1\')', 'notnull' => true, 'default' => '0', 'after' => 'status'),
                'frontend_access_id'             => array('type' => 'INT(10)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'validated'),
                'backend_access_id'              => array('type' => 'INT(10)', 'unsigned' => true, 'notnull' => true, 'default' => '0', 'after' => 'frontend_access_id'),
                'teaser_only'                    => array('type' => 'ENUM(\'0\',\'1\')', 'notnull' => true, 'default' => '0', 'after' => 'backend_access_id'),
                'teaser_frames'                  => array('type' => 'text', 'notnull' => true, 'after' => 'teaser_only'),
                'teaser_text'                    => array('type' => 'text', 'notnull' => true, 'after' => 'teaser_frames'),
                'teaser_show_link'               => array('type' => 'TINYINT(1)', 'unsigned' => true, 'notnull' => true, 'default' => '1', 'after' => 'teaser_text'),
                'teaser_image_path'              => array('type' => 'text', 'notnull' => true, 'after' => 'teaser_show_link'),
                'teaser_image_thumbnail_path'    => array('type' => 'text', 'notnull' => true, 'after' => 'teaser_image_path'),
                'changelog'                      => array('type' => 'INT(14)', 'notnull' => true, 'default' => '0', 'after' => 'teaser_image_thumbnail_path'),
                'allow_comments'                 => array('type' => 'TINYINT(1)', 'notnull' => true, 'default' => '0', 'after' => 'changelog')
            ),
            array(
                'newsindex'                      => array('fields' => array('text','title','teaser_text'), 'type' => 'FULLTEXT')
            )
        );


        $arrColumnsNews = $objDatabase->MetaColumnNames(DBPREFIX.'module_news');
        if ($arrColumnsNews === false) {
            setUpdateMsg(sprintf($_ARRAYLANG['TXT_UNABLE_GETTING_DATABASE_TABLE_STRUCTURE'], DBPREFIX.'module_news'));
            return false;
        }
        if (isset($arrColumnsNews['TITLE']) && isset($arrColumnsNews['TEXT']) && isset($arrColumnsNews['TEASER_TEXT']) && isset($arrColumnsNews['LANG'])) {
            \Cx\Lib\UpdateUtil::sql('
                INSERT INTO `'.DBPREFIX.'module_news_locale` (`news_id`, `lang_id`, `title`, `text`, `teaser_text`)
                SELECT `id`, `lang`, `title`, `text`, `teaser_text` FROM `'.DBPREFIX.'module_news`
                ON DUPLICATE KEY UPDATE `news_id` = `news_id`
            ');
        }
        if (isset($arrColumnsNews['TITLE'])) {
            \Cx\Lib\UpdateUtil::sql('ALTER TABLE `'.DBPREFIX.'module_news` DROP `title`');
        }
        if (isset($arrColumnsNews['TEXT'])) {
            \Cx\Lib\UpdateUtil::sql('ALTER TABLE `'.DBPREFIX.'module_news` DROP `text`');
        }
        if (isset($arrColumnsNews['TEASER_TEXT'])) {
            \Cx\Lib\UpdateUtil::sql('ALTER TABLE `'.DBPREFIX.'module_news` DROP `teaser_text`');
        }
        if (isset($arrColumnsNews['LANG'])) {
            \Cx\Lib\UpdateUtil::sql('ALTER TABLE `'.DBPREFIX.'module_news` DROP `lang`');
        }

        $arrColumnsNewsCategories = $objDatabase->MetaColumnNames(DBPREFIX.'module_news_categories');
        if ($arrColumnsNewsCategories === false) {
            setUpdateMsg(sprintf($_ARRAYLANG['TXT_UNABLE_GETTING_DATABASE_TABLE_STRUCTURE'], DBPREFIX.'module_news_categories'));
            return false;
        }
        if (isset($arrColumnsNewsCategories['NAME'])) {
            \Cx\Lib\UpdateUtil::sql('
                INSERT INTO '.DBPREFIX.'module_news_categories_locale (`category_id`, `lang_id`, `name`)
                SELECT c.catid, l.id, c.name
                FROM '.DBPREFIX.'module_news_categories AS c, '.DBPREFIX.'languages AS l
                ORDER BY c.catid, l.id
                ON DUPLICATE KEY UPDATE `category_id` = `category_id`
            ');
            \Cx\Lib\UpdateUtil::sql('
                INSERT INTO '.DBPREFIX.'module_news_categories_locale (`category_id`, `lang_id`, `name`)
                SELECT c.catid, l.id, c.name
                FROM '.DBPREFIX.'module_news_categories AS c, '.DBPREFIX.'languages AS l
                ORDER BY c.catid, l.id
                ON DUPLICATE KEY UPDATE `category_id` = `category_id`
            ');
            \Cx\Lib\UpdateUtil::sql('ALTER TABLE `'.DBPREFIX.'module_news_categories` DROP `name`');
        }
        if (isset($arrColumnsNewsCategories['LANG'])) {
            \Cx\Lib\UpdateUtil::sql('ALTER TABLE `'.DBPREFIX.'module_news_categories` DROP `lang`');
        }

        \Cx\Lib\UpdateUtil::sql('
            INSERT INTO `'.DBPREFIX.'module_news_settings_locale` (`name`, `lang_id`, `value`)
            SELECT n.`name`, l.`id`, n.`value`
            FROM `'.DBPREFIX.'module_news_settings` AS n, `'.DBPREFIX.'languages` AS l
            WHERE n.`name` IN ("news_feed_description", "news_feed_title")
            ORDER BY n.`name`, l.`id`
            ON DUPLICATE KEY UPDATE `'.DBPREFIX.'module_news_settings_locale`.`name` = `'.DBPREFIX.'module_news_settings_locale`.`name`
        ');

        \Cx\Lib\UpdateUtil::sql('DELETE FROM `'.DBPREFIX.'module_news_settings` WHERE `name` IN ("news_feed_title", "news_feed_description")');

        \Cx\Lib\UpdateUtil::sql('
            INSERT INTO `'.DBPREFIX.'module_news_settings` (`name`, `value`)
            VALUES  ("news_comments_activated", "0"),
                    ("news_comments_anonymous", "0"),
                    ("news_comments_autoactivate", "0"),
                    ("news_comments_notification", "1"),
                    ("news_comments_timeout", "30"),
                    ("news_default_teasers", ""),
                    ("news_use_types","0"),
                    ("news_use_top","0"),
                    ("news_top_days","10"),
                    ("news_top_limit","10"),
                    ("news_assigned_author_groups", "0"),
                    ("news_assigned_publisher_groups", "0")
            ON DUPLICATE KEY UPDATE `name` = `name`
        ');

    } catch (\Cx\Lib\UpdateException $e) {
        return \Cx\Lib\UpdateUtil::DefaultActionHandler($e);
    }

    \Cx\Lib\UpdateUtil::migrateContentPage('news', 'details', array('{NEWS_DATE}','{NEWS_COMMENTS_DATE}'), array('{NEWS_LONG_DATE}', '{NEWS_COMMENTS_LONG_DATE}'), '3.0.1');

    return true;
}
