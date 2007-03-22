<?PHP
/**
 * News headlines
 * @copyright   CONTREXX CMS - ASTALAVISTA IT AG
 * @author Astalavista Development Team <thun@astalvista.ch>
 * @version 1.0.0 
 * @package     contrexx
 * @subpackage  core_module_news
 * @todo        Edit PHP DocBlocks!
 */

/**
 * News headlines
 *
 * Gets all the news headlines
 * @copyright   CONTREXX CMS - ASTALAVISTA IT AG
 * @author Astalavista Development Team <thun@astalvista.ch>
 * @access public
 * @version 1.0.0
 * @package     contrexx
 * @subpackage  core_module_news
 */
class newsHeadlines{
    var $_pageContent;
    var $_objTemplate;
    var $arrSettings = array();

    function newsHeadlines($pageContent){
    	$this->getSettings();
	    $this->_pageContent = $pageContent;
	    $this->_objTemplate = &new HTML_Template_Sigma('.');
	}
	
    function getSettings(){
    	global $objDatabase;   	
        $objResult = $objDatabase->Execute("SELECT name, value FROM ".DBPREFIX."module_news_settings");        
        if ($objResult !== false) {
		    while (!$objResult->EOF) {
			    $this->arrSettings[$objResult->fields['name']] = $objResult->fields['value'];
			    $objResult->MoveNext();
		    }
        }
    }  	
	
    	
	function getHomeHeadlines() {
		global $_CONFIG, $_CORELANG, $objDatabase, $_LANGID;	
		
		$newsLimit = intval($this->arrSettings['news_headlines_limit']);
		if($newsLimit<1 OR $newsLimit>50){
			$newsLimit=10;
		}	
			   
		$this->_objTemplate->setTemplate($this->_pageContent,true,true);		
		$this->_objTemplate->setCurrentBlock('headlines_row');
		
		$objResult = $objDatabase->SelectLimit("SELECT id, 
									                   title,
									                   date,
									                   teaser_image_path,
									                   teaser_text,
									                   redirect
									              FROM ".DBPREFIX."module_news 
									             WHERE status = 1
											           AND teaser_only='0'
											           AND lang=".$_LANGID."
											           AND (startdate<=CURDATE() OR startdate='0000-00-00')
											           AND (enddate>=CURDATE() OR enddate='0000-00-00') 		
									          ORDER BY date DESC", $newsLimit);
		
		if ($objResult !== false && $objResult->RecordCount()>=0) {
			while (!$objResult->EOF) {
			    $this->_objTemplate->setVariable("HEADLINE_DATE", date(ASCMS_DATE_SHORT_FORMAT, $objResult->fields['date']));
				$this->_objTemplate->setVariable("HEADLINE_LINK", (empty($objResult->fields['redirect'])) ? "<a href=\"?section=news&amp;cmd=details&amp;newsid=".$objResult->fields['id']."\" title=\"".htmlspecialchars(stripslashes($objResult->fields['title']), ENT_QUOTES, CONTREXX_CHARSET)."\">".htmlspecialchars(stripslashes($objResult->fields['title']), ENT_QUOTES, CONTREXX_CHARSET)."</a>" : '<a href="'.$objResult->fields['redirect'].'" title="'.htmlspecialchars(stripslashes($objResult->fields['title']), ENT_QUOTES, CONTREXX_CHARSET).'">'.htmlspecialchars(stripslashes($objResult->fields['title']), ENT_QUOTES, CONTREXX_CHARSET).'</a>');	
				$this->_objTemplate->setVariable("HEADLINE_IMAGE_PATH", $objResult->fields['teaser_image_path']);
				$this->_objTemplate->setVariable("HEADLINE_TEXT", $objResult->fields['teaser_text']);
				$this->_objTemplate->parseCurrentBlock();
				$objResult->MoveNext();
			}
			$this->_objTemplate->setVariable("TXT_MORE_NEWS", $_CORELANG['TXT_MORE_NEWS']);
		} else {
		    $this->_objTemplate->hideBlock('headlines_row');
		}					
		return $this->_objTemplate->get();
	}	
}
?>
