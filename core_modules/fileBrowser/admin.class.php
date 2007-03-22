<?php
/**
 * File browser
 * @copyright   CONTREXX CMS - ASTALAVISTA IT AG
 * @author		Astalavista Development Team <thun@astalvista.ch>
 * @version		1.0.0
 * @package     contrexx
 * @subpackage  core_module_filebrowser
 * @todo        Edit PHP DocBlocks!
 */

/**
 * Includes
 */
require_once ASCMS_FRAMEWORK_PATH.'/System.class.php';
require_once ASCMS_FRAMEWORK_PATH.'/File.class.php';
require_once ASCMS_CORE_PATH.'/Tree.class.php';
require_once(ASCMS_FRAMEWORK_PATH.DIRECTORY_SEPARATOR.'Image.class.php');

/**
 * File browser
 * @copyright   CONTREXX CMS - ASTALAVISTA IT AG
 * @author		Astalavista Development Team <thun@astalvista.ch>
 * @access		public
 * @version		1.0.0
 * @package     contrexx
 * @subpackage  core_module_filebrowser
 */
class FileBrowser {

	var $_objTpl;
	var $_pageTitle;
	var $_okMessage = array();
	var $_errMessage = array();
	var $_arrFiles = array();
	var $_arrDirectories = array();
	var $_path = "";
	var $_iconWebPath = '';
	var $_mediaType = '';
	var $_arrWebpages = array();
	var $_arrMediaTypes = array('files' 	=> 'TXT_FILEBROWSER_FILES',
								'webpages' 	=> 'TXT_FILEBROWSER_WEBPAGES',
								'media1'	=> 'TXT_FILEBROWSER_MEDIA_1',
								'media2'	=> 'TXT_FILEBROWSER_MEDIA_2',
								'media3'	=> 'TXT_FILEBROWSER_MEDIA_3',
								'media4'	=> 'TXT_FILEBROWSER_MEDIA_4',
								'shop'	    => 'TXT_FILEBROWSER_SHOP'
							);
    var $_shopEnabled;



	/**
	* Constructor
	*/
	function FileBrowser()
	{
		$this->__construct();
	}


	/**
	* PHP5 constructor
	*
	* @global object $objTemplate
	* @global array $_ARRAYLANG
	*/
	function __construct()
	{
		global $_ARRAYLANG;

		$this->_objTpl = &new HTML_Template_Sigma(ASCMS_CORE_MODULE_PATH.'/fileBrowser/template');
		$this->_objTpl->setErrorHandling(PEAR_ERROR_DIE);

		$this->_iconPath = ASCMS_MODULE_IMAGE_WEB_PATH.'/fileBrowser/';
		$this->_path = $this->_getPath();
		$this->_mediaType = $this->_getMediaType();

		$this->_shopEnabled = $this->_checkForShop();

		$this->_checkUpload();
		$this->_initFiles();
	}


	/**
	 * checks whether the shop module is available and active
	 *
	 * @return bool
	 */
    function _checkForShop(){
        global $objDatabase;
        if( ($objRS = $objDatabase->SelectLimit("SELECT `id` FROM ".DBPREFIX."modules WHERE name = 'shop' AND status = 'y'")) != false){
            if($objRS->RecordCount() > 0){
                return true;
            }
        }
        return false;
    }


	/**
	* Get media type
	*
	* Get the type of which media content should be displayed in the file browser
	*
	* @access private
	* @see FileBrowser::_arrMediaTypes
	*/
	function _getMediaType()
	{
		if (isset($_REQUEST['type']) && isset($this->_arrMediaTypes[$_REQUEST['type']])) {
			return $_REQUEST['type'];
		} else {
			return 'files';
		}
	}

	/**
	* Get the path
	*
	* @return string	current browsing path
	*/
	function _getPath()
	{
		$path = "";
		if (isset($_REQUEST['path']) && !stristr($_REQUEST['path'], '..')) {
			$path = $_REQUEST['path'];
		}
		$pos = strrpos($path, '/');
		if ($pos === false || $pos != (strlen($path)-1)) {
			$path .= "/";
		}

		return $path;
	}


