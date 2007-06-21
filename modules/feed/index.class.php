<?php
/**
 * Feed
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Ivan Schmid <ivan.schmid@astalavista.ch>
 * @author      Paulo M. Santos <pmsantos@astalavista.net>
 * @package     contrexx
 * @subpackage  module_feed
 * @todo        Edit PHP DocBlocks!
 */

// SECURITY CHECK
if (eregi('index.class.php', $_SERVER['PHP_SELF'])) {
    header('Location: index.php');
    die();
}

/**
 * Includes
 */
require_once ASCMS_LIBRARY_PATH . '/PEAR/XML/RSS.class.php';
require_once ASCMS_MODULE_PATH . '/feed/feedLib.class.php';

if(ini_get('allow_url_fopen') != 1){
	@ini_set('allow_url_fopen', '1');
	if(ini_get('allow_url_fopen') != 1){
		die("Please set the variable 'allow_url_fopen' to the value 1");
	}
}

/**
 * Feed
 *
 * News Syndication Class to manage XML feeds
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Ivan Schmid <ivan.schmid@astalavista.ch>
 * @author      Paulo M. Santos <pmsantos@astalavista.net>
 * @package     contrexx
 * @subpackage  module_feed
 */
class feed extends feedLibrary{
	var $_objTpl;
	var $pageTitle;
	var $statusMessage;
	var $feedpath;

    // CONSTRUCTOR
    function feed($pageContent) {
	    global  $_ARRAYLANG;
	    $this->pageContent = $pageContent;
		$this->_objTpl = &new HTML_Template_Sigma('.');
		$this->_objTpl->setErrorHandling(PEAR_ERROR_DIE);
	}

	// GET PAGE
    function getFeedPage() {
    	if (isset($_GET['cmd']) && $_GET['cmd'] == "newsML") {
    		$this->_showNewsML();
    	} else {
    		$this->showNews();
    	}
    	return $this->_objTpl->get();
    }

