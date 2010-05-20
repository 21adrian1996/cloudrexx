<?php
/**
 * Media  Directory Inputfield Text Class
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  module_mediadir
 * @todo        Edit PHP DocBlocks!
 */

/**
 * Includes
 */
require_once ASCMS_MODULE_PATH . '/mediadir/lib/inputfields/inputfield.interface.php';
require_once ASCMS_MODULE_PATH . '/mediadir/lib/lib.class.php';

class mediaDirectoryInputfieldFile extends mediaDirectoryLibrary implements inputfield
{
    public $arrPlaceholders = array('TXT_MEDIADIR_INPUTFIELD_NAME','MEDIADIR_INPUTFIELD_VALUE','MEDIADIR_INPUTFIELD_VALUE_SRC', 'MEDIADIR_INPUTFIELD_VALUE_NAME', 'MEDIADIR_INPUTFIELD_VALUE_FILENAME');

    private $imagePath;
    private $imageWebPath;

    /**
     * Constructor
     */
    function __construct()
    {
        $this->imagePath = ASCMS_MEDIADIR_IMAGES_PATH .'/';
        $this->imageWebPath = ASCMS_MEDIADIR_IMAGES_WEB_PATH .'/';
        parent::getSettings();
    }



    function getInputfield($intView, $arrInputfield, $intEntryId=null)
    {
        global $objDatabase, $_ARRAYLANG, $_LANGID, $objInit;

        switch ($intView) {
            default:
            case 1:
                //modify (add/edit) View
                $intId = intval($arrInputfield['id']);

                if(isset($intEntryId) && $intEntryId != 0) {
                    $objInputfieldValue = $objDatabase->Execute("
                        SELECT
                            `value`
                        FROM
                            ".DBPREFIX."module_".$this->moduleTablePrefix."_rel_entry_inputfields
                        WHERE
                            field_id=".$intId."
                        AND
                            entry_id=".$intEntryId."
                        LIMIT 1
                    ");
                    //$arrValue = htmlspecialchars($objInputfieldValue->fields['value'], ENT_QUOTES, CONTREXX_CHARSET);
                    
                    $arrValue = explode(",",$objInputfieldValue->fields['value']);
                    $strValue = $arrValue[0];
                } else {
                    $arrValue = null;
                }

                if(!empty($strValue) && file_exists(ASCMS_PATH.$strValue)) {
                    $arrFileInfo    = pathinfo($strValue);
                    $strFileName    = htmlspecialchars($arrFileInfo['basename'], ENT_QUOTES, CONTREXX_CHARSET);
                    
	                if(empty($arrValue[1])) {
		                $strName = $strFileName;
		            } else {
		                $strName = strip_tags(htmlspecialchars($arrValue[1], ENT_QUOTES, CONTREXX_CHARSET));
		            }
		            
		            

                    $strFilePreview = '<a href="'.urldecode($strValue).'" target="_blank">'.$strFileName.'</a>&nbsp;<input type="checkbox" value="1" name="deleteMedia['.$intId.']" />'.$_ARRAYLANG['TXT_MEDIADIR_DELETE'].'<br />';
                } else {
                    $strFilePreview = null;
                }

                if(empty($strValue) || $strValue == "new_file") {
                    $strValueHidden = "new_file";
                    $strValue = "";
                } else {
                    $strValueHidden = $strValue;
                }

                if($objInit->mode == 'backend') {
                    $strInputfield = $strFilePreview.'<input type="text" name="'.$this->moduleName.'Inputfield['.$intId.'][file]" value="'.$strValue.'" id="'.$this->moduleName.'Inputfield_'.$intId.'" style="width: 300px;" onfocus="this.select();" />&nbsp;<input type="button" value="Durchsuchen" onClick="getFileBrowser(\'mediadirInputfield_'.$intId.'\', \'mediadir\', \'/uploads\')" />';
                    $strInputfield .= '<br /><input type="text" name="'.$this->moduleName.'Inputfield['.$intId.'][name]" value="'.$strName.'" id="'.$this->moduleName.'Inputfield_'.$intId.'_name" style="width: 300px;" onfocus="this.select();" />&nbsp;<i>'.$_ARRAYLANG['TXT_MEDIADIR_DISPLAYNAME'].'</i>';
                
                } else {
                    $strInputfield = $strFilePreview.'<input type="file" name="fileUpload_'.$intId.'" id="'.$this->moduleName.'Inputfield_'.$intId.'" class="'.$this->moduleName.'InputfieldFile" value="'.$strValue.'" onfocus="this.select();" /><input name="'.$this->moduleName.'Inputfield['.$intId.'][file]" value="'.$strValueHidden.'" type="hidden">';
                    $strInputfield .= '<br /><input type="text" name="'.$this->moduleName.'Inputfield['.$intId.'][name]" value="'.$strName.'" id="'.$this->moduleName.'Inputfield_'.$intId.'_name" style="width: 300px;" onfocus="this.select();" />&nbsp;<i>'.$_ARRAYLANG['TXT_MEDIADIR_DISPLAYNAME'].'</i>';
                }

                return $strInputfield;

                break;
            case 2:
                //search View
                break;
        }
    }



    function saveInputfield($intInputfieldId, $strValue)
    {
        global $objInit;
        
        
        $strValue = contrexx_addslashes($_POST[$this->moduleName.'Inputfield'][$intInputfieldId]['file']);
        
        if(!empty($_POST[$this->moduleName.'Inputfield'][$intInputfieldId]['name'])) {
        	$strName = ",".contrexx_addslashes($_POST[$this->moduleName.'Inputfield'][$intInputfieldId]['name']);
        }

        if($objInit->mode == 'backend') {
            if ($_POST["deleteMedia"][$intInputfieldId] != 1) {
                $strValue = contrexx_addslashes($strValue);
            } else {
                $strValue = null;
            }
        } else {
            if (!empty($_FILES['fileUpload_'.$intInputfieldId]['name']) || $_POST["deleteMedia"][$intInputfieldId] == 1) {
                //delete file
                $this->deleteFile($strValue);

                if ($_POST["deleteMedia"][$intInputfieldId] != 1) {
                    //upload image
                    $strValue = $this->uploadMedia($intInputfieldId);
                } else {
                    $strValue = null;
                }
            } else {
                $strValue = contrexx_addslashes($strValue);
            }
        }

        return $strValue.$strName;
    }



    function deleteFile($strPathFile)
    {
        if(!empty($strPathImage)) {
            $objFile = new File();
            $arrFileInfo = pathinfo($strPathFile);
            $fileName    = $arrFileInfo['basename'];

            //delete file
            if (file_exists(ASCMS_PATH.$strPathFile)) {
                $objFile->delFile($this->imagePath, $this->imageWebPath, 'uploads/'.$fileName);
            }
        }
    }


    function uploadMedia($intInputfieldId)
    {
        global $objDatabase;

        if (isset($_FILES)) {
            $tmpFile   = $_FILES['fileUpload_'.$intInputfieldId]['tmp_name'];
            $fileName  = $_FILES['fileUpload_'.$intInputfieldId]['name'];
            $fileType  = $_FILES['fileUpload_'.$intInputfieldId]['type'];
            $fileSize  = $_FILES['fileUpload_'.$intInputfieldId]['size'];

            if ($fileName != "") {
                //get extension
                $arrFileInfo   = pathinfo($fileName);
                $fileExtension = !empty($arrFileInfo['extension']) ? '.'.$arrFileInfo['extension'] : '';
                $fileBasename  = $arrFileInfo['filename'];
                $randomSum      = rand(10, 99);

                //encode filename
                if ($this->arrSettings['settingsEncryptFilenames'] == 1) {
                    $fileName = md5($randomSum.$fileBasename).$fileExtension;
                }

                //check filename
                if (file_exists($this->imagePath.'uploads/'.$fileName)) {
                    $fileName = $fileBasename.'_'.time().$fileExtension;
                }

                //upload file
                if (move_uploaded_file($tmpFile, $this->imagePath.'uploads/'.$fileName)) {
                    $objFile = new File();
                    $objFile->setChmod($this->imagePath, $this->imageWebPath, 'uploads/'.$fileName);

                    return contrexx_addslashes($this->imageWebPath.'uploads/'.$fileName);
                } else {
                    return false;
                }
            } else {
                return false;
            }
        } else {
            return false;
        }
    }


    function deleteContent($intEntryId, $intIputfieldId)
    {
        global $objDatabase;

        //get file path
        $objFilePathRS = $objDatabase->Execute("SELECT value FROM ".DBPREFIX."module_".$this->moduleTablePrefix."_rel_entry_inputfields WHERE `entry_id`='".intval($intEntryId)."' AND  `field_id`='".intval($intIputfieldId)."'");
        $strFilePath   = $objFilePathRS->fields['value'];

        //delete relation
        $objDeleteInputfieldRS = $objDatabase->Execute("DELETE FROM ".DBPREFIX."module_".$this->moduleTablePrefix."_rel_entry_inputfields WHERE `entry_id`='".intval($intEntryId)."' AND  `field_id`='".intval($intIputfieldId)."'");

        if($objDeleteInputfieldRS !== false) {
            //delete image
            //$this->deleteFile($strFilePath);
            return true;
        } else {
            return false;
        }
    }



    function getContent($intEntryId, $arrInputfield)
    {
        global $objDatabase;

        $intId = intval($arrInputfield['id']);

        $objInputfieldValue = $objDatabase->Execute("
            SELECT
                `value`
            FROM
                ".DBPREFIX."module_".$this->moduleTablePrefix."_rel_entry_inputfields
            WHERE
                field_id=".$intId."
            AND
                entry_id=".$intEntryId."
            LIMIT 1
        ");

        $arrValue = explode(",",$objInputfieldValue->fields['value']);
        $strValue = strip_tags(htmlspecialchars($arrValue[0], ENT_QUOTES, CONTREXX_CHARSET));

        if(!empty($strValue) && $strValue != 'new_file') {
            $arrFileInfo    = pathinfo($strValue);
            $strFileName    = htmlspecialchars($arrFileInfo['basename'], ENT_QUOTES, CONTREXX_CHARSET);
            if(empty($arrValue[1])) {
            	$strName = $strFileName;
            } else {
                $strName = strip_tags(htmlspecialchars($arrValue[1], ENT_QUOTES, CONTREXX_CHARSET));
            }

            $arrContent['TXT_'.$this->moduleLangVar.'_INPUTFIELD_NAME'] = htmlspecialchars($arrInputfield['name'][0], ENT_QUOTES, CONTREXX_CHARSET);
            $arrContent[$this->moduleLangVar.'_INPUTFIELD_VALUE'] = '<a href="'.urldecode($strValue).'" alt="'.$strName.'" title="'.$strName.'" target="_blank">'.$strName.'</a>';
            $arrContent[$this->moduleLangVar.'_INPUTFIELD_VALUE_SRC'] = urldecode($strValue);
            $arrContent[$this->moduleLangVar.'_INPUTFIELD_VALUE_NAME'] = $strName;
            $arrContent[$this->moduleLangVar.'_INPUTFIELD_VALUE_FILENAME'] = $strFileName;
        } else {
            $arrContent = null;
        }

        return $arrContent;
    }


    function getJavascriptCheck()
    {
        $strJavascriptCheck = <<<EOF

            case 'file':
                break;

EOF;
        return $strJavascriptCheck;
    }
}