	/**
	* Set the backend page
	*
	* @access public
	* @global object $objTemplate
	* @global array $_ARRAYLANG
	*/
	function getPage()
	{
		if (!isset($_REQUEST['act'])) {
			$_REQUEST['act'] = '';
		}
		switch ($_REQUEST['act']) {
		case 'FCKEditorUpload':
			$this->_FCKEditorUpload();
			break;

		default:
			$this->_showFileBrowser();
			break;
		}
	}


	function SendResults( $errorNumber, $fileUrl = '', $fileName = '', $customMsg = '' )
	{
		echo '<script type="text/javascript">' ;
		echo 'window.parent.OnUploadCompleted(' . $errorNumber . ',"' . str_replace( '"', '\\"', $fileUrl ) . '","' . str_replace( '"', '\\"', $fileName ) . '", "' . str_replace( '"', '\\"', $customMsg ) . '") ;' ;
		echo '</script>' ;
		exit ;
	}


	function _FCKEditorUpload()
	{
		if (!isset($_FILES['NewFile']) || is_null($_FILES['NewFile']['tmp_name']) || $_FILES['NewFile']['name'] == '') {
			$this->SendResults('202');
		} else {
			$this->_uploadFile($_FILES['NewFile']['name'], $_FILES['NewFile']['tmp_name'], $uploadFileName);
			$this->SendResults( 0, ASCMS_CONTENT_IMAGE_WEB_PATH.$this->_path.$uploadFileName, $uploadFileName);
		}
	}


	/**
	* Show file browser
	*
	* Show the file browser
	*
	* @access private
	* @global array $_ARRAYLANG
	*/
	function _showFileBrowser()
	{
		global $_ARRAYLANG;

		$this->_objTpl->loadTemplateFile('module_fileBrowser_frame.html');

		switch($this->_mediaType) {
			case 'media1':
				$strWebPath = ASCMS_MEDIA1_WEB_PATH.$this->_path;
			break;
			case 'media2':
				$strWebPath = ASCMS_MEDIA2_WEB_PATH.$this->_path;
			break;
			case 'media3':
				$strWebPath = ASCMS_MEDIA3_WEB_PATH.$this->_path;
			break;
			case 'media4':
				$strWebPath = ASCMS_MEDIA4_WEB_PATH.$this->_path;
			break;
    		case 'webpages':
				$strWebPath = 'Webpages (DB)';
			break;
			case 'shop':
				$strWebPath = ASCMS_SHOP_IMAGES_WEB_PATH.$this->_path;
			break;
			default:
				$strWebPath = ASCMS_CONTENT_IMAGE_WEB_PATH.$this->_path;
		}

		$this->_objTpl->setVariable(array(
			'FILEBROWSER_WEB_PATH'	=> $strWebPath,
			'TXT_CLOSE'				=> $_ARRAYLANG['TXT_CLOSE']
		));

		$this->_setNaviagtion();
		$this->_setContent();
		$this->_setUploadForm();
        $this->_showStatus();
		$this->_objTpl->show();
	}

	/**
	 * set the error/ok messages in the template
	 *
	 * @return void
	 */
	function _showStatus()
	{
	    $okMessage  = '';
	    $errMessage = '';

   	    $okMessage  = implode('<br />', $this->_okMessage);
   	    $errMessage = implode('<br />', $this->_errMessage);

   	    if(!empty($errMessage)){
	       $this->_objTpl->setVariable('FILEBROWSER_ERROR_MESSAGE', $errMessage);
	    }else{
	       $this->_objTpl->hideBlock('errormsg');
	    }

	    if(!empty($okMessage)){
    	    $this->_objTpl->setVariable('FILEBROWSER_OK_MESSAGE', $okMessage);
	    }else{
	       $this->_objTpl->hideBlock('okmsg');
	    }
	}

	/**
	 * put $message in the array specified by type
	 * for later use of $this->_showStatus();
	 *
	 * @param string $message
	 * @param string $type ('ok' or 'error')
	 * @return void
	 * @see $this->_showStatus();
	 */
	function _pushStatusMessage($message, $type = 'ok')
	{
	   switch ($type){
	       case 'ok':
	           array_push($this->_okMessage, $message);
	           break;
	       case 'error':
	           array_push($this->_errMessage, $message);
	           break;
	       default:
	           $this->_pushStatusMessage('invalid errortype, check admin.class.php.', 'error');
	           break;
	   }
	}
	/**
	* Check if there is a file-upload in the current reqest
	*
	*/
	function _checkUpload()
	{
		if (isset($_FILES['fileBrowserUploadFile']) && !empty($_FILES['fileBrowserUploadFile'])) {
			$this->_uploadFile($_FILES['fileBrowserUploadFile']['name'], $_FILES['fileBrowserUploadFile']['tmp_name'],$tmp);
		}
	}