    function _showNewsML()
    {
    	global $objDatabase, $_CORELANG;

    	$this->_objTpl->setTemplate($this->pageContent, true, true);

    	$id = intval($_GET['id']);
		$catId = isset($_GET['catid']) ? intval($_GET['catid']) : 0;

    	$objDocument = $objDatabase->Execute("SELECT providerId, publicIdentifier, urgency, headLine, dataContent, thisRevisionDate FROM ".DBPREFIX."module_feed_newsml_documents WHERE id=".$id);
    	if ($objDocument !== false) {
			$pics = array();
			$objAssociated = $objDatabase->Execute("SELECT pId_slave FROM ".DBPREFIX."module_feed_newsml_association WHERE pId_master='".$objDocument->fields['publicIdentifier']."' ORDER BY pId_slave");
			if ($objAssociated !== false) {
				while (!$objAssociated->EOF) {
					$objPic = $objDatabase->SelectLimit("SELECT properties, source FROM ".DBPREFIX."module_feed_newsml_documents WHERE publicIdentifier LIKE '".$objAssociated->fields['pId_slave']."%' AND media_type='Photo'", 1);
					if ($objPic !== false) {
						if ($objPic->RecordCount() == 1) {
							$arrTmpProperties = explode(';', $objPic->fields['properties']);
							foreach ($arrTmpProperties as $property) {
								$arrPair = explode(':', $property);
								$arrProperties[base64_decode($arrPair[0])] = base64_decode($arrPair[1]);
							}

							$objProvider = $objDatabase->Execute("SELECT path FROM ".DBPREFIX."module_feed_newsml_providers WHERE providerId='".$objDocument->fields['providerId']."'");

							$pics[$objAssociated->fields['pId_slave']] = array(
								'source'	=> $objProvider->fields['path'].'/'.$objPic->fields['source'],
								'label'		=> isset($arrProperties['label']) ? $arrProperties['label'] : '',
								'width'		=> isset($arrProperties['Width']) ? $arrProperties['Width'] : '',
								'height'	=> isset($arrProperties['Height']) ? $arrProperties['Height'] : ''
							);

							if ($pics[$objAssociated->fields['pId_slave']]['width'] == 170 && $pics[$objAssociated->fields['pId_slave']]['height'] == 115) {
								$pic = $pics[$objAssociated->fields['pId_slave']];
								break;
							}
						}
					}

					$objAssociated->MoveNext();
				}

				if (!isset($pic) && count($pics) > 0) {
					reset($pics);
					$pic = current($pics);
				}
			}

			$showPics = isset($pic);
			if (isset($pic) && $catId) {
				$objCategory = $objDatabase->SelectLimit("SELECT `showPics` FROM `".DBPREFIX."module_feed_newsml_categories` WHERE `id`=".$catId, 1);
				if ($objCategory !== false) {
					$showPics = $objCategory->fields['showPics'] == '1' ? true : false;
				}
			}

			$arrWeekDays = explode(',', $_CORELANG['TXT_DAY_ARRAY']);
			$arrMonths = explode(',', $_CORELANG['TXT_MONTH_ARRAY']);

    		$this->_objTpl->setVariable(array(
    			'NEWSML_IMAGE'	=> $showPics ? '<img src="'.$pic['source'].'" width="'.$pic['width'].'" height="'.$pic['height'].'" alt="'.$pic['label'].'" />' : '',
    			'NEWSML_TITLE'	=> $objDocument->fields['headLine'],
    			'NEWSML_TEXT'	=> $objDocument->fields['dataContent'],
    			'NEWSML_DATE'		=> $arrWeekDays[date('w', $objDocument->fields['thisRevisionDate'])].', '.date('j', $objDocument->fields['thisRevisionDate']).'. '.$arrMonths[date('n', $objDocument->fields['thisRevisionDate'])-1].' '.date('Y', $objDocument->fields['thisRevisionDate']).' / '.date('G:i', $objDocument->fields['thisRevisionDate']).' h',
    			'NEWSML_LONG_DATE'	=> date(ASCMS_DATE_FORMAT, $objDocument->fields['thisRevisionDate']),
				'NEWSML_SHORT_DATE'	=> date(ASCMS_DATE_SHORT_FORMAT, $objDocument->fields['thisRevisionDate'])
    		));

    		$objDocument->MoveNext();
    	}
    }



