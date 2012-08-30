<?php

require_once(dirname(dirname(dirname(__FILE__))).'/config/configuration.php');
require_once(dirname(dirname(dirname(__FILE__))).'/core/ClassLoader/ClassLoader.class.php');
new \Cx\Core\ClassLoader\ClassLoader();

require_once('../../lib/DBG.php');
require_once('../../config/configuration.php');
require_once('../../core/settings.class.php');
require_once('../../core/API.php');
require_once('../../core/validator.inc.php');
require_once('../../lib/CSRF.php');
require_once('../../core/Html.class.php');

$strErrMessage = '';
$objDatabase = getDatabaseObject($strErrMessage);
$objSettings = new settingsManager();
$objInit = new InitCMS('backend');
$sessionObj = new cmsSession();
$sessionObj->cmsSessionStatusUpdate('backend');
$CSRF = '&'.CSRF::key().'='.CSRF::code();


$langId = !empty($_GET['langId']) ? $_GET['langId'] : null;
$absoluteURIs = !empty($_GET['absoluteURIs']) ? $_GET['absoluteURIs'] : null;

//'&' must not be htmlentities, used in javascript
$defaultBrowser   = ASCMS_BACKEND_PATH.'/'.CONTREXX_DIRECTORY_INDEX
                   .'?cmd=fileBrowser&standalone=true&langId='.$langId
                   .'&absoluteURIs='.$absoluteURIs.$CSRF;
$linkBrowser      = ASCMS_BACKEND_PATH.'/'.CONTREXX_DIRECTORY_INDEX
                   .'?cmd=fileBrowser&standalone=true&langId='.$langId
                   .'&absoluteURIs='.$absoluteURIs.'&type=webpages'.$CSRF;
$defaultUploader  = ASCMS_BACKEND_PATH.'/'.CONTREXX_DIRECTORY_INDEX.'?cmd=fileBrowser'
                   .'&act=FCKEditorUpload&standalone=true'.$CSRF;
$linkUploader     = ASCMS_BACKEND_PATH.'/'.CONTREXX_DIRECTORY_INDEX.'?cmd=fileBrowser'
                   .'&act=FCKEditorUpload&standalone=true&type=webpages'.$CSRF;

?>
CKEDITOR.editorConfig = function( config )
{
    config.skin = 'kama';

    config.height = 307;
    config.uiColor = '#ececec';

    config.forcePasteAsPlainText = false;
    config.enterMode = CKEDITOR.ENTER_BR;
    config.shiftEnterMode = CKEDITOR.ENTER_P;
    config.startupOutlineBlocks = true;

    config.filebrowserBrowseUrl      = CKEDITOR.getUrl('../..<?php echo $linkBrowser;?>');
    config.filebrowserImageBrowseUrl = CKEDITOR.getUrl('../..<?php echo $defaultBrowser?>');
    config.filebrowserFlashBrowseUrl = CKEDITOR.getUrl('../..<?php echo $defaultBrowser?>');
    config.filebrowserUploadUrl      = CKEDITOR.getUrl('../..<?php echo $linkUploader;?>')
    config.filebrowserImageUploadUrl = CKEDITOR.getUrl('../..<?php echo $defaultUploader;?>');
    config.filebrowserFlashUploadUrl = CKEDITOR.getUrl('../..<?php echo $defaultUploader;?>');

    config.toolbar_Default = [
        ['Source','-','NewPage','Preview','-','Templates'],
        ['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print','SpellChecker'],
        ['Undo','Redo','-','Replace','-','SelectAll','RemoveFormat'],
        ['Bold','Italic','Underline','Strike','-','Subscript','Superscript'],
        ['NumberedList','BulletedList','-','Outdent','Indent', 'Blockquote'],
        ['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
        ['Link','Unlink','Anchor'],
        ['Image','Table','HorizontalRule','SpecialChar'],
        ['Format'],
        ['TextColor','BGColor'],
        ['Maximize', 'ShowBlocks']
    ]

    config.toolbar_News = [
        ['NewPage','Preview'],
        ['Cut','Copy','Paste','PasteText','PasteFromWord','-','Print','SpellChecker'],
        ['Undo','Redo','-','Find','Replace','-','SelectAll','RemoveFormat'],
        ['Bold','Italic','Underline','StrikeThrough','-','Subscript','Superscript'],
        ['OrderedList','UnorderedList','-','Outdent','Indent'],
        ['JustifyLeft','JustifyCenter','JustifyRight','JustifyBlock'],
        ['Table','HorizontalRule','Smiley','SpecialChar'],
        ['Maximize']
    ];

    config.toolbar_BBCode = [
        ['Source'],
        ['Bold','Italic','Underline','StrikeThrough','-','Link','Unlink', 'SpecialChar'],
    ];
};

//remove tab "upload" in link and image dialog
CKEDITOR.on('dialogDefinition', function(ev) {
    var dialogName = ev.data.name, dialogDefinition = ev.data.definition;
    if (dialogName === 'image') {
        dialogDefinition.removeContents('Upload');
    }
    if (dialogName === 'link') {
        dialogDefinition.removeContents('upload');
    }
});
