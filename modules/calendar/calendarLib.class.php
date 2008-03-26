<?php
/**
 * Calendar
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author 		Comvation Development Team <info@comvation.com>
 * @version 	1.1.0
 * @package     contrexx
 * @subpackage  module_calendar".$this->mandateLink."
 * @todo        Edit PHP DocBlocks!
 */

/**
 * Includes
 */
require_once ASCMS_LIBRARY_PATH."/activecalendar/activecalendar.php";

/**
 * Calendar
 *
 * LibClass to manage cms calendar
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author Comvation Development Team <info@comvation.com>
 * @access public
 * @version 1.1.0
 * @package     contrexx
 * @subpackage  module_calendar".$this->mandateLink."
 */
if (!class_exists("calendarLibrary")) {


class calendarLibrary
{
	var $_filename = '';
    var $_objTpl;
    var $strErrMessage = '';
    var $strOkMessage = '';
    var $calDay;
    var $calMonth;
    var $calYear;
    var $calDate;
    var $calDate2;
    var $calDate3;
    var $calendarDay;

    var $calStartYear;
    var $calEndYear;
    var $paging;

    var $calendarMonth;

    var $url;
    var $monthnavur=null;

    var $showOnlyActive = true;

   	var $_cachedCatNames = array();

   	var $mandate;
   	var $mandateLink;

    /**
     * PHP 5 Constructor
     */
    function __construct($url)
    {
        $this->calendarLibrary($url);
    }


    /**
     * Constructor for php 4
     */
    function calendarLibrary($url)
    {
        global $_ARRAYLANG, $_CONFIG;

        $this->calStartYear = 2004;
        $this->calEndYear   = 2037;
        $this->paging       = intval($_CONFIG['corePagingLimit']);
        $this->mandate = CALENDAR_MANDATE;
        if ($this->mandate == 1) {
            $this->mandateLink = "";
        } else {
            $this->mandateLink = $this->mandate;
        }

        $this->_objTpl = &new HTML_Template_Sigma(ASCMS_MODULE_PATH.'/calendar'.$this->mandateLink.'/template');
        $this->_objTpl->setErrorHandling(PEAR_ERROR_DIE);
        $this->_objTpl->setGlobalVariable("CALENDAR_MANDATE", $this->mandateLink);

        $this->url = $url.$this->mandateLink;
    }



	/**
	 * check access
	 *
	 * @param string $id note id
	 * @return true/false
	 */
    function _checkAccess($id=null)
    {
    	global $objDatabase, $objAuth, $objPerm;


    	if(!empty($_COOKIE['PHPSESSID'])) {
	    	if (isset($id)) {

		    	//check access
				$query = "SELECT access
							FROM ".DBPREFIX."module_calendar".$this->mandateLink."
				  		   WHERE id = '".$id."'";

				$objResult = $objDatabase->SelectLimit($query, 1);

				if ($objResult->fields['access'] == 1) {
						if ($objAuth->checkAuth()) {
							if (!$objPerm->checkAccess(116, 'static')) {
								header("Location: ".CONTREXX_DIRECTORY_INDEX."?section=login&cmd=noaccess");
								exit;
							}
						}else {
							$link = base64_encode($_SERVER['PHP_SELF'].'?'.$_SERVER['QUERY_STRING']);
							header("Location: ".CONTREXX_DIRECTORY_INDEX."?section=login&redirect=".$link);
							exit;
						}
					}
	    	} else {
					if ($objAuth->checkAuth()) {
						if (!$objPerm->checkAccess(116, 'static')) {
							return false;
							exit;
						}
					} else {
						return false;
						exit;
					}
				}

			return true;
    	} else {
    		return false;
    	}
    }



	/**
	 * check what to export and call specific funciton
	 *
	 * @param string $what to export
	 * @param integer $id the ID of the event/category
	 */
    function _iCalExport($what, $id = 0){
    	switch($what){
    		case 'event':
    			$this->_iCalExportEvent($id);
    			break;
    		case 'category':
    			$this->_iCalExportCategory($id);
    			break;
    		case 'all':
    			$this->_iCalExportAll();
    			break;
    	}
    }

    /**
     * create iCal file and send it to the client
     *
     * @param array $arrEvents array of events to export
     */
    function _sendICal($arrEvents){
    	require_once(ASCMS_LIBRARY_PATH.'/iCalcreator/iCalcreator.class.php');

    	$c = new vcalendar();
		$c->setMethod('PUBLISH');

		foreach ($arrEvents as $arrEvent) {
			$comment 		= $this->_filterHTML($arrEvent['comment']);
			$place 			= $this->_filterHTML($arrEvent['place']);
			$name 			= $this->_filterHTML($arrEvent['name']);
			$categoryName 	= $this->_filterHTML($this->_getCategoryNameByEventId($arrEvent['id']));
			$infoURL		= $this->_filterHTML($arrEvent['info']);

			$ev = new vevent();
	    	$ev->setDtstart(array('timestamp' => $arrEvent['startdate']));
	    	$ev->setDtend(array('timestamp' => $arrEvent['enddate']));
	    	$ev->setAction('DISPLAY');
	    	if(!empty($comment)){
		    	$ev->setComment($comment);
		    	$ev->setDescription($comment);
	    	}
	    	if(!empty($place)){
				$ev->setLocation($place);
	    	}
	    	if(!empty($arrEvent['priority'])){
				$ev->setPriority($arrEvent['priority']);
	    	}
			if(!empty($name)){
				$ev->setSummary($name);
			}
			if(!empty($categoryName)){
				$ev->setCategories($categoryName);
			}
			if(!empty($infoURL)){
				$ev->setUrl($infoURL);
			}
			$ev->setClass('PUBLIC');

			$c->addComponent($ev);
		}

		if(trim($this->_filename) == ''){
			$this->_filename = 'event';
		}

		header('Content-Type: text/calendar; charset='.CONTREXX_CHARSET);
		header('Content-Disposition: attachment; filename="'.$this->_filename.'.ics"');
		die($c->createCalendar());

    }


	/**
	 * export calendar event as iCal-file
	 *
	 * @param integer $id ID of the event
	 * @return bool false on error
	 */
    function _iCalExportEvent($id){
		require_once(ASCMS_LIBRARY_PATH.'/iCalcreator/iCalcreator.class.php');
		//wrap this in an array, since it is only one event (see _sendICal() to understand)
		$this->_sendICal(array($this->getEventByID($id)));
    }

	/**
	 * export calendar category with all events as iCal-file
	 *
	 * @param integer $id ID of the category
	 * @return void
	 */
    function _iCalExportCategory($catID){
    	if($catID == 0){
    		$this->_iCalExportAll();
    	}
		require_once(ASCMS_LIBRARY_PATH.'/iCalcreator/iCalcreator.class.php');
		$this->_filename = html_entity_decode($objRS->fields['name'], ENT_QUOTES, CONTREXX_CHARSET);
		$this->_sendICal($this->getEventsFromCategoryByID($catID));
    }


    /**
     * export all calendar events as iCal-file
     *
     * @return void
     */
    function _iCalExportAll(){
    	$this->_filename = 'all';
		$this->_sendICal($this->_getAllEvents());
    }


    /**
     * return all Events in an array
     *
     * @return array $arrEvents all Events
     */
    function _getAllEvents(){
    	global $objDatabase, $_ARRAYLANG;

		$query = "	SELECT 	`id`, `catid`, 	`startdate`, 	`enddate`,	`priority`,
    						`name`, 	`comment`,		`placeName`,	`link`
    				FROM `".DBPREFIX."module_calendar".$this->mandateLink."`";

		if(($objRS = $objDatabase->Execute($query)) !== false){
    		if($objRS->RecordCount() < 1){
    			return false;
    		}
			$arrEvents = array();
			while(!$objRS->EOF){
	    		// cache the categoryNames to reduce amount of DB queries
	    		if(!isset($this->_cachedCatNames[$objRS->fields['catid']])){
	    			$categoryName = $this->_cachedCatNames[$objRS->fields['catid']] = $this->_getCategoryNameByEventId($objRS->fields['id']);
	    		}else{
	    			$categoryName = $this->_cachedCatNames[$objRS->fields['catid']];
	    		}

				$arrEvents[] = array(
					'id'			=> $objRS->fields['id'],
					'catid' 		=> $objRS->fields['catid'],
					'startdate'		=> $objRS->fields['startdate'],
					'enddate' 		=> $objRS->fields['enddate'],
					'priority' 		=> $objRS->fields['priority'],
					'name' 			=> html_entity_decode($objRS->fields['name'], ENT_QUOTES, CONTREXX_CHARSET),
					'categoryname'	=> html_entity_decode($categoryName, ENT_QUOTES, CONTREXX_CHARSET),
					'comment' 		=> html_entity_decode($objRS->fields['comment'], ENT_QUOTES, CONTREXX_CHARSET),
					'place' 		=> html_entity_decode($objRS->fields['placeName'], ENT_QUOTES, CONTREXX_CHARSET),
					'info' 			=> html_entity_decode($objRS->fields['link'], ENT_QUOTES, CONTREXX_CHARSET),
				);
				$objRS->MoveNext();
			}
			return $arrEvents;
    	}else{
    		$this->strErrMessage = $_ARRAYLANG['TXT_DATABASE_READ_ERROR'];
    	}
    }

    /**
     * return event(s) by ID
     *
     * @param integer $eventID
     * return array $arrEvents;
     */
    function getEventByID($eventID){
		global $objDatabase, $_ARRAYLANG;

		$query = "	SELECT 	`catid`, 	`startdate`, 	`enddate`,	`priority`,
    						`name`, 	`comment`,		`placeName`,	`link`
    				FROM `".DBPREFIX."module_calendar".$this->mandateLink."`
    				WHERE  `id` = ".$eventID."";
    	if(($objRS = $objDatabase->SelectLimit($query, 1)) !== false){
    		if($objRS->RecordCount() < 1){
    			return false;
    		}

    		// cache the categoryNames to reduce amount of DB queries
    		if(!isset($this->_cachedCatNames[$objRS->fields['catid']])){
    			$categoryName = $this->_cachedCatNames[$objRS->fields['catid']] = $this->_getCategoryNameByEventId($eventID);
    		}else{
    			$categoryName = $this->_cachedCatNames[$objRS->fields['catid']];
    		}
			return array(
				'id'			=> $objRS->fields['id'],
				'catid' 		=> $objRS->fields['catid'],
				'startdate'		=> $objRS->fields['startdate'],
				'enddate' 		=> $objRS->fields['enddate'],
				'priority' 		=> $objRS->fields['priority'],
				'name' 			=> html_entity_decode($objRS->fields['name'], ENT_QUOTES, CONTREXX_CHARSET),
				'categoryname'	=> html_entity_decode($categoryName, ENT_QUOTES, CONTREXX_CHARSET),
				'comment' 		=> html_entity_decode($objRS->fields['comment'], ENT_QUOTES, CONTREXX_CHARSET),
				'place' 		=> html_entity_decode($objRS->fields['placeName'], ENT_QUOTES, CONTREXX_CHARSET),
				'info' 			=> html_entity_decode($objRS->fields['link'], ENT_QUOTES, CONTREXX_CHARSET),
			);
    	}else{
    		$this->strErrMessage = $_ARRAYLANG['TXT_DATABASE_READ_ERROR'];
    	}
    }


	/**
	 * return events from a category
	 *
	 * @param integer $categoryID
	 * @return array $arrEvents
	 */
    function getEventsFromCategoryByID($categoryID){
    	global $objDatabase, $_ARRAYLANG;

    	$query = "	SELECT `id` FROM `".DBPREFIX."module_calendar".$this->mandateLink."`
    				WHERE `catid` = ".$categoryID."";
    	if(($objRS = $objDatabase->Execute($query)) !== false){
    		if($objRS->RecordCount() < 1){
    			return false;
    		}
    		$this->_filename = $this->getCategoryNameFromCategoryId($categoryID);
    		$arrEvents = array();
			while(!$objRS->EOF){
				array_push($arrEvents, $this->getEventByID($objRS->fields['id']));
				$objRS->MoveNext();
			}

			return $arrEvents;
    	}else{
    		$this->strErrMessage = $_ARRAYLANG['TXT_DATABASE_READ_ERROR'];
    	}
    }


    /**
     * return the categoryname for the specified category ID
     *
     * @param integer $catId
     */
    function getCategoryNameFromCategoryId($catId){
		global $objDatabase, $_ARRAYLANG;

    	$query = "	SELECT `name` FROM `".DBPREFIX."module_calendar".$this->mandateLink."_categories`
    				WHERE `id` = ".$catId."";
    	if(($objRS = $objDatabase->SelectLimit($query, 1)) !== false){
    		if($objRS->RecordCount() < 1){
    			return false;
    		}
			return $objRS->fields['name'];
    	}else{
    		$this->strErrMessage = $_ARRAYLANG['TXT_DATABASE_READ_ERROR'];
    	}
    }


	/**
	 * return catgeory name by eventID
	 *
	 * @param integer $eventID
	 * @return string $categoryName, bool false on failure
	 *
	 */
    function _getCategoryNameByEventId($eventID){
    	global $objDatabase, $_LANGID;

    	$query = "	SELECT `c`.`name` FROM `".DBPREFIX."module_calendar".$this->mandateLink."` AS `e`
    				INNER JOIN `".DBPREFIX."module_calendar".$this->mandateLink."_categories` AS `c`
    				ON (`e`.`catid` = `c`.`id`)
    				WHERE `lang` = ".$_LANGID."
    				AND `e`.`id` = ".$eventID."";
    	if( ($objRS = $objDatabase->SelectLimit($query, 1)) !== false){
    		if($objRS->RecordCount() < 1){
    			return false;
    		}
    		return $objRS->fields['name'];
    	}else{
    		return false;
    	}


    }


    /**
     * remove HTML tags from a string
     *
     * @param string $str to strip HTML tags from
     * @return string $str_without_html_tags
     */
    function _filterHTML($str){
    	$str = preg_replace("#<([^>]+)>#s", '', $str);
		return preg_replace("#[\s\t\r\n]{2,}+#s", "\n", $str);
    }

    // write month names
    function monthName($month)
    {
        global $_ARRAYLANG;

        $months = explode(',', $_ARRAYLANG['TXT_MONTH_ARRAY']);
        $name = $months[$month - 1];
        return $name;
    }

    // get day note
    function getDayNote($id, $showboxes=true)
    {
        global $objDatabase, $_ARRAYLANG, $_LANGID;

        $query = "SELECT id,
                           catid,
                           startdate,
                           enddate,
                           priority,
                           name,
                           comment,
                           placeName,
                           info
                      FROM ".DBPREFIX."module_calendar".$this->mandateLink."
                     WHERE id = '".intval($id)."'";

        $objResult = $objDatabase->Execute($query);

        $title        = date(ASCMS_DATE_SHORT_FORMAT, $objResult->fields['startdate']);
        $start        = date(ASCMS_DATE_FORMAT, $objResult->fields['startdate']);
        $end        = date(ASCMS_DATE_FORMAT, $objResult->fields['enddate']);
        $comment       = stripslashes($objResult->fields['comment']);

        $startdate = date("d.m.Y", $objResult->fields['startdate']);
        $enddate = date("d.m.Y", $objResult->fields['enddate']);
        $starttime = date("H:i", $objResult->fields['startdate']);
        $endtime = date("H:i", $objResult->fields['enddate']);

        if ($showboxes) {
            $boxes = $this->getBoxes(3, date("Y", $objResult->fields['startdate']),
                date("m", $objResult->fields['startdate']),
                date("d", $objResult->fields['startdate']));
        }

        $this->_objTpl->setVariable("CALENDAR", $boxes);

        if( $objResult->fields['priority'] == 1){
            $priority_gif = 'priority2h';
        }
        elseif ($objResult->fields['priority'] == 2) {
            $priority_gif = 'priorityh';
        }
        elseif ($objResult->fields['priority'] == 3) {
            $priority_gif = 'priorityno';
        }
        elseif ($objResult->fields['priority'] == 4) {
            $priority_gif = 'priorityl';
        }
        elseif ($objResult->fields['priority'] == 5) {
            $priority_gif = 'priority2l';
        }

        $query = "SELECT name
                       FROM ".DBPREFIX."module_calendar".$this->mandateLink."_categories
                      WHERE id = '".$objResult->fields['catid']."'";
        $objResult2 = $objDatabase->SelectLimit($query);

        // parse to template
        $this->_objTpl->setVariable(array(
            'TXT_CALENDAR_CAT'         	 	=> $_ARRAYLANG['TXT_CALENDAR_CAT'],
            'TXT_CALENDAR_DATE'            	=> $_ARRAYLANG['TXT_CALENDAR_DATE'],
            'TXT_CALENDAR_NAME'            	=> $_ARRAYLANG['TXT_CALENDAR_NAME'],
            'TXT_CALENDAR_PLACE'        	=> $_ARRAYLANG['TXT_CALENDAR_PLACE'],
            'TXT_CALENDAR_PRIORITY'        	=> $_ARRAYLANG['TXT_CALENDAR_PRIORITY'],
            'TXT_CALENDAR_START'        	=> $_ARRAYLANG['TXT_CALENDAR_START'],
            'TXT_CALENDAR_END'            	=> $_ARRAYLANG['TXT_CALENDAR_END'],
            'TXT_CALENDAR_COMMENT'        	=> $_ARRAYLANG['TXT_CALENDAR_COMMENT'],
            'TXT_CALENDAR_BACK'         	=> $_ARRAYLANG['TXT_CALENDAR_BACK'],
            'CALENDAR_TITLE'            	=> $title,
            'CALENDAR_NAME'             	=> stripslashes(htmlentities($objResult->fields['name'], ENT_QUOTES, CONTREXX_CHARSET)),
            'CALENDAR_PLACE'            	=> stripslashes(htmlentities($objResult->fields['place'], ENT_QUOTES, CONTREXX_CHARSET)),
            'CALENDAR_PRIORITY_GIF'     	=> $priority_gif,
            'CALENDAR_PRIORITY'           	=> $objResult->fields['priority'],
            'CALENDAR_START'            	=> $start,
            'CALENDAR_END'                	=> $end,
            'CALENDAR_STARTTIME'        	=> $starttime,
            'CALENDAR_ENDTIME'            	=> $endtime,
            'CALENDAR_STARTDATE'        	=> $startdate,
            'CALENDAR_ENDDATE'           	=> $enddate,
            'CALENDAR_COMMENT'           	=> $comment,
            'CALENDAR_ID'                	=> $id,
            'CALENDAR_CAT'              	=> stripslashes($objResult2->fields['name']),
            'CALENDAR_ICAL_EXPORT'      	=> '<a href="'.CONTREXX_DIRECTORY_INDEX.'?section=calendar'.$this->mandateLink.'&amp;cmd=event&amp;export=iCal&amp;id='.$id.'" title="'.$_ARRAYLANG['TXT_CALENDAR_EXPORT_ICAL_EVENT'].'">
            									'.$_ARRAYLANG['TXT_CALENDAR_EXPORT_ICAL_EVENT'].' <img style="padding-top: -1px;" border="0" src="images/modules/calendar/ical_export.gif" alt="'.$_ARRAYLANG['TXT_CALENDAR_EXPORT_ICAL_EVENT'].'" title="'.$_ARRAYLANG['TXT_CALENDAR_EXPORT_ICAL_EVENT'].'" />
            								</a>',
            'CALENDAR_ICAL_EXPORT_IMG'      => '<a href="'.CONTREXX_DIRECTORY_INDEX.'?section=calendar'.$this->mandateLink.'&amp;cmd=event&amp;export=iCal&amp;id='.$id.'" title="'.$_ARRAYLANG['TXT_CALENDAR_EXPORT_ICAL_EVENT'].'">
            									<img style="padding-bottom: -5px;" border="0" src="images/modules/calendar/ical_export.gif" alt="'.$_ARRAYLANG['TXT_CALENDAR_EXPORT_ICAL_EVENT'].'" title="'.$_ARRAYLANG['TXT_CALENDAR_EXPORT_ICAL_EVENT'].'" />
            								</a>',
        ));

        if (!empty($objResult->fields['info'])) {
            $info = $info_href = $objResult->fields['info'];
            if (strlen($info) > 50) {
                $info = substr($info, 0, 47);
                $info .= "...";
            }
            $this->_objTpl->setVariable(array(
                'TXT_CALENDAR_INFO'         => $_ARRAYLANG['TXT_CALENDAR_INFO'],
                'CALENDAR_INFO'             => $info,
                'CALENDAR_INFO_HREF'        => $info_href
            ));

        } else {
            $this->_objTpl->hideBlock("infolink");
        }
    }

	  /**
	   *  function dateNumber
	   *
	   *  convert date-number from one-digit to two-digit
	   */
	  function dateNumber($number)
	  {
	      $number = intval($number);
	      if(strlen($number)==1)
	      {
	          $number = '0'.$number;
	      }
	      return $number;
	  }


    /**
     * Get Boxes
     *
     * Returns 3 calendar Boxes
     */
    function getBoxes($howmany, $year, $month=0, $day=0, $catid=NULL)
    {
        global $objDatabase, $_ARRAYLANG, $objPerm, $objInit;

        $url = htmlentities($this->url, ENT_QUOTES, CONTREXX_CHARSET);

        if (empty($catid)) {
            if (empty($_GET['catid'])) {
                $catid = 0;
            } else {
                $catid = $_GET['catid'];
            }
        }

        $url.="&amp;catid=$catid";

        $month = intval($month);
        $year = intval($year);
        $day = intval($day);
        $firstblock = true;

        $monthnames = explode(",", $_ARRAYLANG['TXT_CALENDAR_MONTH_ARRAY']);
        $daynames = explode(',', $_ARRAYLANG['TXT_CALENDAR_DAY_ARRAY']);

        for ($i=0; $i<$howmany; $i++) {
            $cal = new activeCalendar($year, $month, $day);
            $cal->setMonthNames($monthnames);
            $cal->setDayNames($daynames);
            if ($firstblock) {
                $cal->enableMonthNav($url);
            } else {
                // This is necessary for the modification of the linkname
                // The modification makes a link on the monthname
                $cal->urlNav=$url;
            }

            // for seperate variable for the month links
            if (!empty($this->monthnavurl)) {
                $cal->urlMonthNav = htmlentities($this->monthnavurl, ENT_QUOTES, CONTREXX_CHARSET);
            }

            // get events
            if (empty($catid)) {
                $query = "SELECT * FROM ".DBPREFIX."module_calendar".$this->mandateLink;
            } else {
                $query = "SELECT * FROM ".DBPREFIX."module_calendar".$this->mandateLink."
                          WHERE catid=$catid";
            }


            $objResult = $objDatabase->Execute($query);

            while (!$objResult->EOF) {
            	if ($objResult->fields['access'] && $objInit->mode == 'frontend' && (!is_object($objPerm) || !$objPerm->checkAccess(116, 'static', true))) {
            		$objResult->MoveNext();
            		continue;
            	}
                if (($objResult->fields['active'] == 1 && $this->showOnlyActive) || !$this->showOnlyActive) {
                    $startdate = $objResult->fields['startdate'];
                    $enddate = $objResult->fields['enddate'];

                    $eventYear     = date("Y", $startdate);
                    $eventMonth = date("m", $startdate);
                    $eventDay    = date("d", $startdate);

                    $eventEndDay = date("d", $enddate);
                    $eventEndMonth = date("m", $enddate);

                    // do only something when the event is in the current month
                    if ($eventMonth <= $month && $eventEndMonth >= $month) {
                        // if the event is longer than one day but every day is in the same month
                        if ($eventEndDay > $eventDay && $eventMonth == $eventEndMonth) {
                            $curday = $eventDay;
                            while ($curday <= $eventEndDay) {
                                $eventurl = $url."&amp;yearID=$eventYear&amp;monthID=$month&amp;dayID=$curday";
                                $cal->setEvent("$eventYear", "$eventMonth", "$curday", false, $eventurl);

                                $curday++;
                            }
                        } elseif ($eventEndMonth > $eventMonth) {
                            if ($eventMonth == $month) {
                                // Show the part of the event in the starting month
                                $curday = $eventDay;
                                while ($curday <= 31) {
                                    $eventurl = $url."&amp;yearID=$eventYear&amp;monthID=$month&amp;dayID=$curday";
                                    $cal->setEvent("$eventYear", "$eventMonth", "$curday", false, $eventurl);

                                    $curday++;
                                }
                            } elseif ($eventEndMonth == $month) {
                                // show the part of the event in the ending month
                                $curday = $eventEndDay;
                                while ($curday > 0) {
                                    $eventurl = $url."&amp;yearID=$eventYear&amp;monthID=$month&amp;dayID=$curday";
                                    $cal->setEvent("$eventYear", "$eventEndMonth", "$curday", false, $eventurl);

                                    $curday--;
                                }
                            } elseif ($eventMonth < $month && $eventEndMonth > $month) {
                            	foreach (range(0,31,1) as $curday) {
                            		$eventurl = $url."&amp;yearID=$eventYear&amp;monthID=$month&amp;dayID=$curday";
                                    $cal->setEvent("$eventYear", "$month", "$curday", false, $eventurl);
                            	}
                            }
                        } else {
                            $eventurl = $url."&amp;yearID=$eventYear&amp;monthID=$month&amp;dayID=$eventDay";
                            $cal->setEvent("$eventYear", "$eventMonth", "$eventDay", false, $eventurl);
                        }
                    }
                }
                $objResult->MoveNext();
            }
            $retval .= $cal->showMonth(false, true);

            if ($month == 12) {
                $year++;
                $month = 1;
            } else {
                $month++;
            }
            $day = 0;

            $firstblock = false;
        }

        return $retval;
    }


    /**
     * Category List
     *
     * Returns multiple <option> tags for the
     * list of categories
     */
    function category_list($selected_var, $name="categories") {
		global $objDatabase, $_ARRAYLANG, $_LANGID;

		$calendar_categories = "<form action=\"#\" id=\"selectcat\">
		    <select name=\"$name\" onchange=\"changecat()\"  id=\"calendarSelectcat\">
		        <option value=\"0\">".$_ARRAYLANG['TXT_CALENDAR_ALL_CAT']."</option>";

		// makes the category list
		$query = "SELECT id,name,lang FROM ".DBPREFIX."module_calendar".$this->mandateLink."_categories
			  WHERE status = '1'".
				  (!empty($_LANGID) && intval($_LANGID > 0) ? " AND lang = ".$_LANGID : '')."
			  ORDER BY pos";
		$objResult = $objDatabase->Execute($query);

		while (!$objResult->EOF) {
		    if ($objResult->fields['id'] == $selected_var) {
		        $selected = " selected=\"selected\"";
		    } else {
		        $selected = "";
		    }


		    $calendar_categories .= "<option value=\"".$objResult->fields['id']."\"$selected>".$objResult->fields['name']."</option>";
		    $objResult->MoveNext();
		}

		$calendar_categories .= "
		        </select>
		    </form>";

		return $calendar_categories;
    }


    /**
	 * Get Note Data
	 *
	 * gets all data from note
	 */
	function getNoteData($id, $type, $numBoxes)
	{
		global $objDatabase, $_ARRAYLANG, $_LANGID;

		$query = "SELECT 	id,
							active,
							catid,
							startdate,
							enddate,
							priority,
							access,
							name,
							comment,
							placeName,
							link,
							pic,
							attachment,
							placeStreet,
							placeZip,
							placeCity,
							placeLink,
							placeMap,
							organizerName,
							organizerStreet,
							organizerZip,
							organizerPlace,
							organizerMail,
							organizerLink,
							registration,
							groups,
							all_groups,
							public,
							mailContent,
							mailTitle,
							num,
							notification,
							notification_address
		    		FROM 	".DBPREFIX."module_calendar".$this->mandateLink."
		    	   WHERE 	id = '".$id."'";

		$objResultNote 	= $objDatabase->SelectLimit($query, 1);

		//date and time
		$startdate  = $objResultNote->fields['startdate'];
		$enddate	= $objResultNote->fields['enddate'];

		$day			= date("d", $startdate);
		$end_day		= date("d", $enddate);
		$month			= date("m", $startdate);
		$end_month		= date("m", $enddate);
		$year			= date("Y", $startdate);
		$end_year		= date("Y", $enddate);
		$hour			= date("H", $startdate);
		$minutes		= date("i", $startdate);
		$end_hour		= date("H", $enddate);
		$end_minutes	= date("i", $enddate);


        $active = "";
		//comment
		if($type == "show") {
			$ed = $objResultNote->fields['comment'];

			//calender boxes
			$calendarbox = $this->getBoxes($numBoxes, $year, $month, $day);

			//priority
			switch ($objResultNote->fields['priority']){
				case 1:
					$priority	 	= $_ARRAYLANG['TXT_CALENDAR_PRIORITY_VERY_HEIGHT'];
					$priorityImg	= "<img src='images/modules/calendar/very_height.gif' border='0' title='".$_ARRAYLANG['TXT_CALENDAR_PRIORITY_VERY_HEIGHT']."' alt='".$_ARRAYLANG['TXT_CALENDAR_PRIORITY_VERY_HEIGHT']."' />";
					break;
				case 2:
					$priority	 	= $_ARRAYLANG['TXT_CALENDAR_PRIORITY_HEIGHT'];
					$priorityImg	= "<img src='images/modules/calendar/height.gif' border='0' title='".$_ARRAYLANG['TXT_CALENDAR_PRIORITY_HEIGHT']."' alt='".$_ARRAYLANG['TXT_CALENDAR_PRIORITY_HEIGHT']."' />";
					break;
				case 3:
					$priority	 	= $_ARRAYLANG['TXT_CALENDAR_PRIORITY_NORMAL'];
					$priorityImg	= "&nbsp;";
					break;
				case 4:
					$priority	 	= $_ARRAYLANG['TXT_CALENDAR_PRIORITY_LOW'];
					$priorityImg	= "<img src='images/modules/calendar/low.gif' border='0' title='".$_ARRAYLANG['TXT_CALENDAR_PRIORITY_LOW']."' alt='".$_ARRAYLANG['TXT_CALENDAR_PRIORITY_LOW']."' />";
					break;
				case 5:
					$priority	 	= $_ARRAYLANG['TXT_CALENDAR_PRIORITY_VERY_LOW'];
					$priorityImg	= "<img src='images/modules/calendar/very_low.gif' border='0' title='".$_ARRAYLANG['TXT_CALENDAR_PRIORITY_VERY_LOW']."' alt='".$_ARRAYLANG['TXT_CALENDAR_PRIORITY_VERY_LOW']."' />";
					break;
			}

			//access
			$access = $objResultNote->fields['access'] == 1 ? $_ARRAYLANG['TXT_CALENDAR_ACCESS_COMMUNITY'] : $_ARRAYLANG['TXT_CALENDAR_ACCESS_PUBLIC'];

			//categorie
			$query = "SELECT 	id,
			                	name
			               FROM ".DBPREFIX."module_calendar".$this->mandateLink."_categories
			            WHERE id='".$objResultNote->fields['catid']."'";

			$objResultCat = $objDatabase->Execute($query);

			if ($objResultCat !== false) {
				while(!$objResultCat->EOF) {
					$catName = $objResultCat->fields['name'];
					$objResultCat->MoveNext();
				}
			}

			//place map
	        $arrInfo 	= getimagesize(ASCMS_PATH.$objResultNote->fields['placeMap']); //ermittelt die Gr��e des Bildes
	        $picWidth	= $arrInfo[0]+20;
	        $picHeight	= $arrInfo[1]+20;

	        $attachNamePos  = strrpos($objResultNote->fields['attachment'], '/');
	        $attachNameLenght = strlen($objResultNote->fields['attachment']);
	        $attachName		= substr($objResultNote->fields['attachment'], $attachNamePos+1, $attachNameLenght);

		} else {
			$ed = get_wysiwyg_editor('inputComment',$objResultNote->fields['comment']);

			//calender boxes
			$calendarbox = $this->getBoxes($numBoxes, $year, $month, $day);

			//priority
			switch ($objResultNote->fields['priority']){
				case 1:
					$veryHeight	 	= 'selected="selected"';
					$height 		= '';
					$normal 		= '';
					$low 			= '';
					$veryLow 		= '';
					break;
				case 2:
					$veryHeight	 	= '';
					$height 		= 'selected="selected"';
					$normal 		= '';
					$low 			= '';
					$veryLow 		= '';
					break;
				case 3:
					$veryHeight	 	= '';
					$height 		= '';
					$normal 		= 'selected="selected"';
					$low 			= '';
					$veryLow 		= '';
					break;
				case 4:
					$veryHeight	 	= '';
					$height 		= '';
					$normal 		= '';
					$low 			= 'selected="selected"';
					$veryLow 		= '';
					break;
				case 5:
					$veryHeight	 	= '';
					$height 		= '';
					$normal 		= '';
					$low 			= '';
					$veryLow 		= 'selected="selected"';
					break;
			}

			//actiove
			$active = $objResultNote->fields['active'] == 1 ? 'checked="checked"' : "";

			//time
			$this->selectHour($hour, "hour", "CALENDAR_HOUR_SELECT", "CALENDAR_HOUR");
			$this->selectMinutes($minutes, "minutes", "CALENDAR_MINUTES_SELECT", "CALENDAR_MINUTES");
		    $this->selectHour($end_hour, "endhour", "CALENDAR_END_HOUR_SELECT", "CALENDAR_END_HOUR");
			$this->selectMinutes($end_minutes, "endminutes", "CALENDAR_END_MINUTES_SELECT", "CALENDAR_END_MINUTES");

			//categorie
			$query = "SELECT 	id,
			                	name,
			                    lang
			               FROM ".DBPREFIX."module_calendar".$this->mandateLink."_categories
			            ORDER BY pos";

			$objResultCat = $objDatabase->Execute($query);

			if ($objResultCat !== false) {
				while(!$objResultCat->EOF) {
					$query = "SELECT lang
					            FROM ".DBPREFIX."languages
					           WHERE id = '".$objResultCat->fields['lang']."'";

					$objResultLang = $objDatabase->SelectLimit($query, 1);

					$selected = '';
					if ($objResultCat->fields['id'] == $objResultNote->fields['catid']) {
						$selected = ' selected="selected"';
					}

					$this->_objTpl->setVariable(array(
					    'CALENDAR_CAT_ID'       => $objResultCat->fields['id'],
					    'CALENDAR_CAT_SELECTED' => $selected,
				    	'CALENDAR_CAT_LANG'     => $objResultLang->fields['lang'],
					    'CALENDAR_CAT_NAME'     => $objResultCat->fields['name']
					));
					$this->_objTpl->parse("calendar_cat");

					$objResultCat->MoveNext();
				}
			}
		}


		//access
		switch ($objResultNote->fields['access']){
			case 0:
				$public	 		= 'selected="selected"';
				$community 		= '';
				$return 		= false;
				break;
			case 1:
				$community	 	= 'selected="selected"';
				$public 		= '';
				$return 		= true;
				break;
		}

		//registrations
		switch ($objResultNote->fields['registration']){
			case 0:
				$registrationsActivated	 = '';
				break;
			case 1:
				$registrationsActivated	 = 'checked="checked"';
				break;
		}

		$registrationsAddresserAll				= '';
	    $registrationsAddresserAllUser			= '';
		$registrationsAddresserSelectGroup		= '';
		//addresser
		if ($objResultNote->fields['public'] == 1) {
			$registrationsAddresserAll				= 'selected="selected"';
			$registrationsAddresserAllUser			= '';
			$registrationsAddresserSelectGroup		= '';
		}

		if ($objResultNote->fields['all_groups'] == 1) {
			$registrationsAddresserAll				= '';
			$registrationsAddresserAllUser			= 'selected="selected"';
			$registrationsAddresserSelectGroup		= '';
		}

		if ($objResultNote->fields['groups'] != "") {
			$registrationsAddresserAll				= '';
			$registrationsAddresserAllUser			= '';
			$registrationsAddresserSelectGroup		= 'selected="selected"';
		}

		switch ($objResultNote->fields['notification']){
			case 0:
				$notification	 = '';
				break;
			case 1:
				$notification	 = 'checked="checked"';
				break;
		}

		//count reg
		$reg_signoff = $this->_countRegistrations($objResultNote->fields['id']);

		// parse to template
		$this->_objTpl->setVariable(array(
			'CALENDAR_ID' 					=> $objResultNote->fields['id'],
			'CALENDAR_TITLE' 				=> htmlentities($objResultNote->fields['name'], ENT_QUOTES, CONTREXX_CHARSET),
			'CALENDAR_START'		 		=> date("Y-m-d", $startdate),
			'CALENDAR_END'			 		=> date("Y-m-d", $enddate),
			'CALENDAR_START_SHOW'		 	=> date("d.m.Y", $startdate),
			'CALENDAR_END_SHOW'			 	=> date("d.m.Y", $enddate),
			'CALENDAR_START_TIME'		 	=> date("H:i", $startdate),
			'CALENDAR_END_TIME'			 	=> date("H:i", $enddate),
			'CALENDAR_LINK'			 		=> $objResultNote->fields['link'] != '' ? "<a href='".$objResultNote->fields['link']."' target='_blank' >".$objResultNote->fields['link']."</a>" : "",
			'CALENDAR_LINK_SOURCE'			=> $objResultNote->fields['link'],
			'CALENDAR_PIC_THUMBNAIL' 		=> $objResultNote->fields['pic'] != '' ? "<img src='".$objResultNote->fields['pic'].".thumb' border='0' alt='".$objResultNote->fields['name']."' />" : "",
			'CALENDAR_PIC_SOURCE' 			=> $objResultNote->fields['pic'],
			'CALENDAR_PIC' 					=> $objResultNote->fields['pic'] != '' ? "<img src='".$objResultNote->fields['pic']."' border='0' alt='".$objResultNote->fields['name']."' />" : "",
			'CALENDAR_SOURCE_ATTACHMENT' 	=> $objResultNote->fields['attachment'],
			'CALENDAR_ATTACHMENT'			=> $objResultNote->fields['attachment'] != '' ? "<a href='".$objResultNote->fields['attachment']."' target='_blank' >".$attachName."</a>" : "",
			'CALENDAR_DESCRIPTION' 			=> $ed,
			'CALENDAR_ACTIVE' 				=> $active,

			'CALENDAR_PLACE' 				=> htmlentities($objResultNote->fields['placeName'], ENT_QUOTES, CONTREXX_CHARSET),
			'CALENDAR_PLACE_STREET_NR' 		=> htmlentities($objResultNote->fields['placeStreet'], ENT_QUOTES, CONTREXX_CHARSET),
			'CALENDAR_PLACE_ZIP' 			=> htmlentities($objResultNote->fields['placeZip'], ENT_QUOTES, CONTREXX_CHARSET),
			'CALENDAR_PLACE_CITY' 			=> htmlentities($objResultNote->fields['placeCity'], ENT_QUOTES, CONTREXX_CHARSET),
			'CALENDAR_PLACE_LINK' 			=> $objResultNote->fields['placeLink'] != '' ? "<a href='".$objResultNote->fields['placeLink']."' target='_blank' >".$objResultNote->fields['placeLink']."</a>" : "",
			'CALENDAR_PLACE_LINK_SOURCE' 	=> htmlentities($objResultNote->fields['placeLink'], ENT_QUOTES, CONTREXX_CHARSET),
			'CALENDAR_PLACE_MAP_LINK'		=> $objResultNote->fields['placeMap'] != '' ? '<a href="'.$objResultNote->fields['placeMap'].'" onClick="window.open(this.href,\'\',\'resizable=no,location=no,menubar=no,scrollbars=no,status=no,toolbar=no,fullscreen=no,dependent=no,width='.$picWidth.',height='.$picHeight.',status\'); return false">'.$_ARRAYLANG['TXT_CALENDAR_MAP'].'</a>' : "",
			'CALENDAR_PLACE_MAP_THUMBNAIL'	=> $objResultNote->fields['placeMap'] != '' ? '<a href="'.$objResultNote->fields['placeMap'].'" onClick="window.open(this.href,\'\',\'resizable=no,location=no,menubar=no,scrollbars=no,status=no,toolbar=no,fullscreen=no,dependent=no,width='.$picWidth.',height='.$picHeight.',status\'); return false"><img src="'.$objResultNote->fields['placeMap'].'.thumb" border="0" alt="'.$objResultNote->fields['placeName'].'" /></a>' : "",
			'CALENDAR_PLACE_MAP_SOURCE' 	=> $objResultNote->fields['placeMap'],

			'CALENDAR_ORGANIZER' 			=> htmlentities($objResultNote->fields['organizerName'], ENT_QUOTES, CONTREXX_CHARSET),
			'CALENDAR_ORGANIZER_STREET_NR' 	=> htmlentities($objResultNote->fields['organizerStreet'], ENT_QUOTES, CONTREXX_CHARSET),
			'CALENDAR_ORGANIZER_PLACE' 		=> htmlentities($objResultNote->fields['organizerPlace'], ENT_QUOTES, CONTREXX_CHARSET),
			'CALENDAR_ORGANIZER_ZIP' 		=> htmlentities($objResultNote->fields['organizerZip'], ENT_QUOTES, CONTREXX_CHARSET),
			'CALENDAR_ORGANIZER_LINK_SOURCE'=> htmlentities($objResultNote->fields['organizerLink'], ENT_QUOTES, CONTREXX_CHARSET),
			'CALENDAR_ORGANIZER_LINK'		=> $objResultNote->fields['organizerLink'] != '' ? "<a href='".$objResultNote->fields['organizerLink']."' target='_blank' >".$objResultNote->fields['organizerLink']."</a>" : "",
			'CALENDAR_ORGANIZER_MAIL_SOURCE'=> htmlentities($objResultNote->fields['organizerMail'], ENT_QUOTES, CONTREXX_CHARSET),
			'CALENDAR_ORGANIZER_MAIL'		=> $objResultNote->fields['organizerMail'] != '' ? "<a href='mailto:".$objResultNote->fields['organizerMail']."' >".$objResultNote->fields['organizerMail']."</a>" : "",


			'CALENDAR_ACCESS_PUBLIC' 		=> $public,
			'CALENDAR_ACCESS_COMMUNITY' 	=> $community,
			'CALENDAR_ACCESS' 				=> $access,
			'CALENDAR_PRIORITY' 			=> $priority,
			'CALENDAR_PRIORITY_IMG' 		=> $priorityImg,
			'CALENDAR_PRIORITY_VERY_HEIGHT' => (isset($veryHeight)) ? $veryHeight : "",
			'CALENDAR_PRIORITY_HEIGHT' 		=> (isset($height)) ? $height : "",
			'CALENDAR_PRIORITY_NORMAL' 		=> (isset($normal)) ? $normal : "",
			'CALENDAR_PRIORITY_LOW' 		=> (isset($low)) ? $low : "",
			'CALENDAR_PRIORITY_VERY_LOW' 	=> (isset($veryLow)) ? $veryLow : "",

			'CALENDAR_ACTUALL_BOXES' 		=> $calendarbox,
			'CALENDAR_CATEGORIE' 			=> $catName,

			'CALENDAR_EVENT_COUNT_REG'						=> $reg_signoff[0],
			'CALENDAR_EVENT_COUNT_SIGNOFF'					=> $reg_signoff[1],
			'CALENDAR_EVENT_COUNT_SUBSCRIBER'				=> $this->_countSubscriber($objResultNote->fields['id']),
			'CALENDAR_REGISTRATIONS_SUBSCRIBER'				=> $objResultNote->fields['num'],
			'CALENDAR_REGISTRATIONS_ACTIVATED'				=> $registrationsActivated,
			'CALENDAR_REGISTRATIONS_ADDRESSER_ALL'			=> $registrationsAddresserAll,
			'CALENDAR_REGISTRATIONS_ADDRESSER_ALL_USER'		=> $registrationsAddresserAllUser,
			'CALENDAR_REGISTRATIONS_ADDRESSER_SELECT_GROUP'	=> $registrationsAddresserSelectGroup,
			'CALENDAR_REGISTRATIONS_GROUPS_UNSELECTED' 		=> $this->_getUserGroups($objResultNote->fields['id'], 0),
			'CALENDAR_REGISTRATIONS_GROUPS_SELECTED' 		=> $this->_getUserGroups($objResultNote->fields['id'], 1),
			'CALENDAR_REGISTRATION_LINK'					=> '<a href="'.CONTREXX_DIRECTORY_INDEX.'?section=calendar'.$this->mandateLink.'&amp;cmd=sign&amp;id='.$objResultNote->fields['id'].'">'.$_ARRAYLANG['TXT_CALENDAR_REGISTRATION_LINK'].'</a>',

			'CALENDAR_MAIL_TITLE' 			=> htmlentities($objResultNote->fields['mailTitle'], ENT_QUOTES, CONTREXX_CHARSET),
			'CALENDAR_MAIL_CONTENT' 		=> htmlentities($objResultNote->fields['mailContent'], ENT_QUOTES, CONTREXX_CHARSET),

			'CALENDAR_NOTIFICATION_ACTIVATED' 	=> $notification,
			'CALENDAR_NOTIFICATION_ADDRESS' 	=> htmlentities($objResultNote->fields['notification_address'], ENT_QUOTES, CONTREXX_CHARSET),
		));

		if (($objResultNote->fields['registration'] != 1 || $objResultNote->fields['public'] != 1) ||  $objResultNote->fields['num'] < $this->_countSubscriber($objResultNote->fields['id']) && $objResultNote->fields['num'] != 0 && $objResultNote->fields['num'] != '') {
			if ($this->_objTpl->blockExists('calendarRegistration')){
				$this->_objTpl->hideBlock('calendarRegistration');
			}
		}

		return $return;
	}


	/**
	 * Get Reg Data
	 *
	 * gets all data from Reg
	 */
	function getRegData($regId)
	{
		global  $objDatabase, $_ARRAYLANG, $_CORELANG;

		//get reg data
		$queryReg = "SELECT id,note_id,time,host,ip_address,type
		               FROM ".DBPREFIX."module_calendar".$this->mandateLink."_registrations
		              WHERE id='".$regId."'";

		$objResultReg = $objDatabase->SelectLimit($queryReg, 1);

		//reg vars
		if ($objResultReg !== false) {

			if ($objResultReg->fields['type'] == 1) {
					$statusImg = "green";
					$statusTxt = $_ARRAYLANG['TXT_CALENDAR_REG_REGISTRATION'];
				} else {
					$statusImg = "red";
					$statusTxt = $_ARRAYLANG['TXT_CALENDAR_REG_SIGNOFF'];
				}

			$this->_objTpl->setVariable(array(
				'CALENDAR_REG_ID' 				=> $objResultReg->fields['id'],
				'CALENDAR_REG_NOTE_ID' 			=> $objResultReg->fields['note_id'],
				'CALENDAR_REG_TYPE_TXT' 		=> $statusTxt,
				'CALENDAR_REG_TYPE_IMG' 		=> $statusImg,
				'CALENDAR_REG_DATE' 			=> date("d.m.Y H:i:s", $objResultReg->fields['time']),
				'CALENDAR_REG_HOST'		 		=> htmlentities($objResultReg->fields['host'], ENT_QUOTES, CONTREXX_CHARSET),
				'CALENDAR_REG_IP'			 	=> htmlentities($objResultReg->fields['ip_address'], ENT_QUOTES, CONTREXX_CHARSET),
			));
		}

		//get fields
		$queryField = "SELECT id, note_id, name, type, required
		            	 FROM ".DBPREFIX."module_calendar".$this->mandateLink."_form_fields
		           		WHERE note_id='".$objResultReg->fields['note_id']."'";

		$objResultField = $objDatabase->Execute($queryField);

		if ($objResultField !== false) {
			while(!$objResultField->EOF) {

				//get field data
				$queryData = "SELECT reg_id, field_id, data
				                FROM ".DBPREFIX."module_calendar".$this->mandateLink."_form_data
				               WHERE field_id='".$objResultField->fields['id']."' AND reg_id='".$regId."'";

				$objResultData = $objDatabase->SelectLimit($queryData, 1);

				if ($objResultData !== false) {
					$arrFieldsData[$objResultField->fields['name']] = $objResultData->fields['data'];
				}

				$objResultField->moveNext();
			}
		}

		// spez vars
		$this->_objTpl->setVariable(array(
			'CALENDAR_REG_NAME' 			=> htmlentities($arrFieldsData[$_ARRAYLANG['TXT_CALENDAR_FIRSTNAME'] ]." ".$arrFieldsData[$_ARRAYLANG['TXT_CALENDAR_LASTNAME']], ENT_QUOTES, CONTREXX_CHARSET),
			'CALENDAR_REG_MAIL' 			=> htmlentities($arrFieldsData[$_ARRAYLANG['TXT_CALENDAR_MAIL']], ENT_QUOTES, CONTREXX_CHARSET),
		));

		//field vars (statisch)
		foreach($arrFieldsData as $fieldName => $fieldValue) {
			$this->_objTpl->setVariable(array(
				'CALENDAR_REG_FIELD_'.strtoupper($fieldName)			=> htmlentities($fieldValue, ENT_QUOTES, CONTREXX_CHARSET),
				'TXT_CALENDAR_REG_FIELD_'.strtoupper($fieldName)		=> htmlentities($fieldName, ENT_QUOTES, CONTREXX_CHARSET),
			));
		}

		return $arrFieldsData;
	}


	/**
	 * Count Registrations
	 *
	 * Count all registrations for this note
	 */
	function _countRegistrations($id)
	{
		global $objDatabase, $_LANGID;

		$i = 0;
		$x = 0;

		//registrations
		$query = "SELECT id, type
					FROM ".DBPREFIX."module_calendar".$this->mandateLink."_registrations
				   WHERE note_id = '".$id."'";

		$objResultCount = $objDatabase->Execute($query);

		if ($objResultCount !== false) {
			while(!$objResultCount->EOF) {
				if ($objResultCount->fields['type'] == 1) {
					$i++;
				} else {
					$x++;
				}
				$objResultCount->moveNext();
			}
		}

		$count[0] = $i;
		$count[1] = $x;

		return $count;
	}


	/**
	 * Count subscriber
	 *
	 * Count all subscriber for this note
	 */
	function _countSubscriber($id)
	{
		global $objDatabase, $_LANGID;

		//get field key
		$queryFieldId = "SELECT id
						   FROM ".DBPREFIX."module_calendar".$this->mandateLink."_form_fields
				          WHERE note_id = '".$id."' AND `key`='13'";

		$objResultFieldId 	= $objDatabase->SelectLimit($queryFieldId, 1);
		$fieldId			= $objResultFieldId->fields['id'];

		//get registrations
		$query = "SELECT id, type
					FROM ".DBPREFIX."module_calendar".$this->mandateLink."_registrations
				   WHERE note_id = '".$id."' AND type='1'";

		$objResultCount = $objDatabase->Execute($query);
		$countReg		= $objResultCount->RecordCount();
        $countEscort = 0;

		//add escort
		if ($objResultCount !== false) {
			while(!$objResultCount->EOF) {
				$queryEscort 	= "SELECT data
									 FROM ".DBPREFIX."module_calendar".$this->mandateLink."_form_data
						   			WHERE reg_id = '".$objResultCount->fields['id']."' AND field_id='".$fieldId."'";

				$objResultEscort 	= $objDatabase->SelectLimit($queryEscort, 1);

				$countEscort = $countEscort+$objResultEscort->fields['data'];

				$objResultCount->moveNext();
			}
		}

		$countAll = $countReg+$countEscort;

		return $countAll;
	}


	/**
	 * get user groups
	 *
	 * get user groups selected/unselected
	 */
	function _getUserGroups($noteId, $type)
	{
		global $objDatabase, $_LANGID;

		//get selected groups
		$queryGroups 		= "SELECT groups
							 	 FROM ".DBPREFIX."module_calendar".$this->mandateLink."
				   				WHERE id = '".$noteId."'";

		$objResultGroups 	= $objDatabase->Execute($queryGroups);

		if ($objResultGroups !== false) {
			$arrSelectedGroups = explode(";", $objResultGroups->fields['groups']);
		}

		//get all groups
		$queryGroups 		= "SELECT group_id,group_name,group_description,is_active,type
							 	 FROM ".DBPREFIX."access_user_groups
				   				WHERE is_active = '1'";

		$objResultGroups 	= $objDatabase->Execute($queryGroups);

		if ($objResultGroups !== false) {
			while(!$objResultGroups->EOF) {
				$arrGroups[$objResultGroups->fields['group_id']] = $objResultGroups->fields['group_name'];
				$objResultGroups->moveNext();
			}
		}

		//make group select
		$options = "";
		foreach($arrGroups as $groupKey => $groupName){
			if($type == 0){
				if (!in_array($groupKey, $arrSelectedGroups)){
					$options .= "<option value='".$groupKey."'>".$groupName."</option>";
				}
			}else{
				if (in_array($groupKey, $arrSelectedGroups)){
					$options .= "<option value='".$groupKey."'>".$groupName."</option>";
				}
			}
		}

		return $options;
	}


	/**
     * get registrations form
     *
     * @param int $noteId
     * @param string $type
     */
	function _getFormular($noteId, $frmType, $arrUserData=null)
	{
		global $objDatabase, $_ARRAYLANG, $_LANGID;


		 $arrSysFieldNames = array(
			'1'		=> $_ARRAYLANG['TXT_CALENDAR_REG_FIRSTNAME'],
			'2'		=> $_ARRAYLANG['TXT_CALENDAR_REG_LASTNAME'],
			'3'		=> $_ARRAYLANG['TXT_CALENDAR_REG_STREET'],
			'4'		=> $_ARRAYLANG['TXT_CALENDAR_REG_ZIP'],
			'5'		=> $_ARRAYLANG['TXT_CALENDAR_REG_CITY'],
			'6'		=> $_ARRAYLANG['TXT_CALENDAR_REG_MAIL'],
			'7'		=> $_ARRAYLANG['TXT_CALENDAR_REG_WEBSITE'],
			'8'		=> $_ARRAYLANG['TXT_CALENDAR_REG_PHONE'],
			'9'		=> $_ARRAYLANG['TXT_CALENDAR_REG_MOBILE'],
			'10'	=> $_ARRAYLANG['TXT_CALENDAR_REG_INTERESSTS'],
			'11'	=> $_ARRAYLANG['TXT_CALENDAR_REG_PROFESSION'],
			'12'	=> $_ARRAYLANG['TXT_CALENDAR_REG_COMPANY'],
			'13'	=> $_ARRAYLANG['TXT_CALENDAR_REG_ESCORT'],
		);

		if ($frmType == "backend") {
			for ($i=1; $i <= 20; $i++) {
				$queryField 		= "SELECT `id`,`note_id`,`name`,`type`,`required`,`order`,`key`
									 	 FROM ".DBPREFIX."module_calendar".$this->mandateLink."_form_fields
						   				WHERE note_id = '".$noteId."' AND `key`='".$i."'";

				$objResultField 	= $objDatabase->SelectLimit($queryField, 1);

				if ($objResultField !== false) {
					$id			= $objResultField->fields['id'];
					$name		= $objResultField->fields['name'];
					$order		= $objResultField->fields['order'];
					$type		= $objResultField->fields['type'];

					if(!empty($objResultField->fields['name'])){
						$status 		= 'checked="checked"';
						$statusImg		= 'green';
					} else {
						$status 		= '';
						$statusImg		= 'red';
					}

					if(!empty($objResultField->fields['required']) == 1){
						$required	= 'checked="checked"';
					} else {
						$required	= '';
					}
				}

				if($i <= count($arrSysFieldNames)){
					$disabled 	= 'readonly="readonly"';
					$type		= 1;
					$name		= $arrSysFieldNames[$i];
				} else {
					$disabled 	= '';
				}

				//options
				switch ($type){
					case 1:
						$options	.=	'<option value="0">'.$_ARRAYLANG['TXT_CALENDAR_CHOSE_TYPE'].'</option>';
						$options	.=	'<option value="1" selected="selected">'.$_ARRAYLANG['TXT_CALENDAR_INPUTFIELD'].'</option>';
						$options	.=	'<option value="2">'.$_ARRAYLANG['TXT_CALENDAR_TEXTAREA'].'</option>';
						$options	.=	'<option value="3">'.$_ARRAYLANG['TXT_CALENDAR_CHECKBOCK'].'</option>';
						break;
					case 2:
						$options	.=	'<option value="0">'.$_ARRAYLANG['TXT_CALENDAR_CHOSE_TYPE'].'</option>';
						$options	.=	'<option value="1">'.$_ARRAYLANG['TXT_CALENDAR_INPUTFIELD'].'</option>';
						$options	.=	'<option value="2" selected="selected">'.$_ARRAYLANG['TXT_CALENDAR_TEXTAREA'].'</option>';
						$options	.=	'<option value="3">'.$_ARRAYLANG['TXT_CALENDAR_CHECKBOCK'].'</option>';
						break;
					case 3:
						$options	.=	'<option value="0">'.$_ARRAYLANG['TXT_CALENDAR_CHOSE_TYPE'].'</option>';
						$options	.=	'<option value="1">'.$_ARRAYLANG['TXT_CALENDAR_INPUTFIELD'].'</option>';
						$options	.=	'<option value="2">'.$_ARRAYLANG['TXT_CALENDAR_TEXTAREA'].'</option>';
						$options	.=	'<option value="3" selected="selected">'.$_ARRAYLANG['TXT_CALENDAR_CHECKBOCK'].'</option>';
						break;
					default:
						$options	.=	'<option value="0" selected="selected">'.$_ARRAYLANG['TXT_CALENDAR_CHOSE_TYPE'].'</option>';
						$options	.=	'<option value="1">'.$_ARRAYLANG['TXT_CALENDAR_INPUTFIELD'].'</option>';
						$options	.=	'<option value="2">'.$_ARRAYLANG['TXT_CALENDAR_TEXTAREA'].'</option>';
						$options	.=	'<option value="3">'.$_ARRAYLANG['TXT_CALENDAR_CHECKBOCK'].'</option>';
						break;
				}

				$this->_objTpl->setVariable(array(
					'CALENDAR_FIELD_ROW'			  		=> ($i % 2) ? 'row1'  : 'row2',
					'CALENDAR_FIELD_DISABLED'			  	=> $disabled,
					'CALENDAR_FIELD_OPTIONS'			  	=> $options,
					'CALENDAR_FIELD_ORDER'			  		=> intval($order),
					'CALENDAR_FIELD_ID'			  			=> $id,
					'CALENDAR_FIELD_NAME'			  		=> $name,
					'CALENDAR_FIELD_STATUS'			  		=> $status,
					'CALENDAR_FIELD_STATUS_IMG'			  	=> $statusImg,
					'CALENDAR_FIELD_REQUIRED'			  	=> $required,
					'CALENDAR_FIELD_KEY'				  	=> $i
				));
				$this->_objTpl->parse("calendarRegFields");

				$options	=	"";
				$status		=	"";
				$name		=	"";
			}
		} else {
			$queryField 		= "SELECT `id`,`note_id`,`name`,`type`,`required`,`order`,`key`
								 	 FROM ".DBPREFIX."module_calendar".$this->mandateLink."_form_fields
					   				WHERE note_id = '".$noteId."'
					   			 ORDER BY `order`, `id`";

			$objResultField 	= $objDatabase->Execute($queryField);

			if ($objResultField !== false) {
				while(!$objResultField->EOF) {

					if ($objResultField->fields['required'] == 1) {
						$jsFields	.= 'if (document.getElementsByName("signForm['.$objResultField->fields['id'].']")[0].value == "") {
											errorMsg = errorMsg + "- '.$objResultField->fields['name'].'\n";
										}';
						$required	 = "<font color='red'> *</font>";
					} else {
						$required	 = "";
					}

					switch ($objResultField->fields['type']){
						case 1:
							$inputField = '<input type="text" value="'.$arrUserData[$objResultField->fields['key']].'" name="signForm['.$objResultField->fields['id'].']" style="width: 250px;" />';
							break;
						case 2:
							$inputField = '<textarea  name="signForm['.$objResultField->fields['id'].']" style="width: 250px;"></textarea>';
							break;
						case 3:
							$inputField = '<input type="checkbox" value="1" name="signForm['.$objResultField->fields['id'].']" />';
							break;
					}

					$this->_objTpl->setVariable(array(
						'CALENDAR_NOTE_ID'			  			=> $noteId,
						'CALENDAR_FIELD_NAME'				  	=> $objResultField->fields['name'].$required,
						'CALENDAR_FIELD_INPUT'				  	=> $inputField,
					));

					$this->_objTpl->parse("calendarRegFields");

					$objResultField->MoveNext();
				}
			}

			$frmJS	 	=  '<script type="text/javascript">
							<!--
							function checkFields(){
								var errorMsg = "";

								with(document.getElementById("signForm")) {
									'.$jsFields.'
								}

								if (errorMsg != "") {
									alert ("'.$_ARRAYLANG['TXT_CALENDAR_CHECK_REQUIRED'].':\n\n" + errorMsg);
									return false;
								}else{
									return true;
								}
							}
							-->
							</script>';
			$this->_objTpl->setVariable(array(
				'CALENDAR_FIELD_JS'				  	=> $frmJS,
			));

			$this->_objTpl->parse("signForm");
			$this->_objTpl->hideBlock("signStatus");
		}
	}


	/**
     * send registration
     *
     * @param int $noteId
     */
	function _sendRegistration($noteId)
	{
		global $_CONFIG, $objDatabase, $_ARRAYLANG, $objLanguage;

		//get mail template
		$query 			= "SELECT mailTitle, mailContent
		              	 	 FROM ".DBPREFIX."module_calendar".$this->mandateLink."
			            	WHERE id = '".$noteId."'";

		$objResult 		= $objDatabase->SelectLimit($query, 1);
		$mailTitle 		= $objResult->fields['mailTitle'];
		$mailContent 	= $objResult->fields['mailContent'];

		//get note data
		$queryNote = "SELECT 	id,
							catid,
							startdate,
							enddate,
							name,
							groups,
							all_groups,
							public,
							mailContent,
							mailTitle,
							num,
							`key`
		    		FROM 	".DBPREFIX."module_calendar".$this->mandateLink."
		    	   WHERE 	id = '".$noteId."'";



		$objResultNote 	= $objDatabase->SelectLimit($queryNote, 1);

		$GoupsNote		= substr($objResultNote->fields['groups'],0,-1);

		$arrGoupsNote 	= explode(";",$GoupsNote);

		$queryUser = "SELECT 	id,
								email,
								firstname,
								lastname,
								groups
		    			FROM 	".DBPREFIX."access_users
		    	 	  WHERE 	active = '1'";

		$objResultUser 	= $objDatabase->Execute($queryUser);

		if ($objResultUser !== false) {
			while(!$objResultUser->EOF) {
				if ($objResultNote->fields['all_groups'] == 1) {
					if (!empty($objResultUser->fields['email'])) {
						$arrUsers[$objResultUser->fields['id']]['email'] 		= $objResultUser->fields['email'];
						$arrUsers[$objResultUser->fields['id']]['lastname'] 	= $objResultUser->fields['lastname'];
						$arrUsers[$objResultUser->fields['id']]['firstname'] 	= $objResultUser->fields['firstname'];
					}
				} else {
					if (!empty($objResultUser->fields['email'])) {
						$arrGoupsUser = explode(",",$objResultUser->fields['groups']);
						foreach ($arrGoupsNote as $arrKey => $groupId){
							if (in_array($groupId, $arrGoupsUser)) {
								$arrUsers[$objResultUser->fields['id']]['email'] 		= $objResultUser->fields['email'];
								$arrUsers[$objResultUser->fields['id']]['lastname'] 	= $objResultUser->fields['lastname'];
								$arrUsers[$objResultUser->fields['id']]['firstname'] 	= $objResultUser->fields['firstname'];
							}
						}
					}
				}
				$objResultUser->moveNext();
			}
		}

		$objCategory = $objDatabase->SelectLimit('SELECT lang FROM '.DBPREFIX.'module_calendar_categories WHERE id='.$objResultNote->fields['catid']);
		if ($objCategory !== false) {
			$languageId = $objCategory->fields['lang'];
		}

		//get mail obj
		if (@include_once ASCMS_LIBRARY_PATH.'/phpmailer/class.phpmailer.php') {
			$objMail = new phpmailer();

			if ($_CONFIG['coreSmtpServer'] > 0 && @include_once ASCMS_CORE_PATH.'/SmtpSettings.class.php') {
				$objSmtpSettings = new SmtpSettings();
				if (($arrSmtp = $objSmtpSettings->getSmtpAccount($_CONFIG['coreSmtpServer'])) !== false) {
					$objMail->IsSMTP();
					$objMail->Host = $arrSmtp['hostname'];
					$objMail->Port = $arrSmtp['port'];
					$objMail->SMTPAuth = true;
					$objMail->Username = $arrSmtp['username'];
					$objMail->Password = $arrSmtp['password'];
				}
			}

			foreach($arrUsers as $userId => $arrUser){
				$mailTitleReloaded		= $mailTitle;
				$mailContentReloaded	= $mailContent;

				$key		= base64_encode($noteId."#".$userId."#".$objResultNote->fields['key']);
				$url		= $_CONFIG['domainUrl'].ASCMS_PATH_OFFSET;
				$date		= date(ASCMS_DATE_FORMAT);
				$firstname	= $arrUser['firstname'];
				$lastname	= $arrUser['lastname'];
				$link		= "http://".$url."/".($_CONFIG['useVirtualLanguagePath'] == 'on' ? $objLanguage->getLanguageParameter($languageId, 'lang').'/' : null).CONTREXX_DIRECTORY_INDEX."?section=calendar".$this->mandateLink."&cmd=sign&key=".$key;
				$title		= $objResultNote->fields['name'];
				$startdate	= date("Y-m-d H:i", $objResultNote->fields['startdate']);
				$enddate 	= date("Y-m-d H:i", $objResultNote->fields['enddate']);
				$num 		= $objResultNote->fields['num'];

				//replace placeholder
				$array_1 = array('[[FIRSTNAME]]', '[[LASTNAME]]', '[[REG_LINK]]', '[[TITLE]]', '[[START_DATE]]', '[[END_DATE]]', '[[URL]]', '[[DATE]]', '[[NUM_SUBSCRIBER]]');
				$array_2 = array($firstname, $lastname, $link, $title, $startdate, $enddate, $url, $date, $num);

				for($x = 0; $x < 8; $x++){
				  $mailTitleReloaded = str_replace($array_1[$x], $array_2[$x], $mailTitleReloaded);
				}

				for($x = 0; $x < 8; $x++){
				  $mailContentReloaded = str_replace($array_1[$x], $array_2[$x], $mailContentReloaded);
				}

				//send mail
				$objMail->CharSet = CONTREXX_CHARSET;
				$objMail->From = $_CONFIG['coreAdminEmail'];
				$objMail->FromName = $_CONFIG['coreAdminName'];
				$objMail->AddReplyTo($_CONFIG['coreAdminEmail']);
				$objMail->Subject = $mailTitleReloaded;
				$objMail->IsHTML(false);
				$objMail->Body = $mailContentReloaded;
				$objMail->AddAddress($arrUser['email']);
				$objMail->Send();
				$objMail->ClearAddresses();
			}
		}
	}


	/**
     * send confirmation
     *
     * @param int $userId
     * @param int $noteId
     * @param int $regId
     */
	function _sendConfirmation($user, $noteId, $regId)
	{
		global $_CONFIG, $objDatabase, $_ARRAYLANG;

		//get mail template
		$query 			= "SELECT setvalue
		              	 	 FROM ".DBPREFIX."module_calendar".$this->mandateLink."_settings
			            	WHERE setid = '3'";

		$objResult 		= $objDatabase->SelectLimit($query, 1);
		$mailTitle 	= $objResult->fields['setvalue'];

		$query 			= "SELECT setvalue
		              	 	 FROM ".DBPREFIX."module_calendar".$this->mandateLink."_settings
			            	WHERE setid = '4'";

		$objResult 		= $objDatabase->SelectLimit($query, 1);
		$mailContent = $objResult->fields['setvalue'];

		//get note data
		$queryNote = "SELECT 	id,
								startdate,
								enddate,
								name
			    		FROM 	".DBPREFIX."module_calendar".$this->mandateLink."
			    	   WHERE 	id = '".$noteId."'";

		$objResultNote 	= $objDatabase->SelectLimit($queryNote, 1);

		//get user data
		if (is_numeric($user)) {
			$queryUser = "SELECT 	id,
									firstname,
									lastname,
									email
				    		FROM 	".DBPREFIX."access_users
				    	   WHERE 	id = '".$user."'";

			$objResultUser 	= $objDatabase->SelectLimit($queryUser, 1);

			$firstname		= $objResultUser->fields['firstname'];
			$lastname		= $objResultUser->fields['lastname'];
			$toMail 		= $objResultUser->fields['email'];
		} else {
			$firstname		= "";
			$lastname		= "";
			$toMail 		= $user;
		}


		//get reg data
		$queryReg = "SELECT 	id,
								type
			    		FROM 	".DBPREFIX."module_calendar".$this->mandateLink."_registrations
			    	   WHERE 	id = '".$regId."'";

		$objResultReg 	= $objDatabase->SelectLimit($queryReg, 1);

		//get mail obj
		if (@include_once ASCMS_LIBRARY_PATH.'/phpmailer/class.phpmailer.php') {
			$objMail = new phpmailer();

			if ($_CONFIG['coreSmtpServer'] > 0 && @include_once ASCMS_CORE_PATH.'/SmtpSettings.class.php') {
				$objSmtpSettings = new SmtpSettings();
				if (($arrSmtp = $objSmtpSettings->getSmtpAccount($_CONFIG['coreSmtpServer'])) !== false) {
					$objMail->IsSMTP();
					$objMail->Host = $arrSmtp['hostname'];
					$objMail->Port = $arrSmtp['port'];
					$objMail->SMTPAuth = true;
					$objMail->Username = $arrSmtp['username'];
					$objMail->Password = $arrSmtp['password'];
				}
			}

			$url		= $_CONFIG['domainUrl'].ASCMS_PATH_OFFSET;
			$date		= date(ASCMS_DATE_FORMAT);
			$title		= $objResultNote->fields['name'];
			$startdate	= date("Y-m-d H:i", $objResultNote->fields['startdate']);
			$enddate 	= date("Y-m-d H:i", $objResultNote->fields['enddate']);
			$type 		= $objResultReg->fields['type'] == 1 ? $_ARRAYLANG['TXT_CALENDAR_REG_REGISTRATION'] : $_ARRAYLANG['TXT_CALENDAR_REG_SIGNOFF'];

			//replace placeholder
			$array_1 = array('[[FIRSTNAME]]', '[[LASTNAME]]', '[[TITLE]]', '[[START_DATE]]', '[[END_DATE]]', '[[URL]]', '[[DATE]]', '[[REG_TYPE]]');
			$array_2 = array($firstname, $lastname, $title, $startdate, $enddate, $url, $date, $type);

			for($x = 0; $x < 8; $x++){
			  $mailTitle = str_replace($array_1[$x], $array_2[$x], $mailTitle);
			}

			for($x = 0; $x < 8; $x++){
			  $mailContent = str_replace($array_1[$x], $array_2[$x], $mailContent);
			}

			//send mail
			$objMail->CharSet = CONTREXX_CHARSET;
			$objMail->From = $_CONFIG['coreAdminEmail'];
			$objMail->FromName = $_CONFIG['coreAdminName'];
			$objMail->AddReplyTo($_CONFIG['coreAdminEmail']);
			$objMail->Subject = $mailTitle;
			$objMail->IsHTML(false);
			$objMail->Body = $mailContent;
			$objMail->AddAddress($toMail);
			$objMail->Send();
			$objMail->ClearAddresses();
		}
	}


	function _sendNotification($mail, $firstname, $lastname, $noteId, $regId)
	{
		global $_CONFIG, $objDatabase, $_ARRAYLANG;

		//get note data
		$queryNote 		= "SELECT 	id,
									name,
									notification,
									notification_address
				    		FROM 	".DBPREFIX."module_calendar".$this->mandateLink."
				    	   WHERE 	id = '".$noteId."'";

		$objResultNote 	= $objDatabase->SelectLimit($queryNote, 1);

		//get mail template
		$query 			= "SELECT setvalue
		              	 	 FROM ".DBPREFIX."module_calendar".$this->mandateLink."_settings
			            	WHERE setid = '5'";

		$objResult 		= $objDatabase->SelectLimit($query, 1);
		$mailTitle 		= $objResult->fields['setvalue'];

		$query 			= "SELECT setvalue
		              	 	 FROM ".DBPREFIX."module_calendar".$this->mandateLink."_settings
			            	WHERE setid = '6'";

		$objResult 		= $objDatabase->SelectLimit($query, 1);
		$mailContent 	= $objResult->fields['setvalue'];

		//get reg data
		$queryReg = "SELECT 	id,
								type
			    		FROM 	".DBPREFIX."module_calendar".$this->mandateLink."_registrations
			    	   WHERE 	id = '".$regId."'";

		$objResultReg 	= $objDatabase->SelectLimit($queryReg, 1);



		//get mail obj
		if (@include_once ASCMS_LIBRARY_PATH.'/phpmailer/class.phpmailer.php') {
			if ($objResultNote->fields['notification'] == 1) {
				$objMail = new phpmailer();

				if ($_CONFIG['coreSmtpServer'] > 0 && @include_once ASCMS_CORE_PATH.'/SmtpSettings.class.php') {
					$objSmtpSettings = new SmtpSettings();
					if (($arrSmtp = $objSmtpSettings->getSmtpAccount($_CONFIG['coreSmtpServer'])) !== false) {
						$objMail->IsSMTP();
						$objMail->Host = $arrSmtp['hostname'];
						$objMail->Port = $arrSmtp['port'];
						$objMail->SMTPAuth = true;
						$objMail->Username = $arrSmtp['username'];
						$objMail->Password = $arrSmtp['password'];
					}
				}

				$url			=  "http://".$_CONFIG['domainUrl'].ASCMS_PATH_OFFSET;
				$date			= date(ASCMS_DATE_FORMAT);
				$type 			= $objResultReg->fields['type'] == 1 ? $_ARRAYLANG['TXT_CALENDAR_REG_REGISTRATION'] : $_ARRAYLANG['TXT_CALENDAR_REG_SIGNOFF'];
				$title			= $objResultNote->fields['name'];
				$addresses		= explode(",", $objResultNote->fields['notification_address']);

				//replace placeholder
				$array_1 = array('[[FIRSTNAME]]', '[[LASTNAME]]', '[[TITLE]]', '[[E-MAIL]]', '[[URL]]', '[[DATE]]', '[[REG_TYPE]]');
				$array_2 = array($firstname, $lastname, $title, $mail, $url, $date, $type);

				for($x = 0; $x < 8; $x++){
				  $mailTitle = str_replace($array_1[$x], $array_2[$x], $mailTitle);
				}

				for($x = 0; $x < 8; $x++){
				  $mailContent = str_replace($array_1[$x], $array_2[$x], $mailContent);
				}


				//send mail
				$objMail->CharSet = CONTREXX_CHARSET;
				$objMail->From = $_CONFIG['coreAdminEmail'];
				$objMail->FromName = $_CONFIG['coreAdminName'];
				$objMail->AddReplyTo($_CONFIG['coreAdminEmail']);
				$objMail->Subject = $mailTitle;
				$objMail->IsHTML(false);
				$objMail->Body = $mailContent;

				//add addresses
				foreach ($addresses as $key => $email) {
					$objMail->AddAddress($email);
				}

				$objMail->Send();
				$objMail->ClearAddresses();
			}
		}
	}
}
}
?>