	/**
	 * Upload a file
	 *
	 * @param string $uploadFileName: the name of the file
	 * @param string $tmpFileName: temporary name of th efile
	 * @param string $uploadedFileName: reference to the file name after upload
	 */
	function _uploadFile($uploadFileName, $tmpFileName, &$uploadedFileName)
	{
	    global $_ARRAYLANG;
		$file = $uploadFileName;
		$fileExtension = '';

		switch($this->_mediaType) {
			case 'media1':
				$strPath 	= ASCMS_MEDIA1_PATH.$this->_path;
				$strWebPath = ASCMS_MEDIA1_WEB_PATH.$this->_path;
			break;
			case 'media2':
				$strPath 	= ASCMS_MEDIA2_PATH.$this->_path;
				$strWebPath = ASCMS_MEDIA2_WEB_PATH.$this->_path;
			break;
			case 'media3':
				$strPath 	= ASCMS_MEDIA3_PATH.$this->_path;
				$strWebPath = ASCMS_MEDIA3_WEB_PATH.$this->_path;
			break;
			case 'media4':
				$strPath 	= ASCMS_MEDIA4_PATH.$this->_path;
				$strWebPath = ASCMS_MEDIA4_WEB_PATH.$this->_path;
			break;
			case 'shop':
                $strPath 	= ASCMS_SHOP_IMAGES_PATH.$this->_path;
				$strWebPath = ASCMS_SHOP_IMAGES_WEB_PATH.$this->_path;
			break;
			default:
				$strPath 	= ASCMS_CONTENT_IMAGE_PATH.$this->_path;
				$strWebPath = ASCMS_CONTENT_IMAGE_WEB_PATH.$this->_path;
		}

		$nr = 1;

		if(!empty($_REQUEST['newDir']) && preg_match('#^[0-9a-zA-Z_\-]+$#', $_REQUEST['newDir'])){
		    $objFile = &new File();
		    if(!$objFile->mkDir($strPath, $strWebPath, $_REQUEST['newDir'])){
		        $this->_pushStatusMessage(sprintf($_ARRAYLANG['TXT_FILEBROWSER_UNABLE_TO_CREATE_FOLDER'], $_REQUEST['newDir']), 'error');
		    }else{
		        $this->_pushStatusMessage(sprintf($_ARRAYLANG['TXT_FILEBROWSER_DIRECTORY_SUCCESSFULLY_CREATED'], $_REQUEST['newDir']));
		    }
		}else if(!empty($_REQUEST['newDir'])){
		    $this->_pushStatusMessage($_ARRAYLANG['TXT_FILEBROWSER_INVALID_CHARACTERS'], 'error');
		}

		if (@file_exists($strPath.$uploadFileName)) {
			if (preg_match('/.*\.(.*)$/', $uploadFileName, $arrSubPatterns)) {
				$fileName = substr($uploadFileName, 0, strrpos($uploadFileName, '.'));
				$fileExtension = $arrSubPatterns[1];
				$file = $fileName.'-'.$nr.'.'.$fileExtension;

				while (@file_exists($strPath.$file)) {
					$file = substr($uploadFileName, 0, strrpos($uploadFileName, '.')).'-'.$nr.'.'.$fileExtension;
					$nr++;
				}
			} else {
				return false;
			}
		}
		$uploadedFileName = $file;

		if (move_uploaded_file($tmpFileName, $strPath.$file)) {
		    if(!$objFile){
		        $objFile = &new File();
		    }
			$objFile->setChmod($strPath, $strWebPath, $file);
		}
		if($this->_mediaType == 'shop'){
		    if($this->_createThumb($strPath, $strWebPath, $file)){
		      $this->_pushStatusMessage(sprintf($_ARRAYLANG['TXT_FILEBROWSER_THUMBNAIL_SUCCESSFULLY_CREATED'], $strWebPath.$file));
		    }
		}
	}