	function showNews() {
		global $objDatabase, $_ARRAYLANG, $_LANGID;

		$this->_objTpl->setTemplate($this->pageContent, true, true);

		//feed path
		$this->feedpath = ASCMS_FEED_PATH . '/';

		//active (with $_LANGID) categories
		$query = "SELECT id,
		                   name
		              FROM ".DBPREFIX."module_feed_category
		             WHERE status = '1'
		               AND lang = '".$_LANGID."'
		          ORDER BY pos";
		if(($objResult = $objDatabase->Execute($query)))

		while (!$objResult->EOF) {
			$cat_id[$objResult->fields['id']]   = $objResult->fields['id'];
			$cat_name[$objResult->fields['id']] = $objResult->fields['name'];
			$objResult->MoveNext();
		}


		//active news
		$query = "SELECT id,
		                   subid,
		                   name
		              FROM ".DBPREFIX."module_feed_news
		             WHERE status = '1'
		          ORDER BY pos";
		$objResult = $objDatabase->Execute($query);

		while (!$objResult->EOF) {
			$news_subid[$objResult->fields['subid']][$objResult->fields['id']] = $objResult->fields['subid'];
			$news_id[$objResult->fields['subid']][$objResult->fields['id']]    = $objResult->fields['id'];
			$news_name[$objResult->fields['subid']][$objResult->fields['id']]  = $objResult->fields['name'];
			$objResult->MoveNext();
		}

		//no empty categories
		if(is_array($cat_id)){
			foreach($cat_id as $x){
				if(!isset($news_id[$x])){
					unset($cat_id[$x]);
					unset($cat_name[$x]);
				}
			}
		}

		if(count($cat_id) == 0){
			unset($cat_id);
		}

		//output structure
		if(!is_array($cat_id)){
			if(!isset($_GET['cat']) and !isset($_GET['news'])){
				$this->_objTpl->setVariable('FEED_NO_NEWSFEED', $_ARRAYLANG['TXT_FEED_NO_NEWSFEED']);
			}else{
				header("Location: index.php?section=feed");
			}
		} else {
			if ($this->_objTpl->blockExists('feed_cat')) {
				foreach($cat_id as $x){
					//out cat
					$this->_objTpl->setVariable('FEED_CAT_NAME', $cat_name[$x]);

					//out news
					foreach($news_id[$x] as $y){
						$this->_objTpl->setVariable(array(
						    'FEED_NEWS_LINK'   => '?section=feed&amp;cat='.$news_subid[$x][$y].'&amp;news='.$news_id[$x][$y],
						    'FEED_NEWS_NAME'   => strip_tags($news_name[$x][$y])
						));
						$this->_objTpl->parse('feed_news');
					}
					$this->_objTpl->parse('feed_cat');
				}
			}

			// first access
			if(!isset($_GET['cat']) and !isset($_GET['news'])){
				reset($cat_id);
				$_GET['cat'] = current($cat_id);
				reset($news_id[$_GET['cat']]);
				$_GET['news'] = current($news_id[$_GET['cat']]);
				/*
				foreach($cat_id as $x)
				{
					$_GET['cat'] = $cat_id[$x];

					foreach($news_id[$x] as $y)
					{
						$_GET['news'] = $news_id[$x][$y];
						break;
					}
					break;
				}*/
			}

			$getCat  = intval($_GET['cat']);
			$getNews = intval($_GET['news']);

			//refresh control
			$query = "SELECT time,
		                       cache
		                  FROM ".DBPREFIX."module_feed_news
			             WHERE id = '".$getNews."'
			               AND subid = '".$getCat."'
		                   AND status = '1'";
			$objResult = $objDatabase->Execute($query);
			if($objResult->RecordCount() == 0){
				header("Location: index.php?section=feed");
				die;
			}


			$old_time = $objResult->fields['time'] + $objResult->fields['cache'];
			$time = time();

			if($time >= $old_time){
				$this->showNewsRefresh($getNews, $time, $this->feedpath);
			}

			$query = "SELECT name,
		                       filename,
		         FROM_UNIXTIME(time, '%d. %M %Y, %H:%i') AS time,
		                       articles,
		                       image
			              FROM ".DBPREFIX."module_feed_news
			             WHERE id = '".$getNews."'
			               AND subid = '".$getCat."'
		                   AND status = '1'";
			$objResult = $objDatabase->Execute($query);

			//output selected news
			$news_name = $objResult->fields['name'];

			$this->_objTpl->setVariable(array(
			    'FEED_CAT'    => $cat_name[$getCat],
			    'FEED_PAGE'   => $news_name
			));

			$filename = $this->feedpath.$objResult->fields['filename'];

			//rss class
			$rss =& new XML_RSS($filename);
			$rss->parse();
			//channel info
			$out_title = strip_tags($rss->channel['title']);
			$out_time  = strip_tags($objResult->fields['time']);

			//image
			foreach ($rss->getImages() as $img) {
				if($img['url'] != '' && $objResult->fields['image'] == 1) {
					$out_image = '<img src="'.strip_tags($img['url']).'" alt="" /><br />';
				}
			}


			$this->_objTpl->setVariable(array(
			    'FEED_IMAGE'            => $out_image,
			    'FEED_TITLE'            => $out_title,
			    'FEED_TIME'             => $out_time,
			    'TXT_FEED_LAST_UPTDATE' => $_ARRAYLANG['TXT_FEED_LAST_UPDATE']
			));

			//items
			$x = 0;
			foreach ($rss->getItems() as $value){
				if($x < $objResult->fields['articles']){
					$this->_objTpl->setVariable(array(
					    'FEED_LINK'   => strip_tags($value['link']),
					    'FEED_NAME'   => strip_tags($rss->struct[$x]['title']),
					));
					$this->_objTpl->parse('feed_output_news');
					$x++;
				}
			}
			$this->_objTpl->parse('feed_show_news');
		}
	}
}
?>
