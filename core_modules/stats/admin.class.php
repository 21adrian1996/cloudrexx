<?php
/**
 * Stats
 * @copyright   ASTALAVISTA SECURE CMS - 2005 COMVATION AG
 * @author      Christian Wehrli <christian.wehrli@astalavista.ch>
 * @version     $Id: index.class.php,v 1.11 2003/05/05 10:10:32 hitsch Exp $
 * @package     contrexx
 * @subpackage  core_module_stats
 * @todo        Edit PHP DocBlocks!
 */

//Security-Check
if (eregi("admin.class.php",$_SERVER['PHP_SELF'])){
    Header("Location: index.php");
    die();
}

/**
 * Includes
 */
require_once ASCMS_CORE_MODULE_PATH . '/stats/lib/statsLib.class.php';

/**
 * Stats
 *
 * Class with different methodes to get statistical information about
 * webaccess
 * @copyright   ASTALAVISTA SECURE CMS - 2005 COMVATION AG
 * @author      Christian Wehrli <christian.wehrli@astalavista.ch>
 * @version     $Id: index.class.php,v 1.11 2003/05/05 10:10:32 hitsch Exp $
 * @package     contrexx
 * @subpackage  core_module_stats
 */
class stats extends statsLibrary
{

	var $pageTitle;
	var $strErrMessage = '';
	var $strOkMessage = '';
	var $_objTpl;

	var $arrColourDefinitions = array(
		'1'		=> 'TXT_MONOCHROME',
		'2'		=> 'TXT_CGA',
		'4'		=> 'TXT_VGA',
		'8'		=> 'TXT_SVGA',
		'15'	=> 'TXT_HIGH_COLOR',
		'16'	=> 'TXT_HIGH_COLOR',
		'24'	=> 'TXT_TRUE_COLOR',
		'32'	=> 'TXT_TRUE_COLOR_WITH_ALPHA_CHANNEL'
	);

    /**
    * constructor
    */
    function stats(){
       $this->__construct();
    }