	function _createThumb($strPath, $strWebPath, $file, $height = 80, $quality = 90){
	    global $_ARRAYLANG;
        $objFile = &new File();

	    $_objImage = &new ImageManager();
        $tmpSize    = getimagesize($strPath.$file);
        $thumbWidth = $height / $tmpSize[1] * $tmpSize[0];
        $_objImage->loadImage($strPath.$file);
        $_objImage->resizeImage($thumbWidth, $height, $quality);
        $_objImage->saveNewImage($strPath.$file . '.thumb');

	    if($objFile->setChmod($strPath, $strWebPath, $file . '.thumb')){
	       return true;
	    }
	    return false;
	}

	/**
	* Set the navigation in the file browser
	*
	* Set the navigation with the media type drop-down menu in the file browser
	*
	* @access private
	* @see FileBrowser::_getMediaTypeMenu, _objTpl, _mediaType, _arrDirectories
	*/
	function _setNaviagtion()
	{
		global $_ARRAYLANG;

		$this->_objTpl->addBlockfile('FILEBROWSER_NAVIGATION', 'fileBrowser_navigation', 'module_fileBrowser_navigation.html');

		$this->_objTpl->setVariable(array(
			'FILEBROWSER_MEDIA_TYPE_MENU'	=> $this->_getMediaTypeMenu('fileBrowserType', $this->_mediaType, 'onchange="window.location.replace(\'index.php?cmd=fileBrowser&amp;standalone=true&amp;type=\'+this.value)" style="width:180px;"'),
			'TXT_FILEBROWSER_PREVIEW'		=> $_ARRAYLANG['TXT_FILEBROWSER_PREVIEW']
		));

		if ($this->_mediaType != 'webpages') {
			// only show directories if the files should be displayed
			if (count($this->_arrDirectories) > 0) {
				foreach ($this->_arrDirectories as $arrDirectory) {
					$this->_objTpl->setVariable(array(
						'FILEBROWSER_FILE_PATH'	=> "index.php?cmd=fileBrowser&amp;standalone=true&amp;type=".$this->_mediaType."&amp;path=".$arrDirectory['path'],
						'FILEBROWSER_FILE_NAME'	=> $arrDirectory['name'],
						'FILEBROWSER_FILE_ICON'	=> $arrDirectory['icon']
					));
					$this->_objTpl->parse('navigation_directories');
				}
			}
		}

		$this->_objTpl->parse('fileBrowser_navigation');
	}


