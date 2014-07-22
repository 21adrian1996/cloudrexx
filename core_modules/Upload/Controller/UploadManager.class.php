<?php

/**
 * Upload
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      COMVATION Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  coremodule_upload
 */
namespace Cx\Core_Modules\Upload\Controller;
/**
 * Upload
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      COMVATION Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  coremodule_upload
 */
class UploadManager extends UploadLib
{
    public function getPage()
    {
        $act = '';
        if(isset($_REQUEST['act'])) {
            $act = $_REQUEST['act'];
        }
        switch($act) {
            //uploaders
            case 'upload': //an uploader is sending data
                $this->upload();
                break;
            case 'ajaxUploaderCode': //a js combouploader requests code of another uploader type
                $this->ajaxUploaderCode();
                break;
            //uploaders - formuploader
            case 'formUploaderFrame': //send the formuploader iframe content
                $this->formUploaderFrame();
                break;
            case 'formUploaderFrameFinished': //send the formuploader iframe content
                $this->formUploaderFrameFinished();
                break;
            //uploaders - jumploader
            case 'jumpUploaderApplet': //send the jumpUploader applet
                $this->jumpUploaderApplet();
                break;
            case 'jumpUploaderL10n': //send the jumpUploader messages
                $this->jumpUploaderL10n(basename($_GET['lang']));
                break;
            case 'response':
                $this->response($_GET['upload']);
                break;
          
            //folderWidget
            case 'refreshFolder':
                $this->refreshFolder();
                break;
            case 'deleteFile': //a folderWidget wants to delete something
                $this->deleteFile();
                break;
        }        
    }
}