    /**
    * constructor
    *
    * global	object	$objTemplate
    * global	array	$_ARRAYLANG
    */
    function __construct(){
    	global $objTemplate, $_ARRAYLANG;

    	$this->_objTpl = &new HTML_Template_Sigma(ASCMS_CORE_MODULE_PATH.'/stats/template');
		$this->_objTpl->setErrorHandling(PEAR_ERROR_DIE);

		$objTemplate->setVariable("CONTENT_NAVIGATION","<a href='index.php?cmd=stats&amp;stat=visitorDetails'>".$_ARRAYLANG['TXT_VISITOR_DETAILS']."</a>
												<a href='index.php?cmd=stats&amp;stat=requests'>".$_ARRAYLANG['TXT_VISITORS_AND_PAGE_VIEWS']."</a>
												<a href='index.php?cmd=stats&amp;stat=referer'>".$_ARRAYLANG['TXT_REFERER']."</a>
												<a href='index.php?cmd=stats&amp;stat=mvp'>".$_ARRAYLANG['TXT_MOST_POPULAR_PAGES']."</a>
												<a href='index.php?cmd=stats&amp;stat=spiders'>".$_ARRAYLANG['TXT_SEARCH_ENGINES']."</a>
												<a href='index.php?cmd=stats&amp;stat=clients'>".$_ARRAYLANG['TXT_USER_INFORMATION']."</a>
												<a href='index.php?cmd=stats&amp;stat=search'>".$_ARRAYLANG['TXT_SEARCH_TERMS']."</a>
												<a href='index.php?cmd=stats&amp;stat=settings'>".$_ARRAYLANG['TXT_SETTINGS']."</a>");

		$this->firstDate = time();
		$this->_initConfiguration();
    }

    /**
    * Get content
    *
    * Get the content of the requested page
    *
    * @access	public
    * @global	object	$objTemplate
    * @global 	object	$objPerm
    * @see	_showRequests(), _showMostViewedPages(), _showSpiders(), _showClients(), _showSearchTerms()
    * @return	mixed	Template content
    */
    function getContent(){
    	global $objTemplate, $objPerm;

    	$this->_optimizeTables();

    	if(!isset($_GET['stat'])){
    		$_GET['stat'] = "";
    	}

    	switch ($_GET['stat']){
    		case 'visitorDetails':
    			$this->_showVisitorDetails();
    			break;

    		case 'requests': // show request stats
    			$this->_showRequests();
    			break;

    		case 'referer':
    			$this->_showReferer();
    			break;

    		case 'mvp': // most viewed pages
    			$this->_showMostViewedPages();
    			break;

    		case 'spiders':
    			$this->_showSpiders();
    			break;

    		case 'clients': // show client stats
    			$this->_showClients();
    			break;

    		case 'search': // show search term stats
    			$this->_showSearchTerms();
    			break;

    		case 'settings':
    			$objPerm->checkAccess(40, 'static');
    			$this->_showSettings();
    			break;

    		default: // show overview
    			$this->_showVisitorDetails();
    			break;
    	}

    	$objTemplate->setVariable(array(
    		'CONTENT_TITLE'				=> $this->pageTitle,
			'CONTENT_OK_MESSAGE'		=> $this->strOkMessage,
			'CONTENT_STATUS_MESSAGE'	=> $this->strErrMessage,
    		'ADMIN_CONTENT'				=> $this->_objTpl->get()
    	));
    }

    function _optimizeTables() {
    	global $objDatabase;
    	$query = "OPTIMIZE TABLE `".DBPREFIX."stats_browser`,
    							 `".DBPREFIX."stats_colourdepth`,
    							 `".DBPREFIX."stats_config`,
    							 `".DBPREFIX."stats_country`,
    							 `".DBPREFIX."stats_hostname`,
    							 `".DBPREFIX."stats_javascript`,
    							 `".DBPREFIX."stats_operatingsystem`,
    							 `".DBPREFIX."stats_referer`,
    							 `".DBPREFIX."stats_requests`,
    							 `".DBPREFIX."stats_requests_summary`,
    							 `".DBPREFIX."stats_screenresolution`,
    							 `".DBPREFIX."stats_search`,
    							 `".DBPREFIX."stats_spiders`,
    							 `".DBPREFIX."stats_spiders_summary`,
    							 `".DBPREFIX."stats_visitors`,
    							 `".DBPREFIX."stats_visitors_summary`";
    	$objDatabase->Execute($query);
    }

    function _showVisitorDetails() {
    	global $_ARRAYLANG;

    	$this->_objTpl->loadTemplateFile('module_stats_visitor_details.html',true,true);
    	$this->pageTitle = $_ARRAYLANG['TXT_VISITOR_DETAILS'];

    	$this->_initVisitorDetails();

    	// set language variables
    	$this->_objTpl->setVariable(array(
			'TXT_VISITOR_DETAILS'		=> $_ARRAYLANG['TXT_VISITOR_DETAILS'],
    		'TXT_LAST_REQUEST'			=> $_ARRAYLANG['TXT_LAST_REQUEST'],
    		'TXT_USERAGENT'				=> $_ARRAYLANG['TXT_USERAGENT'],
    		'TXT_IP_ADDRESS'			=> $_ARRAYLANG['TXT_IP_ADDRESS'],
    		'TXT_HOSTNAME'				=> $_ARRAYLANG['TXT_HOSTNAME'],
    		'TXT_PROXY_USERAGENT'		=> $_ARRAYLANG['TXT_PROXY_USERAGENT'],
    		'TXT_PROXY_IP'				=> $_ARRAYLANG['TXT_PROXY_IP'],
    		'TXT_PROXY_HOSTNAME'		=> $_ARRAYLANG['TXT_PROXY_HOSTNAME']
    	));

    	// set client details
    	if (count($this->arrVisitorsDetails)>0) {
	    	$rowClass = 1;
	    	foreach ($this->arrVisitorsDetails as $stats) {
				$this->_objTpl->setVariable(array(
					'STATS_REQUESTS_CLIENT_ROW_CLASS'		=> $rowClass%2 == 1 ? "row2" : "row1",
					'STATS_REQUESTS_CLIENT_HOST'			=> empty($stats['client_host']) ? "-" : "<a href=\"?cmd=nettools&amp;tpl=whois&amp;address=".$stats['client_host']."\" title=\"".$_ARRAYLANG['TXT_SHOW_DETAILS']."\">".$stats['client_host']."</a>",
					'STATS_REQUESTS_CLIENT_IP'				=> empty($stats['client_ip']) ? "-" : "<a href=\"?cmd=nettools&amp;tpl=whois&amp;address=".$stats['client_ip']."\" title=\"".$_ARRAYLANG['TXT_SHOW_DETAILS']."\">".$stats['client_ip']."</a>",
					'STATS_REQUESTS_CLIENT_LAST_REQUEST'	=> empty($stats['last_request']) ? "-" : $stats['last_request'],
					'STATS_REQUESTS_CLIENT_USERAGENT'		=> empty($stats['client_useragent']) ? "-" : $stats['client_useragent'],
					'STATS_REQUESTS_CLIENT_PROXY_USERAGENT'	=> empty($stats['proxy_useragent']) ? "-" : $stats['proxy_useragent'],
					'STATS_REQUESTS_CLIENT_PROXY_IP'		=> empty($stats['proxy_ip']) ? "-" : "<a href=\"?cmd=nettools&amp;tpl=whois&amp;address=".$stats['proxy_ip']."\" title=\"".$_ARRAYLANG['TXT_SHOW_DETAILS']."\">".$stats['proxy_ip']."</a>",
					'STATS_REQUESTS_CLIENT_PROXY_HOST'		=> empty($stats['proxy_host']) ? "-" : "<a href=\"?cmd=nettools&amp;tpl=whois&amp;address=".$stats['proxy_host']."\" title=\"".$_ARRAYLANG['TXT_SHOW_DETAILS']."\">".$stats['proxy_host']."</a>",
					));
				$this->_objTpl->parse('stats_requests_today_clients');
				$this->_objTpl->hideBlock('stats_requests_today_clients_list_nodata');
				$rowClass++;
			}
    	} else {
    		$this->_objTpl->hideBlock('stats_requests_today_clients_list');
    		$this->_objTpl->touchBlock('stats_requests_today_clients_list_nodata');
    		$this->_objTpl->setVariable(array(
				'TXT_NO_DATA_AVAILABLE' => $_ARRAYLANG['TXT_NO_DATA_AVAILABLE']
			));
    	}
    }

    /**
    * Show requests
    *
    * Show the request statistics
    *
    * @access	private
    * @global	array	$_ARRAYLANG
    * @see	_showRequestsToday(), _showRequestsDays(), _showRequestsMonths(), _showRequestsYears(), _showRequestsToday()
    */
    function _showRequests(){
    	global $_ARRAYLANG;
    	$this->_objTpl->loadTemplateFile('module_stats_requests.html',true,true);
    	$this->pageTitle = $_ARRAYLANG['TXT_VISITORS_AND_PAGE_VIEWS'];

    	$this->_objTpl->setGlobalVariable(array(
    		'TXT_TODAY'					=> $_ARRAYLANG['TXT_TODAY'],
    		'TXT_DAILY_STATISTICS'		=> $_ARRAYLANG['TXT_DAILY_STATISTICS'],
    		'TXT_MONTHLY_STATISTICS'	=> $_ARRAYLANG['TXT_MONTHLY_STATISTICS'],
    		'TXT_ANNUAL_STATISTICS'		=> $_ARRAYLANG['TXT_ANNUAL_STATISTICS']
    	));

    	if(!isset($_GET['tpl'])){
    		$_GET['tpl'] = "";
    	}

    	switch ($_GET['tpl']) {
    		case 'today':
    			$this->_showRequestsToday();
    			break;

    		case 'days':
    			$this->_showRequestsDays();
    			break;

    		case 'months':
    			$this->_showRequestsMonths();
    			break;

    		case 'years':
    			$this->_showRequestsYears();
    			break;

    		default:
    			$this->_showRequestsToday();
    			break;
    	}

    	$this->_objTpl->parse('requests_block');
    }

    /**
    * Show requests today
    *
    * Show the page requests and visitors of today
    *
    * @access	private
    * @see	_initStatisticsToday()
    * @global	array	$_ARRAYLANG
    * @global	int	$_LANGID
    */
    function _showRequestsToday() {
		global $_ARRAYLANG, $_LANGID;

		// set variables
		$visitors = 0;
		$requests = 0;
		$rowClass = 0;

    	$this->_objTpl->addBlockfile('STATS_REQUESTS_CONTENT', 'requests_block', 'module_stats_requests_today.html');

    	// initialize the statistics
    	$this->_initStatisticsToday();

    	// set language variables
    	$this->_objTpl->setVariable(array(
    		'TXT_DETAILS'				=> $_ARRAYLANG['TXT_DETAILS'],
    		'TXT_PERIOD_OF_TIME'		=> $_ARRAYLANG['TXT_PERIOD_OF_TIME'],
    		'TXT_VISITORS'				=> $_ARRAYLANG['TXT_VISITORS'],
    		'TXT_PAGE_VIEWS'			=> $_ARRAYLANG['TXT_PAGE_VIEWS'],
    		'TXT_VIEW_DETAILS'			=> $_ARRAYLANG['TXT_VIEW_DETAILS']
    	));

    	// set statistic details
    	$rowClass = 1;
		for ($hour=0;$hour<=date('H');$hour++) {
			$pHour = str_pad($hour, 2, 0, STR_PAD_LEFT);
			if (isset($this->arrRequests[$pHour])) {
				$visitors = $this->arrRequests[$pHour]['visitors'];
				$requests = $this->arrRequests[$pHour]['requests'];
			} else {
				$visitors = 0;
				$requests = 0;
			}
    		$this->_objTpl->setVariable(array(
    			'STATS_REQUESTS_ROW_CLASS'	=> $rowClass%2 == 1 ? "row2" : "row1",
    			'STATS_REQUESTS_TIME'		=> sprintf("%02s:00",$hour).' - '.sprintf("%02s:00",$hour+1),
    			'STATS_REQUESTS_VISITORS'	=> empty($visitors) ? 0 : $visitors,
    			'STATS_REQUESTS_PAGE_VIEWS'	=> empty($requests) ? 0 : $requests
    		));
    		$this->_objTpl->parse('stats_requests_today');

    		$rowClass++;
		}

		// set total statistic details
	    $this->_objTpl->setVariable(array(
			'STATS_REQUESTS_ROW_CLASS'	=> $rowClass%2 == 1 ? "row2" : "row1",
			'STATS_REQUESTS_TIME'		=> '<br /><b>'.$_ARRAYLANG['TXT_TOTAL'].'</b>',
			'STATS_REQUESTS_VISITORS'	=> '<br /><b>'.$this->totalVisitors.'</b>',
			'STATS_REQUESTS_PAGE_VIEWS'	=> '<br /><b>'.$this->totalRequests.'</b>'
		));
		$this->_objTpl->parse('stats_requests_today');

		// set statistic graph
		if (count($this->arrRequests)>0) {
			$this->_objTpl->setVariable(array(
				'STATS_REQUESTS_GRAPH' => '<img style="border: 1px solid #000000;" src="'.ASCMS_PATH_OFFSET.'/core_modules/stats/graph.php?stats=requests_today" width="600" height="250" />'
			));
		} else {
			$this->_objTpl->setVariable(array(
				'STATS_REQUESTS_GRAPH' => $_ARRAYLANG['TXT_NO_DATA_AVAILABLE']
			));
		}

		$this->_objTpl->parse('requests_block');
    }

    /**
    * Show requests days
    *
    * Show the page requests and visitors of the days
    *
    * @access	private
    * @see	_initStatisticsDays()
    * @global	array	$_ARRAYLANG
    * @global	int	$_LANGID
    */
    function _showRequestsDays() {
    	global $_ARRAYLANG, $_LANGID;

		// set variables
		$visitors = 0;
		$requests = 0;
		$rowClass = 0;

		$this->_objTpl->addBlockfile('STATS_REQUESTS_CONTENT', 'requests_block', 'module_stats_requests_days.html');

    	// initialize the statistics
    	$this->_initStatisticsDays();

    	// set language variables
    	$this->_objTpl->setVariable(array(
    		'TXT_DETAILS'				=> $_ARRAYLANG['TXT_DETAILS'],
    		'TXT_WEEKDAY'				=> $_ARRAYLANG['TXT_WEEKDAY'],
    		'TXT_DATE'					=> $_ARRAYLANG['TXT_DATE'],
    		'TXT_VISITORS'				=> $_ARRAYLANG['TXT_VISITORS'],
    		'TXT_PAGE_VIEWS'			=> $_ARRAYLANG['TXT_PAGE_VIEWS'],
    		'TXT_VIEW_DETAILS'			=> $_ARRAYLANG['TXT_VIEW_DETAILS']
    	));

    	// set statistic details
    	$rowClass = 1;
    	$arrMonths = explode(',',$_ARRAYLANG['TXT_MONTH_ARRAY']);
    	$arrDays = explode(',',$_ARRAYLANG['TXT_DAY_ARRAY']);

		for ($day=1;$day<=date('j');$day++) {
			if (isset($this->arrRequests[$day])) {
				$visitors = $this->arrRequests[$day]['visitors'];
				$requests = $this->arrRequests[$day]['requests'];
			} else {
				$visitors = 0;
				$requests = 0;
			}

			$weekday = $arrDays[date('w',mktime(0,0,0,date('m'),$day,date('Y')))];
			if (date('w',mktime(0,0,0,date('m'),$day,date('Y'))) == 0) {
				$weekday = "<span style=\"color: #ff0000;\">".$weekday."</span>";
			}

    		$this->_objTpl->setVariable(array(
    			'STATS_REQUESTS_ROW_CLASS'	=> $rowClass%2 == 1 ? "row2" : "row1",
    			'STATS_REQUESTS_WEEKDAY'	=> $weekday,
    			'STATS_REQUESTS_DATE'		=> $day.'.&nbsp;'.$arrMonths[date('m')-1],
    			'STATS_REQUESTS_VISITORS'	=> empty($visitors) ? 0 : $visitors,
    			'STATS_REQUESTS_PAGE_VIEWS'	=> empty($requests) ? 0 : $requests
    		));
    		$this->_objTpl->parse('stats_requests_days');

    		$rowClass++;
		}

		// set total statistic details
	    $this->_objTpl->setVariable(array(
			'STATS_REQUESTS_ROW_CLASS'	=> $rowClass%2 == 1 ? "row2" : "row1",
			'STATS_REQUESTS_WEEKDAY'	=> '<br /><b>'.$_ARRAYLANG['TXT_TOTAL'].'</b>',
			'STATS_REQUESTS_DATE'		=> '&nbsp;',
			'STATS_REQUESTS_VISITORS'	=> '<br /><b>'.$this->totalVisitors.'</b>',
			'STATS_REQUESTS_PAGE_VIEWS'	=> '<br /><b>'.$this->totalRequests.'</b>'
		));
		$this->_objTpl->parse('stats_requests_days');

		// set statistic graph
		if (count($this->arrRequests)>0) {
			$this->_objTpl->setVariable(array(
				'STATS_REQUESTS_GRAPH' => '<img style="border: 1px solid #000000;" src="'.ASCMS_PATH_OFFSET.'/core_modules/stats/graph.php?stats=requests_days" width="600" height="250" />',
			));
		} else {
			$this->_objTpl->setVariable(array(
				'STATS_REQUESTS_GRAPH' => $_ARRAYLANG['TXT_NO_DATA_AVAILABLE']
			));
		}


		$this->_objTpl->parse('requests_block');
    }

    /**
    * Show requests months
    *
    * Show the page requests and visitors of the months
    *
    * @access	private
    * @see	_initStatisticsMonths()
    * @global	array	$_ARRAYLANG
    * @global	int	$_LANGID
    */
    function _showRequestsMonths() {
    	global $_ARRAYLANG, $_LANGID;

		// set variables
		$visitors = 0;
		$requests = 0;
		$rowClass = 0;

		$this->_objTpl->addBlockfile('STATS_REQUESTS_CONTENT', 'requests_block', 'module_stats_requests_months.html');

    	// initialize the statistics
    	$this->_initStatisticsMonths();

    	// set language variables
    	$this->_objTpl->setVariable(array(
    		'TXT_DETAILS'				=> $_ARRAYLANG['TXT_DETAILS'],
    		'TXT_MONTH'					=> $_ARRAYLANG['TXT_MONTH'],
    		'TXT_VISITORS'				=> $_ARRAYLANG['TXT_VISITORS'],
    		'TXT_PAGE_VIEWS'			=> $_ARRAYLANG['TXT_PAGE_VIEWS'],
    		'TXT_VIEW_DETAILS'			=> $_ARRAYLANG['TXT_VIEW_DETAILS']
    	));

    	// set statistic details
    	$rowClass = 1;
    	$arrMonths = explode(',',$_ARRAYLANG['TXT_MONTH_ARRAY']);
		for ($month=1;$month<=date('m');$month++) {
			if (isset($this->arrRequests[$month])) {
				$visitors = $this->arrRequests[$month]['visitors'];
				$requests = $this->arrRequests[$month]['requests'];
			} else {
				$visitors = 0;
				$requests = 0;
			}
    		$this->_objTpl->setVariable(array(
    			'STATS_REQUESTS_ROW_CLASS'	=> $rowClass%2 == 1 ? "row2" : "row1",
    			'STATS_REQUESTS_TIME'		=> $arrMonths[$month-1],
    			'STATS_REQUESTS_VISITORS'	=> empty($visitors) ? 0 : $visitors,
    			'STATS_REQUESTS_PAGE_VIEWS'	=> empty($requests) ? 0 : $requests
    		));
    		$this->_objTpl->parse('stats_requests_months');

    		$rowClass++;
		}

		// set total statistic details
	    $this->_objTpl->setVariable(array(
			'STATS_REQUESTS_ROW_CLASS'	=> $rowClass%2 == 1 ? "row2" : "row1",
			'STATS_REQUESTS_TIME'		=> '<br /><b>'.$_ARRAYLANG['TXT_TOTAL'].'</b>',
			'STATS_REQUESTS_VISITORS'	=> '<br /><b>'.$this->totalVisitors.'</b>',
			'STATS_REQUESTS_PAGE_VIEWS'	=> '<br /><b>'.$this->totalRequests.'</b>'
		));
		$this->_objTpl->parse('stats_requests_months');

		// set statistic graph
		if (count($this->arrRequests)>0) {
			$this->_objTpl->setVariable(array(
				'STATS_REQUESTS_GRAPH' => '<img style="border: 1px solid #000000;" src="'.ASCMS_PATH_OFFSET.'/core_modules/stats/graph.php?stats=requests_months" width="600" height="250" />',
			));
		} else {
			$this->_objTpl->setVariable(array(
				'STATS_REQUESTS_GRAPH' => $_ARRAYLANG['TXT_NO_DATA_AVAILABLE']
			));
		}

		$this->_objTpl->parse('requests_block');
    }

    /**
    * Show requests years
    *
    * Show the page requests and visitors of the years
    *
    * @access	private
    * @see	_initStatisticsYears()
    * @global	array	$_ARRAYLANG
    * @global	int	$_LANGID
    */
    function _showRequestsYears() {
    	global $_ARRAYLANG, $_LANGID;

		// set variables
		$visitors = 0;
		$requests = 0;
		$rowClass = 0;

		$this->_objTpl->addBlockfile('STATS_REQUESTS_CONTENT', 'requests_block', 'module_stats_requests_years.html');

    	// initialize the statistics
    	$this->_initStatisticsYears();

    	// set language variables
    	$this->_objTpl->setVariable(array(
    		'TXT_DETAILS'				=> $_ARRAYLANG['TXT_DETAILS'],
    		'TXT_YEAR'					=> $_ARRAYLANG['TXT_YEAR'],
    		'TXT_VISITORS'				=> $_ARRAYLANG['TXT_VISITORS'],
    		'TXT_PAGE_VIEWS'			=> $_ARRAYLANG['TXT_PAGE_VIEWS'],
    		'TXT_VIEW_DETAILS'			=> $_ARRAYLANG['TXT_VIEW_DETAILS']
    	));

    	// set statistic details
    	$rowClass = 1;
    	if (count($this->arrRequests)>0) {
			for ($year=key($this->arrRequests);$year<=date('Y');$year++) {
				if (isset($this->arrRequests[$year])) {
					$visitors = $this->arrRequests[$year]['visitors'];
					$requests = $this->arrRequests[$year]['requests'];
				} else {
					$visitors = 0;
					$requests = 0;
				}
	    		$this->_objTpl->setVariable(array(
	    			'STATS_REQUESTS_ROW_CLASS'	=> $rowClass%2 == 1 ? "row2" : "row1",
	    			'STATS_REQUESTS_TIME'		=> $year,
	    			'STATS_REQUESTS_VISITORS'	=> empty($visitors) ? 0 : $visitors,
	    			'STATS_REQUESTS_PAGE_VIEWS'	=> empty($requests) ? 0 : $requests
	    		));
	    		$this->_objTpl->parse('stats_requests_years');

	    		$rowClass++;
			}

			// set total statistic details
		    $this->_objTpl->setVariable(array(
				'STATS_REQUESTS_ROW_CLASS'	=> $rowClass%2 == 1 ? "row2" : "row1",
				'STATS_REQUESTS_TIME'		=> '<br /><b>'.$_ARRAYLANG['TXT_TOTAL'].'</b>',
				'STATS_REQUESTS_VISITORS'	=> '<br /><b>'.$this->totalVisitors.'</b>',
				'STATS_REQUESTS_PAGE_VIEWS'	=> '<br /><b>'.$this->totalRequests.'</b>'
			));
			$this->_objTpl->parse('stats_requests_years');
    	}

		// set statistic graph
		if (count($this->arrRequests)>0) {
			$this->_objTpl->setVariable(array(
				'STATS_REQUESTS_GRAPH' => '<img style="border: 1px solid #000000;" src="'.ASCMS_PATH_OFFSET.'/core_modules/stats/graph.php?stats=requests_years" width="600" height="250" />',
			));
		} else {
			$this->_objTpl->setVariable(array(
				'STATS_REQUESTS_GRAPH' => $_ARRAYLANG['TXT_NO_DATA_AVAILABLE']
			));
		}

		$this->_objTpl->parse('requests_block');
    }

    /**
    * Show referer
    *
    * Show the last referers and the most referers
    *
    * @access	private
    * @global	array	$_ARRAYLANG;
    * @see	_initReferer()
    */
    function _showReferer() {
    	global $_ARRAYLANG;
    	$this->_objTpl->loadTemplateFile('module_stats_referer.html',true,true);
    	$this->pageTitle = $_ARRAYLANG['TXT_REFERER'];

    	$this->_initReferer();

    	// set language variables
    	$this->_objTpl->setVariable(array(
    		'TXT_THE_LAST_REFERER'	=> $_ARRAYLANG['TXT_THE_LAST_REFERER'],
    		'TXT_TOP_REFERER'		=> $_ARRAYLANG['TXT_TOP_REFERER'],
    		'TXT_TIME'				=> $_ARRAYLANG['TXT_TIME'],
    		'TXT_REFERER'			=> $_ARRAYLANG['TXT_REFERER'],
    		'TXT_NO_DATA_AVAILABLE'	=> $_ARRAYLANG['TXT_NO_DATA_AVAILABLE'],
    		'TXT_NUMBER'			=> $_ARRAYLANG['TXT_NUMBER']
    	));

    	if (count($this->arrLastReferer)>0) {
    		$rowClass = 0;
    		foreach ($this->arrLastReferer as $arrReferer) {
	    		$this->_objTpl->setVariable(array(
	    			'STATS_REFERER_ROW_CLASS'	=> $rowClass % 2 == 0 ? "row2" : "row1",
	    			'STATS_REFERER_TIME'		=> date('d-m-Y H:i:s', $arrReferer['timestamp']),
	    			'STATS_REFERER_URI'			=> "<a href=\"".$arrReferer['uri']."\" alt=\"".$arrReferer['uri']."\" title=\"".$arrReferer['uri']."\" target=\"_blank\">".$arrReferer['uri']."</a>"
	    		));
	    		$this->_objTpl->parse('stats_referer_list');
	    		$rowClass++;
    		}
    		$this->_objTpl->hideBlock('stats_referer_nodata');
    	} else {
    		$this->_objTpl->hideBlock('stats_referer');
    		$this->_objTpl->touchBlock('stats_referer_nodata');
    	}

    	if (count($this->arrTopReferer)>0) {
    		$rowClass = 0;
    		foreach ($this->arrTopReferer as $arrReferer) {
	    		$this->_objTpl->setVariable(array(
	    			'STATS_REFERER_TOP_ROW_CLASS'	=> $rowClass % 2 == 0 ? "row2" : "row1",
	    			'STATS_REFERER_TOP_COUNT'		=> $arrReferer['count'],
	    			'STATS_REFERER_TOP_URI'			=> "<a href=\"".$arrReferer['uri']."\" alt=\"".$arrReferer['uri']."\" title=\"".$arrReferer['uri']."\" target=\"_blank\">".$arrReferer['uri']."</a>"
	    		));
	    		$this->_objTpl->parse('stats_referer_top_list');
	    		$rowClass++;
    		}
    		$this->_objTpl->hideBlock('stats_referer_top_nodata');
    	} else {
    		$this->_objTpl->hideBlock('stats_referer_top');
    		$this->_objTpl->touchBlock('stats_referer_top_nodata');
    	}
    }


	/**
	* Show most viewed pages
	*
	* Show a list of the most viewed pages
	*
	* @access	private
	* @global	array	$_ARRAYLANG
	* @see	_initMostViewedPagesStatistics()
	*/
    function _showMostViewedPages() {
    	global $_ARRAYLANG;
    	$i = 0;

    	$this->_objTpl->loadTemplateFile('module_stats_mvp.html',true,true);
    	$this->pageTitle = $_ARRAYLANG['TXT_MOST_POPULAR_PAGES'];

    	$this->_initMostViewedPages();

    	// set language variables
    	$this->_objTpl->setVariable(array(
    		'TXT_MOST_POPULAR_PAGES'	=> $_ARRAYLANG['TXT_MOST_POPULAR_PAGES'],
    		'TXT_PAGE'					=> $_ARRAYLANG['TXT_PAGE'],
    		'TXT_REQUESTS'				=> $_ARRAYLANG['TXT_REQUESTS'],
    		'TXT_LAST_REQUEST'			=> $_ARRAYLANG['TXT_LAST_REQUEST']
    	));

    	if (count($this->arrMostViewedPages)>0) {
	    	foreach ($this->arrMostViewedPages as $stats) {
	    		$this->_objTpl->setVariable(array(
	    			'STATS_REQUESTS_PAGE'	=> '<a href="'.ASCMS_PATH_OFFSET.$stats['page'].'" target="_blank" alt="'.$stats['title'].'" title="'.$stats['title'].'">'.$stats['title'].'</a>&nbsp;('.$stats['page'].')',
	    			'STATS_REQUESTS_REQUESTS'	=> $this->_makePercentBar(300,10,($stats['requests'] * 100) / $this->mostViewedPagesSum, 100 ,1,'').'&nbsp;'.round(($stats['requests'] * 100) / $this->mostViewedPagesSum,2).'%'.' ('.$stats['requests'].')',
	    			'STATS_REQUESTS_LAST_REQUEST'	=> $stats['last_request'],
	    			'STATS_REQUESTS_ROW_CLASS'	=> (($i % 2) == 0) ? "row2" : "row1"
	    		));
	    		$this->_objTpl->parse('stats_requests_mvp');
	    		$this->_objTpl->hideBlock('stats_requests_nodata');
	    		$i++;
	    	}
    	} else {
			$this->_objTpl->hideBlock('stats_requests');
    		$this->_objTpl->setVariable(array(
    			'TXT_NO_DATA_AVAILABLE'				=> $_ARRAYLANG['TXT_NO_DATA_AVAILABLE']
    		));
    	}
    }

    /**
    * Show spiders
    *
    * Show a top list of the spiders that have indexed this webpage and a list of all websites that have been indexed by a spider
    *
    * @access	private
    * @global	array	$_ARRAYLANG
    * @see	_initSpiders()
    */
    function _showSpiders() {
    	global $_ARRAYLANG;
    	$i = 0;

    	$this->_objTpl->loadTemplateFile('module_stats_spiders.html',true,true);
    	$this->pageTitle = $_ARRAYLANG['TXT_SEARCH_ENGINES'];

    	$this->_initSpiders();

    	// set language variables
    	$this->_objTpl->setVariable(array(
    		'TXT_SEARCH_ENGINES'	=> $_ARRAYLANG['TXT_SEARCH_ENGINES'],
    		'TXT_SEARCH_ENGINE'		=> $_ARRAYLANG['TXT_SEARCH_ENGINE'],
    		'TXT_INDEXATIONS'		=> $_ARRAYLANG['TXT_INDEXATIONS'],
    		'TXT_LAST_INDEXATION'	=> $_ARRAYLANG['TXT_LAST_INDEXATION'],
    		'TXT_INDEXED_PAGES'		=> $_ARRAYLANG['TXT_INDEXED_PAGES'],
    		'TXT_PAGE'				=> $_ARRAYLANG['TXT_PAGE'],
    		'TXT_LAST_TIME_INDEXED'	=> $_ARRAYLANG['TXT_LAST_TIME_INDEXED'],
    		'TXT_INDEXED_BY'		=> $_ARRAYLANG['TXT_INDEXED_BY']
		));

		// set spiders top list
		if (count($this->arrSpiders)>0) {
			foreach ($this->arrSpiders as $arrSpider) {
				$this->_objTpl->setVariable(array(
					'STATS_SPIDERS_ROW_CLASS'	=> (($i % 2) == 0) ? "row2" : "row1",
					'STATS_SPIDERS_NAME'		=> $arrSpider['name'],
					'STATS_SPIDERS_COUNT'		=> $arrSpider['count'],
					'STATS_SPIDERS_LAST_INDEXATION'	=> $arrSpider['last_indexed']
				));
				$this->_objTpl->parse('stats_spiders_list');
				$this->_objTpl->hideBlock('stats_spiders_nodata');
				$i++;
			}
		} else {
			$this->_objTpl->hideBlock('stats_spiders');
    		$this->_objTpl->setVariable(array(
    			'TXT_NO_DATA_AVAILABLE'				=> $_ARRAYLANG['TXT_NO_DATA_AVAILABLE']
    		));
		}

		// set indexed page list
		$i = 0;
		if (count($this->arrIndexedPages)>0) {
			foreach ($this->arrIndexedPages as $values) {
				$this->_objTpl->setVariable(array(
					'STATS_SPIDERS_ROW_CLASS'		=> (($i % 2) == 0) ? "row2" : "row1",
					'STATS_SPIDERS_PAGE'			=> '<a href="'.ASCMS_PATH_OFFSET.$values['page'].'" target="_blank" alt="'.$values['title'].'" title="'.$values['title'].'">'.$values['title'].'&nbsp;'.$values['page'].'</a>',
					'STATS_SPIDERS_LAST_INDEXED'	=> $values['last_indexed'],
					'STATS_SPIDERS_INDEXED_BY'		=> $values['spider_useragent'].(!empty($values['spider_ip']) ? ' ('.$values['spider_ip'].(!empty($values['spider_host']) ? ' '.$values['spider_host'].' )' : ' )') : '')
				));
				$this->_objTpl->parse('stats_spiders_indexed_pages');
				$this->_objTpl->hideBlock('stats_spiders_indexed_nodata');
				$i++;
			}
		} else {
			$this->_objTpl->hideBlock('stats_spiders_indexed');
			$this->_objTpl->touchBlock('stats_spiders_indexed_nodata');
    		$this->_objTpl->setVariable(array(
    			'TXT_NO_DATA_AVAILABLE'				=> $_ARRAYLANG['TXT_NO_DATA_AVAILABLE']
    		));
		}
    }

    /**
    * Show search terms
    *
    * Show the statistic of the search queries
    *
    * @access	private
    * @glocal	array	$_ARRAYLANG
    * @see	_initSearchTerms()
    */
    function _showSearchTerms() {
    	global $_ARRAYLANG;

    	$rowClass = 0;

    	$this->_objTpl->loadTemplateFile('module_stats_search.html',true,true);
    	$this->pageTitle = $_ARRAYLANG['TXT_SEARCH_TERMS'];

    	$this->_initSearchTerms();

    	// set language variables
    	$this->_objTpl->setVariable(array(
    		'TXT_INTERNAL'		=> $_ARRAYLANG['TXT_INTERNAL'],
    		'TXT_EXTERNAL'		=> $_ARRAYLANG['TXT_EXTERNAL'],
    		'TXT_SUMMARY'		=> $_ARRAYLANG['TXT_SUMMARY'],
    		'TXT_INTERNAL_SEARCH_QUERIES'	=> $_ARRAYLANG['TXT_INTERNAL_SEARCH_QUERIES'],
    		'TXT_EXTERNAL_SEARCH_QUERIES'	=> $_ARRAYLANG['TXT_EXTERNAL_SEARCH_QUERIES'],
    		'TXT_SEARCH_TERMS'	=> $_ARRAYLANG['TXT_SEARCH_TERMS'],
    		'TXT_SEARCH_TERM'	=> $_ARRAYLANG['TXT_SEARCH_TERM'],
    		'TXT_FREQUENCY'		=> $_ARRAYLANG['TXT_FREQUENCY']
    	));

    	if (isset($this->arrSearchTerms['internal']) && count($this->arrSearchTerms['internal'])>0) {
    		$rowClass = 0;
	    	foreach ($this->arrSearchTerms['internal'] as $arrSearchTerm) {
	    		$this->_objTpl->setVariable(array(
	    			'STATS_REQUESTS_ROW_CLASS'	=> $rowClass % 2 == 0 ? "row2" : "row1",
	    			'STATS_SEARCH_TERM'			=> htmlentities($arrSearchTerm['name'], ENT_QUOTES, CONTREXX_CHARSET),
	    			'STATS_SEARCH_FREQUENCY'	=> $arrSearchTerm['count']
	    		));
	    		$this->_objTpl->parse('stats_search_internal_list');
	    		$rowClass++;
	    	}
	    	$this->_objTpl->hideBlock('stats_search_internal_nodata');
    	} else {
    		$this->_objTpl->hideBlock('stats_search_internal');
    		$this->_objTpl->setVariable(array(
    			'TXT_NO_DATA_AVAILABLE'				=> $_ARRAYLANG['TXT_NO_DATA_AVAILABLE']
    		));
    	}

    	if (isset($this->arrSearchTerms['external']) && count($this->arrSearchTerms['external'])>0) {
    		$rowClass = 0;
	    	foreach ($this->arrSearchTerms['external'] as $arrSearchTerm) {
	    		$this->_objTpl->setVariable(array(
	    			'STATS_REQUESTS_ROW_CLASS'	=> $rowClass % 2 == 0 ? "row2" : "row1",
	    			'STATS_SEARCH_TERM'			=> htmlentities($arrSearchTerm['name'], ENT_QUOTES, CONTREXX_CHARSET),
	    			'STATS_SEARCH_FREQUENCY'	=> $arrSearchTerm['count']
	    		));
	    		$this->_objTpl->parse('stats_search_external_list');
	    		$rowClass++;
	    	}
	    	$this->_objTpl->hideBlock('stats_search_external_nodata');
    	} else {
    		$this->_objTpl->hideBlock('stats_search_external');
    		$this->_objTpl->setVariable(array(
    			'TXT_NO_DATA_AVAILABLE'				=> $_ARRAYLANG['TXT_NO_DATA_AVAILABLE']
    		));
    	}

    	if (isset($this->arrSearchTerms['summary']) && count($this->arrSearchTerms['summary'])>0) {
    		$rowClass = 0;
	    	foreach ($this->arrSearchTerms['summary'] as $arrSearchTerm) {
	    		$this->_objTpl->setVariable(array(
	    			'STATS_REQUESTS_ROW_CLASS'	=> $rowClass % 2 == 0 ? "row2" : "row1",
	    			'STATS_SEARCH_TERM'			=> htmlentities($arrSearchTerm['name'], ENT_QUOTES, CONTREXX_CHARSET),
	    			'STATS_SEARCH_FREQUENCY'	=> $arrSearchTerm['count']
	    		));
	    		$this->_objTpl->parse('stats_search_summary_list');
	    		$rowClass++;
	    	}
	    	$this->_objTpl->hideBlock('stats_search_summary_nodata');
    	} else {
    		$this->_objTpl->hideBlock('stats_search_summary');
    		$this->_objTpl->setVariable(array(
    			'TXT_NO_DATA_AVAILABLE'				=> $_ARRAYLANG['TXT_NO_DATA_AVAILABLE']
    		));
    	}
    }

    /**
    * Show clients
    *
    * Show the statistics of the clients browser, operating system, javascript support, screen resolution and the colour depth
    *
    * @access	private
    * @global	array	$_ARRAYLANG
    * @see	_initClientStatistics
    */
    function _showClients(){
    	global $_ARRAYLANG;

    	$this->_objTpl->loadTemplateFile('module_stats_clients.html',true,true);
    	$this->pageTitle = $_ARRAYLANG['TXT_USER_INFORMATION'];

    	// set language variables
    	$this->_objTpl->setVariable(array(
			'TXT_BROWSERS' 						=> $_ARRAYLANG['TXT_BROWSERS'],
			'TXT_JAVASCRIPT_SUPPORT'			=> $_ARRAYLANG['TXT_JAVASCRIPT_SUPPORT'],
			'TXT_OPERATING_SYSTEMS'				=> $_ARRAYLANG['TXT_OPERATING_SYSTEMS'],
			'TXT_SCREEN_RESOLUTION'				=> $_ARRAYLANG['TXT_SCREEN_RESOLUTION'],
			'TXT_COLOUR_DEPTH'					=> $_ARRAYLANG['TXT_COLOUR_DEPTH'],
			'TXT_CLIENT_SUPPORTS_JAVASCRIPT'	=> $_ARRAYLANG['TXT_CLIENT_SUPPORTS_JAVASCRIPT'],
			'TXT_NUMBER'						=> $_ARRAYLANG['TXT_NUMBER'],
			'TXT_YES'							=> $_ARRAYLANG['TXT_YES'],
			'TXT_NO'							=> $_ARRAYLANG['TXT_NO'],
			'TXT_DOMAINS'						=> $_ARRAYLANG['TXT_DOMAINS'],
			'TXT_DOMAIN'	=> $_ARRAYLANG['TXT_DOMAIN'],
			'TXT_COUNTRIES_OF_ORIGIN'	=> $_ARRAYLANG['TXT_COUNTRIES_OF_ORIGIN'],
			'TXT_COUNTRY_OF_ORIGIN'	=> $_ARRAYLANG['TXT_COUNTRY_OF_ORIGIN'],
			'TXT_NO_DATA_AVAILABLE'				=> $_ARRAYLANG['TXT_NO_DATA_AVAILABLE']
		));

		$this->_initClientStatistics();

		// set browser statistics
		if ($this->browserSum>0) {
			$rowClass = 0;
			foreach ($this->arrBrowsers as $name => $count) {
				if ($name == "unknown") {
					$name = $_ARRAYLANG['TXT_UNKNOWN'];
				}
				$this->_objTpl->setVariable(array(
					'STATS_CLIENTS_BROWSER_ROW_CLASS'	=> $rowClass % 2 == 0 ? "row2" : "row1",
					'STATS_CLIENTS_BROWSER_NAME'		=> $name,
					'STATS_CLIENTS_BROWSER_COUNT'		=> $this->_makePercentBar(200,10,100/$this->browserSum*$count,100,1,$name).' '.round(100/$this->browserSum*$count,2).'% ('.$count.')'
				));
				$this->_objTpl->parse('stats_clients_browsers');
				$rowClass++;
			}
			$this->_objTpl->hideBlock('stats_clients_browsers_nodata');
		} else {
			$this->_objTpl->hideBlock('stats_clients_browsers');
		}

		// set javascript statistics
		if ($this->supportJavaScriptSum>0) {
			$this->_objTpl->setVariable(array(
				'STATS_CLIENTS_JAVASCRIPT_SUPPORT'		=> $this->_makePercentBar(200,10,100/$this->supportJavaScriptSum*$this->arrSupportJavaScript[1],100,1,'Javascript Support unterst�tzt').' '.round(100/$this->supportJavaScriptSum*$this->arrSupportJavaScript[1],2).'% ('.$this->arrSupportJavaScript[1].')',
				'STATS_CLIENTS_JAVASCRIPT_NO_SUPPORT'	=> $this->_makePercentBar(200,10,100/$this->supportJavaScriptSum*$this->arrSupportJavaScript[0],100,1,'Javascript wird nicht unters�tzt').' '.round(100/$this->supportJavaScriptSum*$this->arrSupportJavaScript[0],2).'% ('.$this->arrSupportJavaScript[0].')'
			));
			$this->_objTpl->hideBlock('stats_clients_javascript_nodata');
		} else {
			$this->_objTpl->hideBlock('stats_clients_javascript');
			$this->_objTpl->touchBlock('stats_clients_javascript_nodata');
		}

    	// set operating system statistics
    	if ($this->operatingSystemsSum>0) {
    		$rowClass = 0;
    		foreach ($this->arrOperatingSystems as $name => $count) {
				if ($name == "unknown") {
					$name = $_ARRAYLANG['TXT_UNKNOWN'];
				}

				$this->_objTpl->setVariable(array(
					'STATS_CLIENTS_OS_ROW_CLASS'	=> $rowClass % 2 == 0 ? "row2" : "row1",
					'STATS_CLIENTS_OS_NAME'			=> $name,
					'STATS_CLIENTS_OS_COUNT'		=> $this->_makePercentBar(200,10,100/$this->operatingSystemsSum*$count,100,1,$name).' '.round(100/$this->operatingSystemsSum*$count,2).'% ('.$count.')'
				));
				$this->_objTpl->parse('stats_clients_os');
				$rowClass++;
    		}
    		$this->_objTpl->hideBlock('stats_clients_os_nodata');
    	} else {
    		$this->_objTpl->hideBlock('stats_clients_os');
    		$this->_objTpl->touchBlock('stats_clients_os_nodata');
    	}

    	// set screen resolution statistics
    	if ($this->screenResolutionSum>0) {
    		$rowClass = 0;
    		foreach ($this->arrScreenResolutions as $resolution => $count) {
    			$this->_objTpl->setVariable(array(
    				'STATS_CLIENTS_RESOLUTION_ROW_CLASS'	=> $rowClass % 2 == 0 ? "row2" : "row1",
    				'STATS_CLIENTS_RESOLUTION_NAME'	=> $resolution,
    				'STATS_CLIENTS_RESOLUTION_COUNT'		=> $this->_makePercentBar(200,10,100/$this->screenResolutionSum*$count,100,1,$resolution).' '.round(100/$this->screenResolutionSum*$count,2).'% ('.$count.')'
    			));
    			$this->_objTpl->parse('stats_clients_resolution');
    			$rowClass++;
    		}
    		$this->_objTpl->hideBlock('stats_clients_resolution_nodata');
    	} else {
    		$this->_objTpl->hideBlock('stats_clients_resolution');
    		$this->_objTpl->touchBlock('stats_clients_resolution_nodata');
    	}

    	// set colour depth statistics
    	if ($this->colourDepthSum>0) {
    		$rowClass = 0;
    		foreach ($this->arrColourDepths as $depth => $count) {
    			$this->_objTpl->setVariable(array(
    				'STATS_CLIENTS_COLOUR_ROW_CLASS'	=> $rowClass % 2 == 0 ? "row2" : "row1",
    				'STATS_CLIENTS_COLOUR_NAME'			=> $depth.' '.$_ARRAYLANG['TXT_BIT'].(array_key_exists($depth,$this->arrColourDefinitions) ? " (".$_ARRAYLANG[$this->arrColourDefinitions[$depth]].")" : ""),
    				'STATS_CLIENTS_COLOUR_COUNT'		=> $this->_makePercentBar(200,10,100/$this->colourDepthSum*$count,100,1,$depth.' '.$_ARRAYLANG['TXT_BIT']).' '.round(100/$this->colourDepthSum*$count,2).'% ('.$count.')'
    			));
    			$this->_objTpl->parse('stats_clients_colour');
    			$rowClass++;
    		}
    		$this->_objTpl->hideBlock('stats_clients_colour_nodata');
    	} else {
    		$this->_objTpl->hideBlock('stats_clients_colour');
    		$this->_objTpl->touchBlock('stats_clients_colour_nodata');
    	}

    	// set hostnames statistics
    	if (count($this->arrHostnames)>0) {
    		$rowClass = 0;

    		foreach ($this->arrHostnames as $hostname => $count) {
				$this->_objTpl->setVariable(array(
					'STATS_CLIENTS_HOSTNAME_ROW_CLASS'	=> $rowClass % 2 == 0 ? "row2" : "row1",
					'STATS_CLIENTS_HOSTNAME'			=> $hostname,
					'STATS_CLIENTS_HOSTNAME_COUNT'		=> $this->_makePercentBar(200,10,100/$this->hostnamesSum*$count,100,1,$hostname).' '.round(100/$this->hostnamesSum*$count,2).'% ('.$count.')'
				));
				$this->_objTpl->parse('stats_clients_hostnames_list');
				$rowClass++;
    		}

    		$this->_objTpl->hideBlock('stats_clients_hostnames_nodata');
    	} else {
    		$this->_objTpl->hideBlock('stats_clients_hostnames');
			$this->_objTpl->touchBlock('stats_clients_hostnames_nodata');
    	}

    	// set countries of origin statistics
    	if (count($this->arrCountries)>0) {
    		$rowClass = 0;

			// get country names from xml file
        	$xmlCountryFilePath  = ASCMS_CORE_MODULE_PATH.'/stats/lib/countries.xml';
			$xml_parser = xml_parser_create();
			xml_set_object($xml_parser,$this);
			xml_set_element_handler($xml_parser,"_xmlCountryStartTag","_xmlCountryEndTag");
			xml_parse($xml_parser,file_get_contents($xmlCountryFilePath));

    		foreach ($this->arrCountries as $countryCode => $count) {
    			$country = isset($this->arrCountryNames[$countryCode]) ? $this->arrCountryNames[$countryCode] : strtoupper($countryCode);
    			if (file_exists(ASCMS_CORE_MODULE_PATH.'/stats/flags/'.$countryCode.'.gif')) {
    				$flag = $countryCode;
    			} else {
    				$flag = 'other';
    			}
				$this->_objTpl->setVariable(array(
					'STATS_CLIENTS_COUNTRY_ROW_CLASS'	=> $rowClass % 2 == 0 ? "row2" : "row1",
					'STATS_CLIENTS_COUNTRY'				=> "<img src=\"".ASCMS_CORE_MODULE_WEB_PATH."/stats/flags/".$flag.".gif\" style=\"width:18px;height:12px;\">&nbsp;".$country,
					'STATS_CLIENTS_COUNTRY_COUNT'		=> $this->_makePercentBar(200,10,100/$this->countriesSum*$count,100,1,$country).' '.round(100/$this->countriesSum*$count,2).'% ('.$count.')'
				));
				$this->_objTpl->parse('stats_clients_countries_list');
				$rowClass++;
    		}

    		$this->_objTpl->hideBlock('stats_clients_countries_nodata');
    	} else {
    		$this->_objTpl->hideBlock('stats_clients_countries');
			$this->_objTpl->touchBlock('stats_clients_countries_nodata');
    	}
    }

    /**
    * start element handler of the xml country parser
    *
    * @access	private
    */
	function _xmlCountryStartTag($parser,$name,$attrs){
		if($name == "COUNTRY"){
			$this->arrCountryNames[$attrs['CODE']] = $attrs['NAME'];
		}
	}

    /**
    * end element handler of the xml country parser
    *
    * @access	private
    */
	function _xmlCountryEndTag($parser,$name){
	}

	/**
	* Show settings
	*
	* Show the settings page
	*
	* @access	private
	* @global	array	$_ARRAYLANG
	* @see	_saveSettings();
	*/
    function _showSettings() {
    	global $_ARRAYLANG;

    	if (isset($_POST['save_stats_settings']) && !empty($_POST['save_stats_settings'])) {
    		$this->strErrMessage .= $this->_saveSettings();
    	}
    	if (isset($_POST['delete_statistics']) && !empty($_POST['delete_statistics'])) {
    		$this->strOkMessage .= $this->_deleteStatistics();
    	}

    	$this->_objTpl->loadTemplateFile('module_stats_settings.html',true,true);

    	// set language variables
    	$this->_objTpl->setVariable(array(
    		'TXT_SETTINGS'					=> $_ARRAYLANG['TXT_SETTINGS'],
    		'TXT_MAKE_STATISTICS'			=> $_ARRAYLANG['TXT_MAKE_STATISTICS'],
    		'TXT_REFERER'					=> $_ARRAYLANG['TXT_REFERER'],
    		'TXT_DOMAIN'					=> $_ARRAYLANG['TXT_DOMAIN'],
    		'TXT_COUNTRIES_OF_ORIGIN'		=> $_ARRAYLANG['TXT_COUNTRIES_OF_ORIGIN'],
    		'TXT_BROWSERS'					=> $_ARRAYLANG['TXT_BROWSERS'],
    		'TXT_OPERATING_SYSTEMS'			=> $_ARRAYLANG['TXT_OPERATING_SYSTEMS'],
    		'TXT_SEARCH_ENGINES'			=> $_ARRAYLANG['TXT_SEARCH_ENGINES'],
    		'TXT_SEARCH_TERMS'				=> $_ARRAYLANG['TXT_SEARCH_TERMS'],
    		'TXT_SCREEN_RESOLUTION'			=> $_ARRAYLANG['TXT_SCREEN_RESOLUTION'],
    		'TXT_COLOUR_DEPTH'				=> $_ARRAYLANG['TXT_COLOUR_DEPTH'],
    		'TXT_JAVASCRIPT_SUPPORT'		=> $_ARRAYLANG['TXT_JAVASCRIPT_SUPPORT'],
    		'TXT_REMOVE_REQUESTS'			=> $_ARRAYLANG['TXT_REMOVE_REQUESTS'],
    		'TXT_REMOVE_REQUESTS_INTERVAL'	=> $_ARRAYLANG['TXT_REMOVE_REQUESTS_INTERVAL'],
    		'TXT_STATS_COUNT_VISIOTR_NUMBER'	=> $_ARRAYLANG['TXT_STATS_COUNT_VISIOTR_NUMBER'],
    		'TXT_ONLINE_TIMEOUT'			=> $_ARRAYLANG['TXT_ONLINE_TIMEOUT'],
    		'TXT_ONLINE_TIMEOUT_INTERVAL'	=> $_ARRAYLANG['TXT_ONLINE_TIMEOUT_INTERVAL'],
    		'TXT_RELOAD_BLOCK_TIME'			=> $_ARRAYLANG['TXT_RELOAD_BLOCK_TIME'],
 			'TXT_SHOW_LIMIT'				=> $_ARRAYLANG['TXT_SHOW_LIMIT'],
 			'TXT_SHOW_LIMIT_VISITOR_DETAILS'	=> $_ARRAYLANG['TXT_SHOW_LIMIT_VISITOR_DETAILS'],
 			'TXT_STORE'						=> $_ARRAYLANG['TXT_STORE'],
 			'TXT_SECONDS'					=> $_ARRAYLANG['TXT_SECONDS'],
 			'TXT_RESET_STATISTIC'				=> $_ARRAYLANG['TXT_RESET_STATISTIC'],
    		'TXT_SELECT_STATISTICS_TO_RESET'	=> $_ARRAYLANG['TXT_SELECT_STATISTICS_TO_RESET'],
    		'TXT_VISITORS_AND_PAGE_VIEWS'		=> $_ARRAYLANG['TXT_VISITORS_AND_PAGE_VIEWS'],
    		'TXT_VISITOR_DETAIL_FROM_TODAY'		=> $_ARRAYLANG['TXT_VISITOR_DETAIL_FROM_TODAY'],
    		'TXT_MOST_POPULAR_PAGES'			=> $_ARRAYLANG['TXT_MOST_POPULAR_PAGES'],
    		'TXT_INDEXED_PAGES'					=> $_ARRAYLANG['TXT_INDEXED_PAGES'],
    		'TXT_MARKED'						=> $_ARRAYLANG['TXT_MARKED'],
    		'TXT_SELECT_ALL'					=> $_ARRAYLANG['TXT_SELECT_ALL'],
    		'TXT_REMOVE_SELECTION'				=> $_ARRAYLANG['TXT_REMOVE_SELECTION']
    	));

    	$this->_objTpl->setVariable(array(
    		'STATS_SETTINGS_MAKE_STATISTICS'	=> $this->arrConfig['make_statistics']['status'] ? "checked=\"checked\"" : "",
    		'STATS_SETTINGS_STATUS'				=> $this->arrConfig['make_statistics']['status'] ? "block" : "none",
    		'STATS_SETTINGS_COUNT_REFERER'		=> $this->arrConfig['count_referer']['status'] ? "checked=\"checked\"" : "",
    		'STATS_SETTINGS_COUNT_HOSTNAME'		=> $this->arrConfig['count_hostname']['status'] ? "checked=\"checked\"" : "",
    		'STATS_SETTINGS_COUNT_COUNTRY'		=> $this->arrConfig['count_country']['status'] ? "checked=\"checked\"" : "",
    		'STATS_SETTINGS_COUNT_BROWSER'		=> $this->arrConfig['count_browser']['status'] ? "checked=\"checked\"" : "",
    		'STATS_SETTINGS_COUNT_OS'			=> $this->arrConfig['count_operating_system']['status'] ? "checked=\"checked\"" : "",
    		'STATS_SETTINGS_COUNT_SPIDERS'		=> $this->arrConfig['count_spiders']['status'] ? "checked=\"checked\"" : "",
    		'STATS_SETTINGS_COUNT_SEARCH_TERMS'	=> $this->arrConfig['count_search_terms']['status'] ? "checked=\"checked\"" : "",
    		'STATS_SETTINGS_COUNT_RESOLUTION'	=> $this->arrConfig['count_screen_resolution']['status'] ? "checked=\"checked\"" : "",
    		'STATS_SETTINGS_COUNT_COLOUR'		=> $this->arrConfig['count_colour_depth']['status'] ? "checked=\"checked\"" : "",
    		'STATS_SETTINGS_COUNT_JAVASCRIPT'	=> $this->arrConfig['count_javascript']['status'] ? "checked=\"checked\"" : "",

    		'STATS_SETTINGS_REMOVE_REQUESTS'	=> $this->arrConfig['remove_requests']['value'],
    		'STATS_SETTINGS_REMOVE_REQUESTS_STATUS' => $this->arrConfig['remove_requests']['status'] ? "block" : "none",
    		'STATS_SETTINGS_REMOVE_REQUESTS_CHECKED' => $this->arrConfig['remove_requests']['status'] ? "checked=\"checked\"" : "",
    		'STATS_SETTINGS_COUNT_VISITOR_NUMBER'	=> $this->arrConfig['count_visitor_number']['status'] ? 'checked="checked"' : '',
    		'STATS_SETTINGS_ONLINE_TIMEOUT'		=> $this->arrConfig['online_timeout']['value'],
    		'STATS_SETTINGS_ONLINE_TIMEOUT_STATUS' => $this->arrConfig['online_timeout']['status'] ? "block" : "none",
    		'STATS_SETTINGS_ONLINE_TIMEOUT_CHECKED' => $this->arrConfig['online_timeout']['status'] ? "checked=\"checked\"" : "",
    		'STATS_SETTINGS_RELOAD_BLOCK_TIME'		=> $this->arrConfig['reload_block_time']['value'],
    		'STATS_SETTINGS_PAGING_LIMIT'		=> $this->arrConfig['paging_limit']['value'],
    		'STATS_SETTINGS_PAGING_LIMIT_VISITOR_DETAILS'	=> $this->arrConfig['paging_limit_visitor_details']['value'],

    	));
    }
}
?>