	/**
	* Shows all files / pages in filebrowser
	*
	*/
	function _setContent()
	{
		global $objDatabase, $objPerm;

		$this->_objTpl->addBlockfile('FILEBROWSER_CONTENT', 'fileBrowser_content', 'module_fileBrowser_content.html');

		$rowNr = 0;

		switch ($this->_mediaType) {
		case 'webpages':
			$arrModules = array();
			$objResult = $objDatabase->Execute("SELECT id, name FROM ".DBPREFIX."modules");
			if ($objResult !== false) {
				while (!$objResult->EOF) {
					$arrModules[$objResult->fields['id']] = $objResult->fields['name'];
					$objResult->MoveNext();
				}
			}
			$getPageId = (isset($_REQUEST['getPageId']) && $_REQUEST['getPageId'] == 'true') ? true : false;

			$objContentTree = &new ContentTree();
			foreach ($objContentTree->getTree() as $arrPage) {
				$s = isset($arrModules[$arrPage['moduleid']]) ? $arrModules[$arrPage['moduleid']] : '';
				$c = $arrPage['cmd'];
				$section = ($s=="") ? "" : "&amp;section=$s";
				$cmd = ($c=="") ? "" : "&amp;cmd=$c";
				$link = ASCMS_PATH_OFFSET.'/index.php'.((!empty($s)) ? "?section=".$s.$cmd : "?page=".$arrPage['catid'].$section.$cmd);

				$this->_objTpl->setVariable(array(
					'FILEBROWSER_ROW_CLASS'			=> $rowNr%2 == 0 ? "row1" : "row2",
					'FILEBROWSER_FILE_PATH_CLICK'	=> "javascript:{setUrl('".$link."'".($getPageId ? ','.$arrPage['catid'] : '').")}",
					'FILEBROWSER_FILE_NAME'			=> $arrPage['catname'],
					'FILEBROWSER_FILESIZE'			=> '&nbsp;',
					'FILEBROWSER_FILE_ICON'			=> $this->_iconPath.'htm.gif',
					'FILEBROWSER_FILE_DIMENSION'	=> '&nbsp;',
					'FILEBROWSER_SPACER'			=> '<img src="images/icons/pixel.gif" width="'.($arrPage['level']*16).'" height="1" />'
				));
				$this->_objTpl->parse('content_files');

				$rowNr++;
			}

		break;

		case 'media1':
		case 'media2':
		case 'media3':
		case 'media4':
			$objPerm->checkAccess(7, 'static');		//Access Media-Archive
			$objPerm->checkAccess(38, 'static');	//Edit Media-Files
			$objPerm->checkAccess(39, 'static');	//Upload Media-Files

		//Hier soll wirklich kein break stehen! Beabsichtig!


		default:
			if (count($this->_arrDirectories) > 0) {
				foreach ($this->_arrDirectories as $arrDirectory) {
					$this->_objTpl->setVariable(array(
						'FILEBROWSER_ROW_CLASS'			=> $rowNr%2 == 0 ? "row1" : "row2",
						'FILEBROWSER_FILE_PATH_CLICK'	=> "index.php?cmd=fileBrowser&amp;standalone=true&amp;type=".$this->_mediaType."&amp;path=".$arrDirectory['path'],
						'FILEBROWSER_FILE_NAME'			=> $arrDirectory['name'],
						'FILEBROWSER_FILESIZE'			=> '&nbsp;',
						'FILEBROWSER_FILE_ICON'			=> $arrDirectory['icon'],
						'FILEBROWSER_FILE_DIMENSION'	=> '&nbsp;'
					));
					$this->_objTpl->parse('content_files');

					$rowNr++;
				}
			}
			if (count($this->_arrFiles) > 0) {
				foreach ($this->_arrFiles as $arrFile) {
					$this->_objTpl->setVariable(array(
						'FILEBROWSER_ROW_CLASS'				=> $rowNr%2 == 0 ? "row1" : "row2",
						'FILEBROWSER_FILE_PATH_DBLCLICK'	=> "setUrl('".$arrFile['path']."',".$arrFile['width'].",".$arrFile['height'].",'')",
						'FILEBROWSER_FILE_PATH_CLICK'		=> "javascript:{showPreview('".$arrFile['path']."',".$arrFile['width'].",".$arrFile['height'].")}",
						'FILEBROWSER_FILE_NAME'				=> $arrFile['name'],
						'FILEBROWSER_FILESIZE'				=> $arrFile['size'].' KB',
						'FILEBROWSER_FILE_ICON'				=> $arrFile['icon'],
						'FILEBROWSER_FILE_DIMENSION'		=> (empty($arrFile['width']) && empty($arrFile['height'])) ? '' : intval($arrFile['width']).'x'.intval($arrFile['height'])
					));
					$this->_objTpl->parse('content_files');

					$rowNr++;
				}
			}

			switch ($this->_mediaType) {
				case 'media1':
					$this->_objTpl->setVariable('FILEBROWSER_IMAGE_PATH', ASCMS_MEDIA1_WEB_PATH);
				break;
				case 'media2':
					$this->_objTpl->setVariable('FILEBROWSER_IMAGE_PATH', ASCMS_MEDIA2_WEB_PATH);
				break;
				case 'media3':
					$this->_objTpl->setVariable('FILEBROWSER_IMAGE_PATH', ASCMS_MEDIA3_WEB_PATH);
				break;
				case 'media4':
					$this->_objTpl->setVariable('FILEBROWSER_IMAGE_PATH', ASCMS_MEDIA4_WEB_PATH);
				break;
				case 'shop':
					$this->_objTpl->setVariable('FILEBROWSER_IMAGE_PATH', ASCMS_SHOP_IMAGES_WEB_PATH);
				break;
				default:
					$this->_objTpl->setVariable('FILEBROWSER_IMAGE_PATH', ASCMS_CONTENT_IMAGE_WEB_PATH);
			}
			break;
		}
		$this->_objTpl->parse('fileBrowser_content');
	}


