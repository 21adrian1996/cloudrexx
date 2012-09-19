<?php
include_once ASCMS_MODULE_PATH . "/filesharing/lib/FilesharingLib.class.php";
/**
 * Filesharing module
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team <info@comvation.com>
 * @version     1.0.0
 * @package     contrexx
 * @subpackage  filesharing
 */

class Filesharing extends FilesharingLib
{
    private $_objTpl;

    public function __construct(&$objTpl, $loadTemplate=true) {
        global $_ARRAYLANG, $objInit;
        $_ARRAYLANG = array_merge($_ARRAYLANG, $objInit->loadLanguageData('filesharing'));

        $this->_objTpl = $objTpl;
        if ($loadTemplate) {
            $this->_objTpl->loadTemplateFile('module_media_filesharing.html', true, true);
        }
        JS::activate("cx");
    }

    public function getDetailPage() {
        global $_ARRAYLANG, $objDatabase;
        $file = str_replace(ASCMS_PATH_OFFSET, '', $_GET["path"]) . $_GET["file"];
        $objResult = $objDatabase->Execute("SELECT `id`, `file`, `source`, `hash`, `check`, `expiration_date` FROM " . DBPREFIX . "module_filesharing WHERE `source` = ?", array($file));

        $existing = $objResult !== false && $objResult->RecordCount() > 0;
        if ($_GET["switch"]) {
            if ($existing) {
                $objDatabase->Execute("DELETE FROM " . DBPREFIX . "module_filesharing WHERE `source` = ?", array($file));
            } else {
                $hash = FilesharingLib::createHash();
                $check = FilesharingLib::createCheck($hash);
                $source = str_replace(ASCMS_PATH_OFFSET, '', $_GET["path"]) . $_GET["file"];
                $objDatabase->Execute("INSERT INTO " . DBPREFIX . "module_filesharing (`file`, `source`, `hash`, `check`) VALUES (?, ?, ?, ?)", array($source, $source, $hash, $check));
            }

            $existing = !$existing;
        }

        if ($existing) {
            $this->_objTpl->setVariable(array(
                'FILE_STATUS' => $_ARRAYLANG["TXT_FILESHARING_SHARED"],
                'FILE_STATUS_SWITCH' => $_ARRAYLANG["TXT_FILESHARING_STOP_SHARING"],
                'FILE_STATUS_SWITCH_HREF' => 'index.php?cmd=media&amp;archive=filesharing&amp;act=filesharing&amp;path=' . $_GET["path"] . '&amp;file=' . $_GET["file"] . '&amp;switch=1',
            ));
            $this->_objTpl->touchBlock('shared');
        } else {
            $this->_objTpl->setVariable(array(
                'FILE_STATUS' => $_ARRAYLANG["TXT_FILESHARING_NOT_SHARED"],
                'FILE_STATUS_SWITCH' => $_ARRAYLANG["TXT_FILESHARING_START_SHARING"],
                'FILE_STATUS_SWITCH_HREF' => 'index.php?cmd=media&amp;archive=filesharing&amp;act=filesharing&amp;path=' . $_GET["path"] . '&amp;file=' . $_GET["file"] . '&amp;switch=1',
            ));
            $this->_objTpl->hideBlock('shared');
        }

        if ($_POST["shareFiles"]) {
            if (FWValidator::isEmail($_POST["email"])) {
                FilesharingLib::sendMail($objResult->fields["id"], $_POST["subject"], $_POST["email"], $_POST["message"]);
            }
        } elseif ($_POST["saveExpiration"]) {
            if ($_POST["expiration"]) {
                $objDatabase->Execute("UPDATE " . DBPREFIX . "module_filesharing SET `expiration_date` = NULL WHERE `id` = " . $objResult->fields["id"]);
            } else {
                $objDatabase->Execute("UPDATE " . DBPREFIX . "module_filesharing SET `expiration_date` = ? WHERE `id` = " . $objResult->fields["id"], array(date('Y-m-d H:i:s', strtotime($_POST["expirationDate"]))));
            }
        }

        $objResult = $objDatabase->Execute("SELECT `id`, `hash`, `check`, `expiration_date` FROM " . DBPREFIX . "module_filesharing WHERE `source` = ?", array($file));

        $this->_objTpl->setVariable(array(
            'FORM_ACTION' => 'index.php?cmd=media&amp;archive=filesharing&amp;act=filesharing&amp;path=' . $_GET["path"] . '&amp;file=' . $_GET["file"],
            'FORM_METHOD' => 'POST',

            'FILESHARING_INFO' => $_ARRAYLANG['TXT_FILESHARING_INFO'],
            'FILESHARING_LINK_BACK_HREF' => 'index.php?cmd=media&amp;archive=filesharing&amp;path=' . $_GET["path"],
            'FILESHARING_LINK_BACK' => $_ARRAYLANG['TXT_FILESHARING_LINK_BACK'],
            'FILESHARING_DOWNLOAD_LINK' => $_ARRAYLANG['TXT_FILESHARING_DOWNLOAD_LINK'],
            'FILE_DOWNLOAD_LINK_HREF' => FilesharingLib::getDownloadLink($objResult->fields["id"]),
            'FILE_DELETE_LINK_HREF' => FilesharingLib::getDeleteLink($objResult->fields["id"]),
            'FILESHARING_DELETE_LINK' => $_ARRAYLANG['TXT_FILESHARING_DELETE_LINK'],
            'FILESHARING_STATUS' => $_ARRAYLANG['TXT_FILESHARING_STATUS'],

            'FILESHARING_EXPIRATION' => $_ARRAYLANG['TXT_FILESHARING_EXPIRATION'],
            'FILESHARING_NEVER' => $_ARRAYLANG['TXT_FILESHARING_NEVER'],
            'FILESHARING_EXPIRATION_CHECKED' => htmlentities($objResult->fields["expiration_date"] == NULL ? 'checked="checked"' : '', ENT_QUOTES, CONTREXX_CHARSET),
            'FILESHARING_EXPIRATION_DATE' => htmlentities($objResult->fields["expiration_date"] != NULL ?
                date('d.m.Y H:i', strtotime($objResult->fields["expiration_date"])) : date('d.m.Y H:i', time()+3600*24*7), ENT_QUOTES, CONTREXX_CHARSET),

            'FILESHARING_SEND_MAIL' => $_ARRAYLANG['TXT_FILESHARING_SEND_MAIL'],
            'FILESHARING_EMAIL' => $_ARRAYLANG["TXT_FILESHARING_EMAIL"],
            'FILESHARING_EMAIL_INFO' => $_ARRAYLANG["TXT_FILESHARING_EMAIL_INFO"],
            'FILESHARING_SUBJECT' => $_ARRAYLANG["TXT_FILESHARING_SUBJECT"],
            'FILESHARING_SUBJECT_INFO' => $_ARRAYLANG["TXT_FILESHARING_SUBJECT_INFO"],
            'FILESHARING_MESSAGE' => $_ARRAYLANG["TXT_FILESHARING_MESSAGE"],
            'FILESHARING_MESSAGE_INFO' => $_ARRAYLANG["TXT_FILESHARING_MESSAGE_INFO"],
            'FILESHARING_SEND' => $_ARRAYLANG["TXT_FILESHARING_SEND"],
            'FILESHARING_SAVE' => $_ARRAYLANG["TXT_FILESHARING_SAVE"],
        ));
    }

