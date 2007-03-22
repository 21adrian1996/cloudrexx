<?php
/**
 * Logging manager
 *
 * Class to see logging
 *
 * @copyright   CONTREXX CMS - ASTALAVISTA IT AG
 * @author		Astalavista Development Team <thun@astalvista.ch>
 * @version		1.0.0
 * @package     contrexx
 * @subpackage  core
 * @todo        Edit PHP DocBlocks!
 */

/**
 * Class Logging manager
 *
 * Class to see logging
 *
 * @copyright   CONTREXX CMS - ASTALAVISTA IT AG
 * @author		Astalavista Development Team <thun@astalvista.ch>
 * @access		public
 * @version		1.0.0
 * @package     contrexx
 * @subpackage  core
 */
class logmanager
{
    var $statusMessage = "";

    /**
    * Constructor
    *
    * @param  string
    * @access public
    */
    function logmanager()
    {
    	global $_CORELANG, $objTemplate;

    	$objTemplate->setVariable(array(
    	    'CONTENT_TITLE'      => $_CORELANG['TXT_LOG_ADMINISTRATION'],
    	    'CONTENT_NAVIGATION' => "<a href='?cmd=log'>[".$_CORELANG['TXT_LOG_INDEX']."]</a>"
    	));
    }

    function getLogPage()
    {
    	global $_CORELANG, $objTemplate;

        switch($_GET['act']){
		    case "del":
			    $this->deleteLog();
                $action = $this->showLogs();
			    break;

		    case "details":
                $action = $this->showDetails();
			    break;

		    case "stats":
                $action = $this->showStats();
			    break;

			default:
                $action = $this->showLogs();
			    break;
		}

		$objTemplate->setVariable(array(
			'CONTENT_TITLE'				=> $_CORELANG['TXT_LOG_ADMINISTRATION'],
			'CONTENT_STATUS_MESSAGE'	=> trim($this->statusMessage)
		));
    }


    function deleteLog()
    {
    	global $objDatabase, $_CORELANG;

		if(!empty($_REQUEST['logId'])) {
			if ($objDatabase->Execute("DELETE FROM ".DBPREFIX."log WHERE id=".intval($_REQUEST['logId'])) === false) {
		    	$this->errorHandling();
		        return false;
	    	}
	    	return true;
		} else {
			return false;
		}
    }



	/*
	* Show Logs
	*
    */
    function showLogs()
    {
    	global $objDatabase, $_CORELANG, $_CONFIG, $objTemplate;

    	$objTemplate->addBlockfile('ADMIN_CONTENT', 'log', 'log.html');
    	$objTemplate->setVariable(array(
			'TXT_CONFIRM_DELETE_DATA'    => $_CORELANG['TXT_CONFIRM_DELETE_DATA'],
			'TXT_ACTION_IS_IRREVERSIBLE' => $_CORELANG['TXT_ACTION_IS_IRREVERSIBLE'],
			'TXT_HOSTNAME'               => $_CORELANG['TXT_HOSTNAME'],
			'TXT_USER_NAME'			     => $_CORELANG['TXT_USERNAME'],
			'TXT_LOGTIME'                => $_CORELANG['TXT_LOGTIME'],
			'TXT_USERAGENT'              => $_CORELANG['TXT_USERAGENT'],
			'TXT_BROWSERLANGUAGE'        => $_CORELANG['TXT_BROWSERLANGUAGE'],
			'TXT_ACTION'                 => $_CORELANG['TXT_ACTION'],
			'TXT_SEARCH'				 => $_CORELANG['TXT_SEARCH']
		));

		$term = contrexx_strip_tags(trim($_REQUEST['term']));
    	$objTemplate->setVariable('LOG_SEARCHTERM', $term);
		$q_search = "";
		if(!empty($term)){
		   $q_search = "AND ( log.id LIKE '%$term%'
		                   OR log.userid LIKE '%$term%'
		                   OR log.useragent LIKE '%$term%'
		                   OR log.userlanguage LIKE '%$term%'
		                   OR log.remote_addr LIKE '%$term%'
		                   OR log.remote_host LIKE '%$term%'
		                   OR log.http_via LIKE '%$term%'
		                   OR log.http_client_ip LIKE '%$term%'
		                   OR log.http_x_forwarded_for LIKE '%$term%'
		                   OR log.referer LIKE '%$term%'
		                   OR users.username LIKE '%$term%'
		               )";
		}