	/**
	* Shows the upload-form in the filebrowser
	*/
	function _setUploadForm()
	{
	    global $_ARRAYLANG;
		$objFWSystem = &new FWSystem();

		$this->_objTpl->addBlockfile('FILEBROWSER_UPLOAD', 'fileBrowser_upload', 'module_fileBrowser_upload.html');
		$this->_objTpl->setVariable(array(
			'FILEBROWSER_UPLOAD_TYPE'	=> $this->_mediaType,
			'FILEBROWSER_UPLOAD_PATH'	=> $this->_path,
			'FILEBROWSER_MAX_FILE_SIZE'	=> $objFWSystem->getMaxUploadFileSize(),
			'TXT_UPLOAD_FILE'			=> $_ARRAYLANG['TXT_FILEBROWSER_UPLOAD_FILE'],
			'TXT_CREATE_DIRECTORY'      => $_ARRAYLANG['TXT_FILEBROWSER_CREATE_DIRECTORY'],
		));

		$this->_objTpl->parse('fileBrowser_upload');
	}


	/**
	* Read all files / directories of the current folder
	*
	*/
	function _initFiles()
	{

		switch($this->_mediaType) {
			case 'media1':
				$strPath = ASCMS_MEDIA1_PATH.$this->_path;
			break;
			case 'media2':
				$strPath = ASCMS_MEDIA2_PATH.$this->_path;
			break;
			case 'media3':
				$strPath = ASCMS_MEDIA3_PATH.$this->_path;
			break;
			case 'media4':
				$strPath = ASCMS_MEDIA4_PATH.$this->_path;
			break;
			case 'shop':
			    $strPath = ASCMS_SHOP_IMAGES_PATH.$this->_path;
			break;
			default:
				$strPath = ASCMS_CONTENT_IMAGE_PATH.$this->_path;
		}

		$objDir = @opendir($strPath);

		$arrFiles = array();

		if ($objDir) {
			if ($this->_path !== "/" && preg_match('#(.*/).+[/]?$#',$this->_path, $path)) {
				array_push($this->_arrDirectories, array('name' => '..', 'path' => $path[1], 'icon' => $this->_iconPath.'_folder.gif'));
			}

			while ($file = readdir($objDir)) {
				if ($file == '.' || $file == '..' || preg_match('/\.thumb$/', $file) || $file == 'index.php') {
					continue;
				}
				array_push($arrFiles, $file);
			}
			closedir($objDir);

			sort($arrFiles);

			foreach ($arrFiles as $file) {
				if (is_dir($strPath.$file)) {
					array_push($this->_arrDirectories, array('name' => $file, 'path' => $this->_path.$file, 'icon' => $this->_getIcon($strPath.$file)));
				} else {
					$filesize = @filesize($strPath.$file);
					if ($filesize > 0) {
						$filesize = round($filesize/1024);
					} else {
						$filesize = 0;
					}
					$arrDimensions = @getimagesize($strPath.$file);
					array_push($this->_arrFiles, array('name' => $file, 'path' => $this->_path.$file, 'size' => $filesize, 'icon' => $this->_getIcon($strPath.$file), 'width' => intval($arrDimensions[0]), 'height' => intval($arrDimensions[1])));
				}
			}
		}
	}


	/**
	* Search the icon for a file
	*
	* @param  string $file: The icon of this file will be searched
	*/
    function _getIcon($file)
    {
        if (is_file($file)) {
            $info = pathinfo($file);
		    $icon = strtolower($info['extension']);
        }

        if (is_dir($file)) {
            $icon = '_folder';
        }

        if (!file_exists(ASCMS_MODULE_IMAGE_PATH.'/fileBrowser/'.$icon.'.gif') or !isset($icon)){
			$icon = '_blank';
		}
		return $this->_iconPath.$icon.'.gif';
    }




    /**
     * Create html-source of a complete <select>-navigation
     *
     * @param string $name: name of the <select>-tag
     * @param string $selectedType: which <option> will be "selected"?
     * @param string $attrs: further attributes of the <select>-tag
     * @return string html-source
     */
    function _getMediaTypeMenu($name, $selectedType, $attrs)
    {
    	global $_ARRAYLANG;

    	$menu = "<select name=".$name." ".$attrs.">";
    	foreach ($this->_arrMediaTypes as $type => $text) {
    	    if($type == 'shop' && !$this->_shopEnabled){
    	        continue;
    	    }
			$menu .= "<option value=\"".$type."\"".($selectedType == $type ? " selected=\"selected\"" : "").">".$_ARRAYLANG[$text]."</option>\n";
    	}
		$menu .= "</select>";
		return $menu;
    }
}
?>