    public function parseSettingsPage() {
        global $_ARRAYLANG;

        $this->_objTpl->setVariable(array(
            'FILESHARING_INFO' => $_ARRAYLANG["TXT_FILESHARING_SETTINGS_GENERAL_INFORMATION"],
            'FILESHARING_MAIL_TEMPLATES' => $_ARRAYLANG["TXT_FILESHARING_MAIL_TEMPLATES"],

            'TXT_FILESHARING_APPLICATION_NAME' => $_ARRAYLANG["TXT_FILESHARING_SETTINGS_GENERAL_MODULE_NAME_TITLE"],
            'FILESHARING_APPLICATION_NAME' => $_ARRAYLANG["TXT_FILESHARING_MODULE"],
            'TXT_FILESHARING_DESCRIPTION' => $_ARRAYLANG["TXT_FILESHARING_SETTINGS_GENERAL_MODULE_DESCRIPTION_TITLE"],
            'FILESHARING_DESCRIPTION' => $_ARRAYLANG["TXT_FILESHARING_SETTINGS_GENERAL_MODULE_DESCRIPTION"],
            'TXT_FILESHARING_MANUAL' => $_ARRAYLANG["TXT_FILESHARING_SETTINGS_GENERAL_MODULE_MANUAL_TITLE"],
            'FILESHARING_MANUAL' => $_ARRAYLANG["TXT_FILESHARING_SETTINGS_GENERAL_MODULE_MANUAL"],
        ));
    }
}

?>