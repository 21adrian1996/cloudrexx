<?php
/**
 * DocSys
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team <info@comvation.com>
 * @access      public
 * @version     1.0.0
 * @package     contrexx
 * @subpackage  module_docsys
 * @todo        Edit PHP DocBlocks!
 */


/**
 * Includes
 */
require_once ASCMS_MODULE_PATH . '/docsys/xmlfeed.class.php';
require_once ASCMS_MODULE_PATH . '/docsys/lib/Library.class.php';

/**
 * DocSys
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team <info@comvation.com>
 * @access      public
 * @version     1.0.0
 * @package     contrexx
 * @subpackage  module_docsys
 */
class docSysManager extends docSysLibrary
{
    var $_objTpl;
	var $pageTitle;
	var $pageContent;
	var $strErrMessage = '';
	var $strOkMessage = '';
	var $langId;


	/**
    * Constructor
    *
    * @param  string
    * @access public
    */
    function docSysManager()
    {
    	global  $_ARRAYLANG, $objInit, $objTemplate;

        $this->_objTpl = &new HTML_Template_Sigma(ASCMS_MODULE_PATH.'/docsys/template');
        $this->_objTpl->setErrorHandling(PEAR_ERROR_DIE);

        $objTemplate->setVariable("CONTENT_NAVIGATION","<a href='?cmd=docsys'>".$_ARRAYLANG['TXT_DOC_SYS_MENU_OVERVIEW']."</a>
                                                      <a href='?cmd=docsys&amp;act=add'>".$_ARRAYLANG['TXT_CREATE_DOCUMENT']."</a>
                                                      <a href='?cmd=docsys&amp;act=cat'>".$_ARRAYLANG['TXT_CATEGORY_MANAGER']."</a>");

        $this->pageTitle = $_ARRAYLANG['TXT_DOC_SYS_MANAGER'];
        $this->langId=$objInit->userFrontendLangId;
    }


    /**
    * Do the requested newsaction
    *
    * @return    string    parsed content
    */
    function getDocSysPage()
    {
    	global $objTemplate;

    	if(!isset($_GET['act'])){
    	    $_GET['act']="";
    	}

        switch($_GET['act']){
			case "add":
		        $this->add();
		        // $this->overview();
			break;

			case "edit":
				$this->edit();
			break;

			case "delete":
			    $this->delete();
			    $this->overview();
			break;

			case "update":
			    $this->update();
			    $this->overview();
			break;

			case "cat":
			    $this->manageCategories();
			    break;

			case "delcat":
			    $this->deleteCat();
			    $this->manageCategories();
			    break;

			case "changeStatus":
				$this->changeStatus();
				$this->overview();
				break;

			default:
			    $this->overview();
		}

		$objTemplate->setVariable(array(
			'CONTENT_TITLE'				=> $this->pageTitle,
			'CONTENT_OK_MESSAGE'		=> $this->strOkMessage,
			'CONTENT_STATUS_MESSAGE'	=> $this->strErrMessage,
			'ADMIN_CONTENT'				=> $this->_objTpl->get()
		));
    }

    /**
    * List up the news for edit or delete
    *
    * @global	 object    $objDatabase
    * @param     integer   $newsid
    * @param     string	   $what
    * @return    string    $output
    */
    function overview()
    {
    	global $objDatabase, $_ARRAYLANG, $_CONFIG;

    	// initialize variables
    	$i=0;

    	$this->_objTpl->loadTemplateFile('module_docsys_list.html',true,true);
		$this->pageTitle = $_ARRAYLANG['TXT_DOC_SYS_MANAGER'];

    	$this->_objTpl->setVariable(array(
    	    'TXT_EDIT_DOCSYS_MESSAGE'    => $_ARRAYLANG['TXT_EDIT_DOCUMENTS'],
    	    'TXT_EDIT_DOCSYS_ID'         => $_ARRAYLANG['TXT_DOCUMENT_ID'],
    	    'TXT_ARCHIVE'                => $_ARRAYLANG['TXT_ARCHIVE'],
    	    'TXT_DATE'                   => $_ARRAYLANG['TXT_DATE'],
    	    'TXT_TITLE'                  => $_ARRAYLANG['TXT_TITLE'],
    	    'TXT_USER'                   => $_ARRAYLANG['TXT_USER'],
    	    'TXT_LAST_EDIT'              => $_ARRAYLANG['TXT_LAST_EDIT'],
    	    'TXT_ACTION'                 => $_ARRAYLANG['TXT_ACTION'],
    	    'TXT_CATEGORY'               => $_ARRAYLANG['TXT_CATEGORY'],
            'TXT_CONFIRM_DELETE_DATA'    => $_ARRAYLANG['TXT_DOCUMENT_DELETE_CONFIRM'],
            'TXT_ACTION_IS_IRREVERSIBLE' => $_ARRAYLANG['TXT_ACTION_IS_IRREVERSIBLE'],
            'TXT_SELECT_ALL'             => $_ARRAYLANG['TXT_SELECT_ALL'],
            'TXT_REMOVE_SELECTION'       => $_ARRAYLANG['TXT_REMOVE_SELECTION'],
            'TXT_EDIT'                   => $_ARRAYLANG['TXT_EDIT'],
            'TXT_MARKED'                 => $_ARRAYLANG['TXT_MARKED'],
            'TXT_ACTIVATE'               => $_ARRAYLANG['TXT_ACTIVATE'],
            'TXT_DEACTIVATE'             => $_ARRAYLANG['TXT_DEACTIVATE'],
            'TXT_STATUS'                 => $_ARRAYLANG['TXT_STATUS'],
            'TXT_AUTHOR'                 => $_ARRAYLANG['TXT_AUTHOR'],
    	));

    	$this->_objTpl->setGlobalVariable(array(
    		'TXT_DELETE'	=> $_ARRAYLANG['TXT_DELETE']
    	));

	  	/**************************************
		 	paging start
		 **************************************/
		$query = "SELECT n.id AS docSysId,
		                 n.date AS date,
		                 n.changelog AS changelog,
		                 n.title AS title,
		                 n.status AS status,
		                 n.author AS author,
		                 l.name AS name,
		                 nc.name AS catname,
		                 u.username AS username
		            FROM ".DBPREFIX."module_docsys_categories AS nc,
		                 ".DBPREFIX."module_docsys AS n,
		                 ".DBPREFIX."languages AS l,
				         ".DBPREFIX."access_users AS u
		           WHERE n.lang=l.id
		             AND n.lang=".$this->langId."
		             AND nc.catid=n.catid
		             AND u.id=n.userid
		        ORDER BY n.id DESC";

		$objResult = $objDatabase->Execute($query);
		$count = $objResult->RecordCount();
		$pos = (isset($_GET['pos'])) ? intval($_GET['pos']) : 0;
		$paging = ($count>intval($_CONFIG['corePagingLimit'])) ? getPaging($count, $pos, "&amp;cmd=docsys", $_ARRAYLANG['TXT_DOCUMENTS '],true) : "";


		$objDatabase->SelectLimit($query, $pos, $_CONFIG['corePagingLimit']);
		$this->_objTpl->setCurrentBlock('row');
		while (!$objResult->EOF) {
            $class = ($i % 2) ? "row2" : "row1";
            $i++;
            $statusPicture = ($objResult->fields['status']==1) ? "status_green.gif" : "status_red.gif";

		    $this->_objTpl->setVariable(array(
                'DOCSYS_ID'         =>$objResult->fields['docSysId'],
                'DOCSYS_DATE'       =>date(ASCMS_DATE_FORMAT, $objResult->fields['date']),
                'DOCSYS_TITLE'      =>stripslashes($objResult->fields['title']),
                'DOCSYS_AUTHOR'      =>stripslashes($objResult->fields['author']),
                'DOCSYS_USER'       =>$objResult->fields['username'],
                'DOCSYS_CHANGELOG'  =>date(ASCMS_DATE_FORMAT, $objResult->fields['changelog']),
                'DOCSYS_PAGING'     =>$paging,
                'DOCSYS_CLASS'      =>$class,
                'DOCSYS_CATEGORY'   =>$objResult->fields['catname'],
                'DOCSYS_STATUS'     =>$objResult->fields['status'],
                'DOCSYS_STATUS_PICTURE' => $statusPicture,
			));
			$this->_objTpl->parseCurrentBlock("row");
			$objResult->MoveNext();
		}
    }



    function _getSortingDropdown($catID, $sorting = 'alpha')
    {
    	global $_ARRAYLANG;
    	return '
    		<select name="sortStyle['.$catID.']">
    			<option value="alpha" '.($sorting == 'alpha' ? 'selected="selected"' : '').' >'.$_ARRAYLANG['TXT_DOCSYS_SORTING_ALPHA'].'</option>
    			<option value="date" '.($sorting == 'date' ? 'selected="selected"' : '').'>'.$_ARRAYLANG['TXT_DOCSYS_SORTING_DATE'].'</option>
    			<option value="date_alpha" '.($sorting == 'date_alpha' ? 'selected="selected"' : '').'>'.$_ARRAYLANG['TXT_DOCSYS_SORTING_DATE_ALPHA'].'</option>
    		</select>
    	';
    }


    /**
    * adds a news entry
    *
    * @global	 object    $objDatabase
    * @param     integer   $newsid -> the id of the news entry
    * @return    boolean   result
    */
    function add()
    {
	    global $objDatabase, $_ARRAYLANG;

	    $this->_objTpl->loadTemplateFile('module_docsys_modify.html',true,true);
	    $this->pageTitle = $_ARRAYLANG['TXT_CREATE_DOCUMENT'];

	    $this->_objTpl->setVariable(array(
	        'TXT_DOCSYS_MESSAGE'     => $_ARRAYLANG['TXT_ADD_DOCUMENT'],
	        'TXT_TITLE'           	 => $_ARRAYLANG['TXT_TITLE'],
	        'TXT_CATEGORY'        	 => $_ARRAYLANG['TXT_CATEGORY'],
	        'TXT_HYPERLINKS'      	 => $_ARRAYLANG['TXT_HYPERLINKS'],
	        'TXT_EXTERNAL_SOURCE' 	 => $_ARRAYLANG['TXT_EXTERNAL_SOURCE'],
	        'TXT_LINK'            	 => $_ARRAYLANG['TXT_LINK'],
	        'TXT_DOCSYS_CONTENT'     => $_ARRAYLANG['TXT_CONTENT'],
	        'TXT_STORE'           	 => $_ARRAYLANG['TXT_STORE'],
	        'TXT_PUBLISHING'      	 => $_ARRAYLANG['TXT_PUBLISHING'],
	        'TXT_STARTDATE'       	 => $_ARRAYLANG['TXT_STARTDATE'],
	        'TXT_ENDDATE'         	 => $_ARRAYLANG['TXT_ENDDATE'],
	        'TXT_OPTIONAL'           => $_ARRAYLANG['TXT_OPTIONAL'],
	        'TXT_ACTIVE'             => $_ARRAYLANG['TXT_ACTIVE'],
	        'TXT_DATE'            	 => $_ARRAYLANG['TXT_DATE'],
			'DOCSYS_TEXT'            => get_wysiwyg_editor('docSysText'),
			'DOCSYS_FORM_ACTION'     => "add",
			'DOCSYS_STORED_FORM_ACTION' => "add",
			'DOCSYS_STATUS'          => "checked='checked'",
			'DOCSYS_ID'         	 => "",
			'DOCSYS_TOP_TITLE'       => $_ARRAYLANG['TXT_CREATE_DOCUMENT'],
			'DOCSYS_CAT_MENU'        => $this->getCategoryMenu($this->langId, $selectedOption=""),
			'DOCSYS_STARTDATE'       => "",
			'DOCSYS_ENDDATE' => "",
			'DOCSYS_DATE'  => date(ASCMS_DATE_FORMAT, time()),
			'DOCSYS_JS_DATE'	=> date('Y-m-d', $objResult->fields['date']),
            'TXT_AUTHOR' => $_ARRAYLANG['TXT_AUTHOR'],
            'DOCSYS_AUTHOR' => $_SESSION['auth']['username'],
		));

		if (isset($_POST['docSysTitle']) AND !empty($_POST['docSysTitle'])) {
    	    $this->insert();
    	    $this->createRSS();
		}
    }




    /**
    * Deletes a news entry
    *
    * @global	 object    $objDatabase
    * @global	 array     $_ARRAYLANG
    * @return    -
    */
    function delete()
    {
	    global $objDatabase, $_ARRAYLANG;

	    $newsId = "";
	    if(isset($_GET['id'])){
	    	$docSysId = intval($_GET['id']);

	    	$query = "DELETE FROM ".DBPREFIX."module_docsys WHERE id = $docSysId";

		    if ($objDatabase->Execute($query)) {
		    	$this->strOkMessage = $_ARRAYLANG['TXT_DATA_RECORD_DELETED_SUCCESSFUL'];
		    	$this->createRSS();
		    } else {
		    	$this->strErrMessage = $_ARRAYLANG['TXT_DATABASE_QUERY_ERROR'];
		    }
	    }

		if(is_array($_POST['selectedId'])) {
			foreach ($_POST['selectedId'] as $value) {
				if (!empty($value)) {
				    if($objDatabase->Execute("DELETE FROM ".DBPREFIX."module_docsys WHERE id = ".intval($value))) {
				    	$this->strOkMessage = $_ARRAYLANG['TXT_DATA_RECORD_DELETED_SUCCESSFUL'];
				    	$this->createRSS();
				    } else {
			            $this->strErrMessage = $_ARRAYLANG['TXT_DATABASE_QUERY_ERROR'];
				    }
				}
			}
		}
    }




    /**
    * Edit the news
    *
    * @global    object     $objDatabase
    * @param     string     $pageContent
    */
    function edit()
    {
    	global $objDatabase, $_ARRAYLANG;

		$status = "";
		$startDate = "";
		$endDate = "";

    	$this->_objTpl->loadTemplateFile('module_docsys_modify.html',true,true);
		$this->pageTitle = $_ARRAYLANG['TXT_EDIT_DOCUMENTS'];

		$this->_objTpl->setVariable(array(
		    'TXT_DOCSYS_MESSAGE'  => $_ARRAYLANG['TXT_EDIT_DOCUMENTS'],
		    'TXT_TITLE'           => $_ARRAYLANG['TXT_TITLE'],
		    'TXT_CATEGORY'        => $_ARRAYLANG['TXT_CATEGORY'],
		    'TXT_HYPERLINKS'      => $_ARRAYLANG['TXT_HYPERLINKS'],
		    'TXT_EXTERNAL_SOURCE' => $_ARRAYLANG['TXT_EXTERNAL_SOURCE'],
		    'TXT_LINK'            => $_ARRAYLANG['TXT_LINK'],
		    'TXT_DOCSYS_CONTENT'  => $_ARRAYLANG['TXT_CONTENT'],
		    'TXT_STORE'           => $_ARRAYLANG['TXT_STORE'],
	        'TXT_PUBLISHING'      => $_ARRAYLANG['TXT_PUBLISHING'],
	        'TXT_STARTDATE'       => $_ARRAYLANG['TXT_STARTDATE'],
	        'TXT_ENDDATE'         => $_ARRAYLANG['TXT_ENDDATE'],
	        'TXT_OPTIONAL'        => $_ARRAYLANG['TXT_OPTIONAL'],
	        'TXT_DATE'      	  => $_ARRAYLANG['TXT_DATE'],
	        'TXT_ACTIVE'=> $_ARRAYLANG['TXT_ACTIVE'],
	        'TXT_AUTHOR' => $_ARRAYLANG['TXT_AUTHOR'],
		));

		$id = intval($_REQUEST['id']);

		$query = "SELECT `catid`,
						   `lang`,
						   `date`,
						   `id`,
						   `title`,
		                   `author`,
						   `text`,
						   `source`,
						   `url1`,
						   `url2`,
						   `startdate`,
						   `enddate`,
						   `status`
		              FROM `".DBPREFIX."module_docsys`
		             WHERE id = '$id'
		             LIMIT 1";
		$objResult = $objDatabase->Execute($query);

		if(!$objResult->EOF) {
			$catId=$objResult->fields['catid'];
			$id = $objResult->fields['id'];
			$docSysText = stripslashes($objResult->fields['text']);

			if($objResult->fields['status']==1) {
				$status = "checked";
			}
			if($objResult->fields['startdate']!="0000-00-00") {
				$startDate = $objResult->fields['startdate'];
			}
			if($objResult->fields['enddate']!="0000-00-00") {
				$endDate = $objResult->fields['enddate'];
			}

			$this->_objTpl->setVariable(array(
				'DOCSYS_ID'		    => $id,
				'DOCSYS_STORED_ID'	=> $id,
				'DOCSYS_TITLE'		=> stripslashes(htmlspecialchars($objResult->fields['title'], ENT_QUOTES, CONTREXX_CHARSET)),
				'DOCSYS_AUTHOR'		=> stripslashes(htmlspecialchars($objResult->fields['author'], ENT_QUOTES, CONTREXX_CHARSET)),
				'DOCSYS_TEXT'		=> get_wysiwyg_editor('docSysText', $docSysText),
				'DOCSYS_SOURCE'	    => $objResult->fields['source'],
				'DOCSYS_URL1'		=> $objResult->fields['url1'],
				'DOCSYS_URL2'		=> $objResult->fields['url2'],
				'DOCSYS_STARTDATE'	=> $startDate,
				'DOCSYS_ENDDATE'	=> $endDate,
				'DOCSYS_STATUS'		=> $status,
				'DOCSYS_DATE'       => date(ASCMS_DATE_FORMAT, $objResult->fields['date']),
				'DOCSYS_JS_DATE'	=> date('Y-m-d', $objResult->fields['date']),
			));
		}

		$this->_objTpl->setVariable("DOCSYS_CAT_MENU",$this->getCategoryMenu($this->langId, $catId));
		$this->_objTpl->setVariable("DOCSYS_FORM_ACTION","update");
	    $this->_objTpl->setVariable("DOCSYS_STORED_FORM_ACTION","update");
	    $this->_objTpl->setVariable("DOCSYS_TOP_TITLE",$_ARRAYLANG['TXT_EDIT']);
    }




    /**
    * Update news
    *
    * @global	 object    $objDatabase
    * @return    boolean   result
    */
    function update()
    {
	    global $objDatabase, $_ARRAYLANG, $_CONFIG;

	    if (isset($_GET['id'])) {
	    	$id = intval($_GET['id']);
		    $userId = $_SESSION['auth']['userid'];
		    $changelog = mktime();
		    $date = date(ASCMS_DATE_FORMAT);
		    $title = get_magic_quotes_gpc() ? strip_tags($_POST['docSysTitle']) : addslashes(strip_tags($_POST['docSysTitle']));
		    $text = get_magic_quotes_gpc() ? $_POST['docSysText'] : addslashes($_POST['docSysText']);
		    $title= str_replace("�","ss",$title);
		    $text = $this->filterBodyTag($text);
		    $text = str_replace("�","ss",$text);
		    $source	= get_magic_quotes_gpc() ? strip_tags($_POST['docSysSource']) : addslashes(strip_tags($_POST['docSysSource']));
		    $url1 = get_magic_quotes_gpc() ? strip_tags($_POST['docSysUrl1']) : addslashes(strip_tags($_POST['docSysUrl1']));
		    $url2 = get_magic_quotes_gpc() ? strip_tags($_POST['docSysUrl2']) : addslashes(strip_tags($_POST['docSysUrl2']));
		    $catId = intval($_POST['docSysCat']);
		    $status = (!empty($_POST['status'])) ? intval($_POST['status']) : 0;
		    $startDate = get_magic_quotes_gpc() ? strip_tags($_POST['startDate']) : addslashes(strip_tags($_POST['startDate']));
		    $endDate = get_magic_quotes_gpc() ? strip_tags($_POST['endDate']) : addslashes(strip_tags($_POST['endDate']));
		    $author =  get_magic_quotes_gpc() ? strip_tags($_POST['author']) : addslashes(strip_tags($_POST['author']));

		    $query = "UPDATE ".DBPREFIX."module_docsys
					       SET title='$title',
					       	   date=".$this->_checkDate($_POST['creation_date']).",
		                       author='".$author."',
		                       text='$text',
		                       source='$source',
		                       url1='$url1',
		                       url2='$url2',
		                       catid='$catId',
		                       lang='$this->langId',
		                       userid = '$userId',
		                       status = '$status',
		                       startdate = '$startDate',
		                       enddate = '$endDate',
		                       changelog = '$changelog'
	                     WHERE id = '$id'";
		   if(!$objDatabase->Execute($query)) {
		   	    $this->strErrMessage = $_ARRAYLANG['TXT_DATABASE_QUERY_ERROR'];
		   } else {
	            $this->createRSS();
	            $this->strOkMessage = $_ARRAYLANG['TXT_DATA_RECORD_UPDATED_SUCCESSFUL'];
		   }
        }
    }






    /**
    * Update news
    *
    * @global	 object    $objDatabase
    * @global	 array     $_POST
    * @param     integer   $newsid
    * @return    boolean   result
    */
	function changeStatus()
	{
		global $objDatabase, $_ARRAYLANG, $_CONFIG;

		if(isset($_POST['deactivate']) AND !empty($_POST['deactivate'])){
			$status = 0;
		}
		if(isset($_POST['activate']) AND !empty($_POST['activate'])){
			$status = 1;
		}
		if(isset($status)){
			if(is_array($_POST['selectedId'])){
				foreach ($_POST['selectedId'] as $value){
					if (!empty($value)){
					    $retval = $objDatabase->Execute("UPDATE ".DBPREFIX."module_docsys SET status = '$status' WHERE id = ".intval($value));
					}
				    if(!$retval){
				   	    $this->strErrMessage = $_ARRAYLANG['TXT_DATABASE_QUERY_ERROR'];
				    } else{
			            $this->strOkMessage = $_ARRAYLANG['TXT_DATA_RECORD_UPDATED_SUCCESSFUL'];
				    }
				}
			}
		}
    }

	/**
	 * checks if date is valid
	 *
	 * @param string $date
	 * @return integer $timestamp
	 */
	function _checkDate($date)
    {
    	if (preg_match('/^([0-9]{1,2})\:([0-9]{1,2})\:([0-9]{1,2})\s*([0-9]{1,2})\.([0-9]{1,2})\.([0-9]{1,4})/', $date, $arrDate)) {
	    	return mktime(intval($arrDate[1]), intval($arrDate[2]), intval($arrDate[3]), intval($arrDate[5]), intval($arrDate[4]), intval($arrDate[6]));
	    } else {
	    	return time();
	    }
    }


    /**
    * Insert news
    *
    * @global	 object    $objDatabase
    * @return    boolean   result
    */
    function insert()
    {
	    global $objDatabase, $_ARRAYLANG;

	    $date = $this->_checkDate($_POST['creation_date']);
	    $title = get_magic_quotes_gpc() ? strip_tags($_POST['docSysTitle']) : addslashes(strip_tags($_POST['docSysTitle']));
	    $author = get_magic_quotes_gpc() ? strip_tags($_POST['author']) : addslashes(strip_tags($_POST['author']));
	    $text = get_magic_quotes_gpc() ? $_POST['docSysText'] : addslashes($_POST['docSysText']);

	    $title = str_replace("�","ss",$title);
	    $text = str_replace("�","ss",$text);
	    $text = $this->filterBodyTag($text);

	    $source = get_magic_quotes_gpc() ? strip_tags($_POST['docSysSource']) : addslashes(strip_tags($_POST['docSysSource']));
	    $url1 = get_magic_quotes_gpc() ? strip_tags($_POST['docSysUrl1']) : addslashes(strip_tags($_POST['docSysUrl1']));
	    $url2 = get_magic_quotes_gpc() ? strip_tags($_POST['docSysUrl2']) : addslashes(strip_tags($_POST['docSysUrl2']));

	    $cat = intval($_POST['docSysCat']);
	    $userid = intval($_SESSION['auth']['userid']);

	    $startDate = get_magic_quotes_gpc() ? strip_tags($_POST['startDate']) : addslashes(strip_tags($_POST['startDate']));
	    $endDate = get_magic_quotes_gpc() ? strip_tags($_POST['endDate']) : addslashes(strip_tags($_POST['endDate']));

	    $status = intval($_POST['status']);
	    if($status == 0) {
	        $startDate = "";
	        $endDate = "";
	    }

	    $query = "INSERT INTO `".DBPREFIX."module_docsys`
	                            ( `id`,
							      `date`,
							      `title`,
	                              `author`,
							      `text`,
							      `source`,
							      `url1`,
							      `url2`,
							      `catid`,
							      `lang`,
								  `startdate`,
								  `enddate`,
								  `status`,
							      `userid`,
							      `changelog` )
	                     VALUES ( '',
	                              '$date',
								  '$title',
	                              '$author',
								  '$text',
								  '$source',
								  '$url1',
								  '$url2',
								  '$cat',
								  '$this->langId',
	                              '$startDate',
	                              '$endDate',
	                              '$status',
								  '$userid',
								  '$date')";


        if ($objDatabase->Execute($query)){
    	    $this->strOkMessage = $_ARRAYLANG['TXT_DATA_RECORD_ADDED_SUCCESSFUL'];
        } else {
        	$this->strErrMessage = $_ARRAYLANG['TXT_DATABASE_QUERY_ERROR'];
        }
        $this->overview();
    }



    /**
    * Add or edit the news categories
    *
    * @global    object     $objDatabase
    * @global    array      $_ARRAYLANG
    * @param     string     $pageContent
    */
    function manageCategories()
    {
    	global $objDatabase,$_ARRAYLANG;

    	$this->_objTpl->loadTemplateFile('module_docsys_category.html',true,true);
		$this->pageTitle = $_ARRAYLANG['txtCategoryManager'];

    	$this->_objTpl->setVariable(array(
    	    'TXT_ADD_NEW_CATEGORY'                       => $_ARRAYLANG['TXT_ADD_NEW_CATEGORY'],
    	    'TXT_NAME'                                   => $_ARRAYLANG['TXT_NAME'],
    	    'TXT_ADD'                                    => $_ARRAYLANG['TXT_ADD'],
    	    'TXT_CATEGORY_LIST'                          => $_ARRAYLANG['TXT_CATEGORY_LIST'],
    	    'TXT_ID'                                     => $_ARRAYLANG['TXT_ID'],
    	    'TXT_ACTION'                                 => $_ARRAYLANG['TXT_ACTION'],
    	    'TXT_ACCEPT_CHANGES'                         => $_ARRAYLANG['TXT_ACCEPT_CHANGES'],
    	    'TXT_CONFIRM_DELETE_DATA'                    => $_ARRAYLANG['TXT_CONFIRM_DELETE_DATA'],
    	    'TXT_ACTION_IS_IRREVERSIBLE'                 => $_ARRAYLANG['TXT_ACTION_IS_IRREVERSIBLE'],
    	    'TXT_ATTENTION_SYSTEM_FUNCTIONALITY_AT_RISK' => $_ARRAYLANG['TXT_ATTENTION_SYSTEM_FUNCTIONALITY_AT_RISK'],
    	    'TXT_DOCSYS_SORTING' 						 => $_ARRAYLANG['TXT_DOCSYS_SORTING'],
    	    'TXT_DOCSYS_SORTTYPE' 						 => $_ARRAYLANG['TXT_DOCSYS_SORTTYPE'],
    	));

    	$this->_objTpl->setGlobalVariable(array(
    		'TXT_DELETE'	=> $_ARRAYLANG['TXT_DELETE']
    	));

    	// Add a new category
    	if (isset($_POST['addCat']) AND ($_POST['addCat']==true)){
    		 $catName = get_magic_quotes_gpc() ? strip_tags($_POST['newCatName']) : addslashes(strip_tags($_POST['newCatName']));
    	     if($objDatabase->Execute("INSERT INTO ".DBPREFIX."module_docsys_categories (name,lang)
    	                         VALUES ('$catName','$this->langId')")) {
    	     	$this->strOkMessage = $_ARRAYLANG['TXT_DATA_RECORD_ADDED_SUCCESSFUL'];
    	     } else {
    	     	$this->strErrMessage = $_ARRAYLANG['TXT_DATABASE_QUERY_ERROR'];
    	     }

    	}

    	// Modify a new category
    	if (isset($_POST['modCat']) AND ($_POST['modCat']==true)) {
    		foreach ($_POST['catName'] as $id => $name) {
				$name = get_magic_quotes_gpc() ? strip_tags($name) : addslashes(strip_tags($name));
				$id=intval($id);

				$sorting = !empty($_REQUEST['sortStyle'][$id]) ? contrexx_addslashes($_REQUEST['sortStyle'][$id]) : 'alpha';

			    if($objDatabase->Execute("UPDATE ".DBPREFIX."module_docsys_categories
			                      SET name='$name',
			                          lang='$this->langId',
			                          sort_style='$sorting'
			                    WHERE catid=$id"))
			    {
			    	$this->strOkMessage = $_ARRAYLANG['TXT_DATA_RECORD_UPDATED_SUCCESSFUL'];
			    } else {
			    	$this->strErrMessage = $_ARRAYLANG['TXT_DATABASE_QUERY_ERROR'];
			    }
		    }
    	}

		$query = "SELECT `catid`,
		                   `name`,
		                   `sort_style`
		              FROM `".DBPREFIX."module_docsys_categories`
		             WHERE `lang`='$this->langId'
		          ORDER BY `catid` asc";
		$objResult = $objDatabase->Execute($query);

		$this->_objTpl->setCurrentBlock('row');
		$i=0;

		while (!$objResult->EOF) {
			$class = (($i % 2) == 0) ? "row1" : "row2";
			$sorting = $objResult->fields['sort_style'];
			$this->_objTpl->setVariable(array(
			    'DOCSYS_ROWCLASS'   => $class,
				'DOCSYS_CAT_ID'	  => $objResult->fields['catid'],
				'DOCSYS_CAT_NAME'	  => stripslashes($objResult->fields['name']),
				'DOCSYS_SORTING_DROPDOWN'	=> $this->_getSortingDropdown($objResult->fields['catid'], $sorting),
			));
			$this->_objTpl->parseCurrentBlock('row');
			$i++;
			$objResult->MoveNext();
		};
    }



    /**
    * Delete the news categories
    *
    * @global    object     $objDatabase
    * @global    array      $_ARRAYLANG[news*]
    * @param     string     $pageContent
    */
    function deleteCat(){
    	global $objDatabase,$_ARRAYLANG;

    	if(isset($_GET['catId'])) {
    	    $catId=intval($_GET['catId']);
    	    $objResult = $objDatabase->Execute("SELECT id FROM ".DBPREFIX."module_docsys WHERE catid=$catId");

    	    if (!$objResult->EOF) {
    	         $this->strErrMessage = $_ARRAYLANG['TXT_CATEGORY_NOT_DELETED_BECAUSE_IN_USE'];
    	    } else {
    	        if($objDatabase->Execute("DELETE FROM ".DBPREFIX."module_docsys_categories WHERE catid=$catId")) {
    	            $this->strOkMessage = $_ARRAYLANG['TXT_DATA_RECORD_DELETED_SUCCESSFUL'];
    	        } else {
    	        	$this->strErrMessage = $_ARRAYLANG['TXT_DATABASE_QUERY_ERROR'];
    	        }
    	    }
    	}
    }


    /**
    * Gets only the body content and deleted all the other tags
    *
    * @param     string     $fullContent      HTML-Content with more than BODY
    * @return    string     $content          HTML-Content between BODY-Tag
    */
    function filterBodyTag($fullContent){
	    $res=false;
	    $posBody=0;
	    $posStartBodyContent=0;
	    $res=preg_match_all("/<body[^>]*>/i", $fullContent, $arrayMatches);
	    if ($res==true) {
            $bodyStartTag = $arrayMatches[0][0];
            // Position des Start-Tags holen
            $posBody = strpos($fullContent, $bodyStartTag, 0);
            // Beginn des Contents ohne Body-Tag berechnen
            $posStartBodyContent = $posBody + strlen($bodyStartTag);
	    }
	    $posEndTag=strlen($fullContent);
	    $res=preg_match_all("/<\/body>/i",$fullContent, $arrayMatches);
	    if($res==true){
            $bodyEndTag=$arrayMatches[0][0];
            // Position des End-Tags holen
            $posEndTag = strpos($fullContent, $bodyEndTag, 0);
            // Content innerhalb der Body-Tags auslesen
	     }
	     $content = substr($fullContent, $posStartBodyContent, $posEndTag  - $posStartBodyContent);
         return $content;
    }


    /**
    * Create the RSS-Feed
    *
    */
    function createRSS()
    {
    	global $_CONFIG;
    	/*
		$RSS = new rssFeed();
		$RSS->channelTitle = $_CONFIG['backendXmlChannelTitle'];
		$RSS->channelDescription = $_CONFIG['backendXmlChannelDescription'];

		$RSS->xmlType = "headlines";
		$RSS->createXML();
		$RSS->xmlType = "fulltext";
		$RSS->createXML();
		*/
    }
}
?>
