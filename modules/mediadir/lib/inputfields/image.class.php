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
require_once(ASCMS_FRAMEWORK_PATH.DIRECTORY_SEPARATOR.'Image.class.php');
require_once ASCMS_MODULE_PATH . '/mediadir/lib/inputfields/inputfield.interface.php';
require_once ASCMS_MODULE_PATH . '/mediadir/lib/lib.class.php';


class mediaDirectoryInputfieldImage extends mediaDirectoryLibrary implements inputfield
{
    public $arrPlaceholders = array('TXT_MEDIADIR_INPUTFIELD_NAME','MEDIADIR_INPUTFIELD_VALUE','MEDIADIR_INPUTFIELD_VALUE_SRC','MEDIADIR_INPUTFIELD_VALUE_SRC_THUMB','MEDIADIR_INPUTFIELD_VALUE_POPUP','MEDIADIR_INPUTFIELD_VALUE_IMAGE','MEDIADIR_INPUTFIELD_VALUE_THUMB', 'MEDIADIR_INPUTFIELD_VALUE_FILENAME');

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
                    $strValue = htmlspecialchars($objInputfieldValue->fields['value'], ENT_QUOTES, CONTREXX_CHARSET);
                } else {
                    $strValue = null;
                }

                if(!empty($strValue) && file_exists(ASCMS_PATH.$strValue.".thumb")) {
                    $objInit->mode == 'backend' ? $style = 'style="border: 1px solid rgb(10, 80, 161); margin: 0px 0px 3px;"' : '';
                    $strImagePreview = '<img src="'.$strValue.'.thumb" alt="" '.$style.'  width="'.intval($this->arrSettings['settingsThumbSize']).'"/>&nbsp;<input type="checkbox" value="1" name="deleteMedia['.$intId.']" />'.$_ARRAYLANG['TXT_MEDIADIR_DELETE'].'<br />';
                } else {
                    $strImagePreview = null;
                }

                if(empty($strValue) || $strValue == "new_image") {
                    $strValueHidden = "new_image";
                    $strValue = "";
                } else {
                    $strValueHidden = $strValue;
                }

                if($objInit->mode == 'backend') {
                    $strInputfield = $strImagePreview.'<input type="text" name="'.$this->moduleName.'Inputfield['.$intId.']" value="'.$strValue.'" id="'.$this->moduleName.'Inputfield_'.$intId.'" style="width: 300px;" onfocus="this.select();" />&nbsp;<input type="button" value="Durchsuchen" onClick="getFileBrowser(\''.$this->moduleName.'Inputfield_'.$intId.'\', \''.$this->moduleName.'\', \'/images\')" />';
                } else {
                    $strInputfield = $strImagePreview.'<input type="file" name="imageUpload_'.$intId.'" id="'.$this->moduleName.'Inputfield_'.$intId.'" class="'.$this->moduleName.'InputfieldImage" value="'.$strValue.'" onfocus="this.select();" /><input name="'.$this->moduleName.'Inputfield['.$intId.']" value="'.$strValueHidden.'" type="hidden">';
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

        if($objInit->mode == 'backend') {
            if ($_POST["deleteMedia"][$intInputfieldId] != 1) {
                $this->checkThumbnail($strValue);
                $strValue = contrexx_addslashes($strValue);
            } else {
                $strValue = null;
            }
        } else {
            if (!empty($_FILES['imageUpload_'.$intInputfieldId]['name']) || $_POST["deleteMedia"][$intInputfieldId] == 1) {
                //delete image & thumb
                $this->deleteImage($strValue);

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

        return $strValue;
    }


    function checkThumbnail($strPathImage)
    {
        if (!file_exists(ASCMS_PATH.$strPathImage.".thumb")) {
            $this->createThumbnail($strPathImage);
        }
    }

    function deleteImage($strPathImage)
    {
        if(!empty($strPathImage)) {
            $objFile = new File();
            $arrImageInfo = pathinfo($strPathImage);
            $imageName    = $arrImageInfo['basename'];

            //delete thumb
            if (file_exists(ASCMS_PATH.$strPathImage.".thumb")) {
                $objFile->delFile($this->imagePath, $this->imageWebPath, 'images/'.$imageName.".thumb");
            }

            //delete image
            if (file_exists(ASCMS_PATH.$strPathImage)) {
                $objFile->delFile($this->imagePath, $this->imageWebPath, 'images/'.$imageName);
            }
        }
    }


    function uploadMedia($intInputfieldId)
    {
        global $objDatabase;

        if (isset($_FILES)) {
            $tmpImage   = $_FILES['imageUpload_'.$intInputfieldId]['tmp_name'];
            $imageName  = $_FILES['imageUpload_'.$intInputfieldId]['name'];
            $imageType  = $_FILES['imageUpload_'.$intInputfieldId]['type'];
            $imageSize  = $_FILES['imageUpload_'.$intInputfieldId]['size'];

            if ($imageName != "") {
                //get extension
                $arrImageInfo   = pathinfo($imageName);
                $imageExtension = !empty($arrImageInfo['extension']) ? '.'.$arrImageInfo['extension'] : '';
                $imageBasename  = $arrImageInfo['filename'];
                $randomSum      = rand(10, 99);

                //encode filename
                if ($this->arrSettings['settingsEncryptFilenames'] == 1) {
                    $imageName = md5($randomSum.$imageBasename).$imageExtension;
                }

                //check filename
                if (file_exists($this->imagePath.'images/'.$imageName)) {
                    $imageName = $imageBasename.'_'.time().$imageExtension;
                }

                //upload file
                if (move_uploaded_file($tmpImage, $this->imagePath.'images/'.$imageName)) {
                    $objFile = new File();
                    $objFile->setChmod($this->imagePath, $this->imageWebPath, 'images/'.$imageName);

                    //create thumbnail
                    $this->checkThumbnail($this->imageWebPath.'images/'.$imageName);

                    return contrexx_addslashes($this->imageWebPath.'images/'.$imageName);
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


    function createThumbnail($strPathImage)
    {
        global $objDatabase;

        $arrImageInfo = getimagesize(ASCMS_PATH.$strPathImage);

        if ($arrImageInfo['mime'] == "image/gif" || $arrImageInfo['mime'] == "image/jpeg" || $arrImageInfo['mime'] == "image/jpg" || $arrImageInfo['mime'] == "image/png") {
            $objImage = &new ImageManager();

            $arrImageInfo = array_merge($arrImageInfo, pathinfo($strPathImage));

            $thumbWidth = intval($this->arrSettings['settingsThumbSize']);
            $thumbHeight = intval($thumbWidth / $arrImageInfo[0] * $arrImageInfo[1]);

            $objImage->loadImage(ASCMS_PATH.$strPathImage);
            $objImage->resizeImage($thumbWidth, $thumbHeight, 100);
            $objImage->saveNewImage(ASCMS_PATH.$strPathImage . '.thumb');
        }
    }


    function deleteContent($intEntryId, $intIputfieldId)
    {
        global $objDatabase;

        //get image path
        $objImagePathRS = $objDatabase->Execute("SELECT value FROM ".DBPREFIX."module_".$this->moduleTablePrefix."_rel_entry_inputfields WHERE `entry_id`='".intval($intEntryId)."' AND  `field_id`='".intval($intIputfieldId)."'");
        $strImagePath   = $objImagePathRS->fields['value'];

        //delete relation
        $objDeleteInputfieldRS = $objDatabase->Execute("DELETE FROM ".DBPREFIX."module_".$this->moduleTablePrefix."_rel_entry_inputfields WHERE `entry_id`='".intval($intEntryId)."' AND  `field_id`='".intval($intIputfieldId)."'");

        if($objDeleteInputfieldRS !== false) {
            //delete image
            //$this->deleteImage($strImagePath);
            return true;
        } else {
            return false;
        }
    }



    function getContent($intEntryId, $arrInputfield, $arrTranslationStatus)
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

        $strValue = strip_tags(htmlspecialchars($objInputfieldValue->fields['value'], ENT_QUOTES, CONTREXX_CHARSET));

        if(!empty($strValue) && $strValue != 'new_image') {
            $arrImageInfo   = getimagesize(ASCMS_PATH.$strValue);
            $imageWidth     = $arrImageInfo[0]+20;
            $imageHeight    = $arrImageInfo[1]+20;
            $arrImageInfo   = pathinfo($strValue);
            $strImageName    = $arrImageInfo['basename'];

            $arrContent['TXT_'.$this->moduleLangVar.'_INPUTFIELD_NAME'] = htmlspecialchars($arrInputfield['name'][0], ENT_QUOTES, CONTREXX_CHARSET);
            $arrContent[$this->moduleLangVar.'_INPUTFIELD_VALUE'] = '<a rel="shadowbox[1];options={slideshowDelay:5}"  href="'.$strValue.'" width="'.intval($this->arrSettings['settingsThumbSize']).'"><img src="'.$strValue.'.thumb" alt="" border="0" title="" /></a>';
            $arrContent[$this->moduleLangVar.'_INPUTFIELD_VALUE_SRC'] = $strValue;
            $arrContent[$this->moduleLangVar.'_INPUTFIELD_VALUE_FILENAME'] = $strImageName;
            $arrContent[$this->moduleLangVar.'_INPUTFIELD_VALUE_SRC_THUMB'] = $strValue.".thumb";
            $arrContent[$this->moduleLangVar.'_INPUTFIELD_VALUE_POPUP'] = '<a href="'.$strValue.'" onclick="window.open(this.href,\'\',\'resizable=no,location=no,menubar=no,scrollbars=no,status=no,toolbar=no,fullscreen=no,dependent=no,width='.$imageWidth.',height='.$imageHeight.',status\'); return false"><img src="'.$strValue.'.thumb" title="'.$arrInputfield['name'][0].'" width="'.intval($this->arrSettings['settingsThumbSize']).'" alt="'.$arrInputfield['name'][0].'" border="0" /></a>';
            $arrContent[$this->moduleLangVar.'_INPUTFIELD_VALUE_IMAGE'] = '<img src="'.$strValue.'" title="'.$arrInputfield['name'][0].'" alt="'.$arrInputfield['name'][0].'" />';
            $arrContent[$this->moduleLangVar.'_INPUTFIELD_VALUE_THUMB'] = '<img src="'.$strValue.'.thumb" width="'.intval($this->arrSettings['settingsThumbSize']).'" title="'.$arrInputfield['name'][0].'" alt="'.$arrInputfield['name'][0].'" />';
        } else {
            $arrContent = null;
        }

        return $arrContent;
    }


    function getJavascriptCheck()
    {
        $strJavascriptCheck = <<<EOF

            case 'image':
                break;

EOF;
        return $strJavascriptCheck;
    }
}