		$q = "SELECT log.id AS id,
					 log.userid AS userid,
		             log.datetime AS datetime,
					 log.useragent AS useragent,
					 log.userlanguage AS userlanguage,
					 log.remote_addr AS remote_addr,
					 log.remote_host AS remote_host,
					 log.http_via AS http_via,
					 log.http_client_ip AS http_client_ip,
					 log.http_x_forwarded_for AS http_x_forwarded_for,
					 log.referer AS referer,
					 users.id AS uid,
					 users.username AS username
		        FROM ".DBPREFIX."log AS log,
		             ".DBPREFIX."access_users AS users
		       WHERE log.userid=users.id " . $q_search .
		    "ORDER BY log.id DESC";

		$objResult = $objDatabase->Execute($q);
		if ($objResult === false) {
		    $this->errorHandling();
			return false;
		}

		$pos = intval($_GET[pos]);
		$count = $objResult->RecordCount();

		if(!empty($term)) {
		    $paging = getPaging($count, $pos, "&cmd=log&amp;term=$term", "<b>".$_CORELANG['TXT_LOG_ENTRIES']."</b>", true);
		} else {
		    $paging = getPaging($count, $pos, "&amp;cmd=log", "<b>".$_CORELANG['TXT_LOG_ENTRIES']."</b>", true);
		}

		$objResult = $objDatabase->SelectLimit($q, $_CONFIG['corePagingLimit'], $pos);
		if ($objResult === false) {
		    $this->errorHandling();
			return false;
		}

		$objTemplate->setVariable(array(
			'LOG_PAGING'	=> $paging,
			'LOG_TOTAL'		=> $count
		));

		while (!$objResult->EOF) {
			if (($i % 2) == 0) {$class="row1";} else {$class="row2";}
			$objTemplate->setVariable(array(
			    'LOG_ROWCLASS' 		  => $class,
			    'LOG_ID' 			  => $objResult->fields['id'],
			    'LOG_USERID' 		  => $objResult->fields['userid'],
			    'LOG_USERNAME'	 	  => $objResult->fields['username'],
			    'LOG_TIME' 		 	  => $objResult->fields['datetime'],
			    'LOG_USERAGENT'	 	  => substr_replace($objResult->fields['useragent'],' ...', 90),
			    'LOG_USERLANGUAGE' 	  => substr_replace($objResult->fields['userlanguage'],' ...', 20),
			    'LOG_REMOTE_ADDR'	  => $objResult->fields['remote_addr'],
			    'LOG_REMOTE_HOST'	  => substr_replace($objResult->fields['remote_host'],'', 60),
			    'LOG_HTTP_VIA'     	  => $objResult->fields['http_via'],
			    'LOG_CLIENT_IP'	  	  => $objResult->fields['http_client_ip'],
			    'LOG_X_FORWARDED_FOR' => $objResult->fields['http_x_forwarded_for'],
			    'LOG_REFERER' 		  => $objResult->fields['referer']
             ));
			$objTemplate->parse("logRow");
			$i++;
			$objResult->MoveNext();
		}
    }




    /**
    * Set the error Message (void())
    *
    * @global    array      $_CORELANG
    */
    function errorHandling(){
    	global $_CORELANG;
        $this->statusMessage .= $_CORELANG['TXT_DATABASE_QUERY_ERROR']."<br>";
    }
}
?>