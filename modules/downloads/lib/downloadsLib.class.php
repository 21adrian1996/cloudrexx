<?php

/**
 * Digital Asset Management
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      COMVATION Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  module_downloads
 * @version     1.0.0
 */

/**
 * @ignore
 */
require_once dirname(__FILE__).'/Group.class.php';
/**
 * @ignore
 */
require_once dirname(__FILE__).'/Category.class.php';
/**
 * @ignore
 */
require_once dirname(__FILE__).'/Download.class.php';
/**
 * @ignore
 */
require_once dirname(__FILE__).'/Mail.class.php';
/**
 * @ignore
 */
require_once ASCMS_FRAMEWORK_PATH.'/System.class.php';
require_once ASCMS_LIBRARY_PATH.'/FRAMEWORK/Validator.class.php';

/**
 * Digital Asset Management Library
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      COMVATION Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  module_downloads
 * @version     1.0.0
 */
class DownloadsLibrary
{
    protected $defaultCategoryImage = array();
    protected $defaultDownloadImage = array();
    protected $arrPermissionTypes = array(
        'getReadAccessId'                   => 'read',
        'getAddSubcategoriesAccessId'       => 'add_subcategories',
        'getManageSubcategoriesAccessId'    => 'manage_subcategories',
        'getAddFilesAccessId'               => 'add_files',
        'getManageFilesAccessId'            => 'manage_files'
    );
    protected $searchKeyword;
    protected $arrConfig = array(
        'overview_cols_count'           => 2,
        'overview_max_subcats'          => 5,
        'use_attr_size'                 => 1,
        'use_attr_license'              => 1,
        'use_attr_version'              => 1,
        'use_attr_author'               => 1,
        'use_attr_website'              => 1,
        'most_viewed_file_count'        => 5,
        'most_downloaded_file_count'    => 5,
        'most_popular_file_count'       => 5,
        'newest_file_count'             => 5,
        'updated_file_count'            => 5,
        'new_file_time_limit'           => 604800,
        'updated_file_time_limit'       => 604800,
        'associate_user_to_groups'      => ''
    );


    public function __construct()
    {
        $this->initSettings();
        $this->initSearch();
        $this->initDefaultCategoryImage();
        $this->initDefaultDownloadImage();
    }


    private function initSettings()
    {
        global $objDatabase;

        $objResult = $objDatabase->Execute('SELECT `name`, `value` FROM `'.DBPREFIX.'module_downloads_settings`');
        if ($objResult) {
            while (!$objResult->EOF) {
                $this->arrConfig[$objResult->fields['name']] = $objResult->fields['value'];
                $objResult->MoveNext();
            }
        }
    }


    protected function initSearch()
    {
        $this->searchKeyword = empty($_REQUEST['downloads_search_keyword']) ? '' : $_REQUEST['downloads_search_keyword'];
    }


    protected function initDefaultCategoryImage()
    {
        $this->defaultCategoryImage['src'] = ASCMS_DOWNLOADS_IMAGES_WEB_PATH.'/no_picture.gif';

        $imageSize = getimagesize(ASCMS_PATH.$this->defaultCategoryImage['src']);

        $this->defaultCategoryImage['width'] = $imageSize[0];
        $this->defaultCategoryImage['height'] = $imageSize[1];
    }


    protected function initDefaultDownloadImage()
    {
        $this->defaultDownloadImage = $this->defaultCategoryImage;
    }


    protected function updateSettings()
    {
        global $objDatabase;

        foreach ($this->arrConfig as $key => $value) {
            $objDatabase->Execute("UPDATE `".DBPREFIX."module_downloads_settings` SET `value` = '".addslashes($value)."' WHERE `name` = '".$key."'");
        }
    }

    public function getSettings()
    {
        return $this->arrConfig;
    }

    protected function getCategoryMenu(
        $accessType, $selectedCategory, $selectionText,
        $attrs=null, $categoryId=null)
    {
        global $_LANGID;

        $objCategory = Category::getCategories(null, null, array('order' => 'ASC', 'name' => 'ASC', 'id' => 'ASC'));
        $arrCategories = array();

        switch ($accessType) {
            case 'read':
                $accessCheckFunction = 'getReadAccessId';
                break;

            case 'add_subcategory':
                $accessCheckFunction = 'getAddSubcategoriesAccessId';
                break;
        }

        while (!$objCategory->EOF) {
            // TODO: getVisibility() < should only be checked if the user isn't an admin or so
            if ($objCategory->getVisibility() || Permission::checkAccess($objCategory->getReadAccessId(), 'dynamic', true)) {
                $arrCategories[$objCategory->getParentId()][] = array(
                    'id'        => $objCategory->getId(),
                    'name'      => $objCategory->getName($_LANGID),
                    'owner_id'  => $objCategory->getOwnerId(),
                    'access_id' => $objCategory->{$accessCheckFunction}(),
                    'is_child'  => $objCategory->check4Subcategory($categoryId)
                );
            }

            $objCategory->next();
        }

        $menu = '<select name="downloads_category_parent_id"'.(!empty($attrs) ? ' '.$attrs : '').'>';
        $menu .= '<option value="0"'.(!$selectedCategory ? ' selected="selected"' : '').($accessType != 'read' && !Permission::checkAccess(142, 'static', true) ? ' disabled="disabled"' : '').' style="border-bottom:1px solid #000;">'.$selectionText.'</option>';

        $menu .= $this->parseCategoryTreeForMenu($arrCategories, $selectedCategory, $categoryId);

        while (count($arrCategories)) {
            reset($arrCategories);
            $menu .= $this->parseCategoryTreeForMenu($arrCategories, $selectedCategory, $categoryId, key($arrCategories));
        }
        $menu .= '</select>';

        return $menu;
    }


    private function parseCategoryTreeForMenu(&$arrCategories, $selectedCategory, $categoryId = null, $parentId = 0, $level = 0)
    {
        $options = '';

        if (!isset($arrCategories[$parentId])) {
            return $options;
        }

        $length = count($arrCategories[$parentId]);
        for ($i = 0; $i < $length; $i++) {
            $options .= '<option value="'.$arrCategories[$parentId][$i]['id'].'"'
                    .($arrCategories[$parentId][$i]['id'] == $selectedCategory ? ' selected="selected"' : '')
                    .($arrCategories[$parentId][$i]['id'] == $categoryId || $arrCategories[$parentId][$i]['is_child'] ? ' disabled="disabled"' : (
                        // managers are allowed to see the content of every category
                        Permission::checkAccess(142, 'static', true)
                        // the category isn't protected => everyone is allowed to the it's content
                        || !$arrCategories[$parentId][$i]['access_id']
                        // the category is protected => only those who have the sufficent permissions are allowed to see it's content
                        || Permission::checkAccess($arrCategories[$parentId][$i]['access_id'], 'dynamic', true)
                        // the owner is allowed to see the content of the category
                        || ($objFWUser = FWUser::getFWUserObject()) && $objFWUser->objUser->login() && $arrCategories[$parentId][$i]['owner_id'] == $objFWUser->objUser->getId() ? '' : ' disabled="disabled"')
                    )
                .'>'
                    .str_repeat('&nbsp;', $level * 4).htmlentities($arrCategories[$parentId][$i]['name'], ENT_QUOTES, CONTREXX_CHARSET)
                .'</option>';
            if (isset($arrCategories[$arrCategories[$parentId][$i]['id']])) {
                $options .= $this->parseCategoryTreeForMenu($arrCategories, $selectedCategory, $categoryId, $arrCategories[$parentId][$i]['id'], $level + 1);
            }
        }

        unset($arrCategories[$parentId]);

        return $options;
    }


    protected function getParsedCategoryListForDownloadAssociation( )
    {
        global $_LANGID;

        $objCategory = Category::getCategories(null, null, array('order' => 'ASC', 'name' => 'ASC', 'id' => 'ASC'));
        $arrCategories = array();

        while (!$objCategory->EOF) {
                $arrCategories[$objCategory->getParentId()][] = array(
                    'id'                    => $objCategory->getId(),
                    'name'                  => $objCategory->getName($_LANGID),
                    'owner_id'              => $objCategory->getOwnerId(),
                    'add_files_access_id'     => $objCategory->getAddFilesAccessId(),
                    'manage_files_access_id'  => $objCategory->getManageFilesAccessId()
                );

            $objCategory->next();
        }

       $arrParsedCategories = $this->parseCategoryTreeForDownloadAssociation($arrCategories);

        while (count($arrCategories)) {
            reset($arrCategories);
            $arrParsedCategories = array_merge($arrParsedCategories, $this->parseCategoryTreeForDownloadAssociation($arrCategories, key($arrCategories)));
        }

        return $arrParsedCategories;
    }


    private function parseCategoryTreeForDownloadAssociation(&$arrCategories, $parentId = 0, $level = 0, $parentName = '')
    {
        $arrParsedCategories = array();

        if (!isset($arrCategories[$parentId])) {
            return $arrParsedCategories;
        }

        $length = count($arrCategories[$parentId]);
        for ($i = 0; $i < $length; $i++) {
            $arrCategories[$parentId][$i]['name'] = $parentName.$arrCategories[$parentId][$i]['name'];
            $arrParsedCategories[] = array_merge($arrCategories[$parentId][$i], array('level' => $level));
            if (isset($arrCategories[$arrCategories[$parentId][$i]['id']])) {
                $arrParsedCategories = array_merge($arrParsedCategories, $this->parseCategoryTreeForDownloadAssociation($arrCategories, $arrCategories[$parentId][$i]['id'], $level + 1, $arrCategories[$parentId][$i]['name'].'\\'));
            }
        }

        unset($arrCategories[$parentId]);

        return $arrParsedCategories;
    }


    protected function getParsedUsername($userId)
    {
        global $_ARRAYLANG;

        $objFWUser = FWUser::getFWUserObject();
        $objUser = $objFWUser->objUser->getUser($userId);
        if ($objUser) {
            if ($objUser->getProfileAttribute('firstname') || $objUser->getProfileAttribute('lastname')) {
                $author = $objUser->getProfileAttribute('firstname').' '.$objUser->getProfileAttribute('lastname').' ('.$objUser->getUsername().')';
            } else {
                $author = $objUser->getUsername();
            }
            $author = htmlentities($author, ENT_QUOTES, CONTREXX_CHARSET);
        } else {
            $author = $_ARRAYLANG['TXT_DOWNLOADS_UNKNOWN'];
        }
        return $author;
    }


    protected function getGroupDropDownMenu($selectedGroupId)
    {
        global $_ARRAYLANG;

        $menu = '<select name="downloads_filter_group_id" id="downloads_filter_group_id" onchange="document.getElementById(\'downloads_filter_user_id\').value=0;this.form.submit()" style="width:300px;">';
        $objFWUser = FWUser::getFWUserObject();
        $objGroup = $objFWUser->objGroup->getGroups();
        $menu .= '<option value="0"'.($selectedGroupId ? '' : ' selected="selected"').' style="border-bottom:1px solid #000;">'.$_ARRAYLANG['TXT_DOWNLOADS_SELECT_USER_GROUP'].'</option>';
        while (!$objGroup->EOF) {
            $menu .= '<option value="'.$objGroup->getId().'"'.($objGroup->getId() == $selectedGroupId ? ' selected="selected"' : '').'>'.htmlentities($objGroup->getName(), ENT_QUOTES, CONTREXX_CHARSET).'</option>';
            $objGroup->next();
        }
        $menu .= '</select>';

        return $menu;
    }


    protected function getUserDropDownMenu($selectedUserId, $params, $header = false)
    {
        global $_ARRAYLANG;

        $menu = "<select $params>";
        $objFWUser = FWUser::getFWUserObject();
        $objUser = $objFWUser->objUser->getUsers(null, null, array('firstname' => 'asc', 'lastname' => 'asc', 'username' => 'asc'), array('id', 'username', 'firstname', 'lastname'));
        if ($header) {
            $menu .= '<option value="0"'.($selectedUserId ? '' : ' selected="selected"').' style="border-bottom:1px solid #000;">'.$_ARRAYLANG['TXT_DOWNLOADS_SELECT_USER'].'</option>';
        }
        while (!$objUser->EOF) {
            $menu .= '<option value="'.$objUser->getId().'"'.($objUser->getId() == $selectedUserId ? ' selected="selected"' : '').'>'.$this->getParsedUsername($objUser->getId()).'</option>';
            $objUser->next();
        }
        $menu .= '</select>';

        return $menu;
    }


    protected function getDownloadMimeTypeMenu($selectedType)
    {
        global $_ARRAYLANG;

        $menu = '<select name="downloads_download_mime_type" id="downloads_download_mime_type" style="width:300px;">';
        $arrMimeTypes = Download::$arrMimeTypes;
        foreach ($arrMimeTypes as $type => $arrMimeType) {
            $menu .= '<option value="'.$type.'"'.($type == $selectedType ? ' selected="selected"' : '').'>'.$_ARRAYLANG[$arrMimeType['description']].'</option>';
        }

        return $menu;
    }

    protected function getValidityMenu($validity, $expirationDate)
    {
//TODO:Use own methods instead of FWUser::getValidityString() and FWUser::getValidityMenuOptions()
        $menu = '<select name="downloads_download_validity" '.($validity && $expirationDate < time() ? 'onchange="this.style.color = this.value == \'current\' ? \'#f00\' : \'#000\'"' : null).' style="width:300px;'.($validity && $expirationDate < time() ? 'color:#f00;font-weight:normal;' : 'color:#000;').'">';
        if ($validity) {
            $menu .= '<option value="current" selected="selected" style="border-bottom:1px solid #000;'.($expirationDate < time() ? 'color:#f00;font-weight:normal;' : null).'">'.FWUser::getValidityString($validity).' ('.date(ASCMS_DATE_SHORT_FORMAT, $expirationDate).')</option>';
        }
        $menu .= FWUser::getValidityMenuOptions(null, 'style="color:#000; font-weight:normal;"');
        $menu .= '</select>';
        return $menu;
    }

    public function setGroups($arrGroups, &$page_content)
    {
        global $_LANGID;

        $objGroup = Group::getGroups(array('id' => $arrGroups));

        while (!$objGroup->EOF) {
            $output = "<ul>\n";
            $objCategory = Category::getCategories(array('id' => $objGroup->getAssociatedCategoryIds()), null, array( 'order' => 'asc', 'name' => 'asc'));
            while (!$objCategory->EOF) {
                $output .= '<li><a href="'.CONTREXX_SCRIPT_PATH.'?section=downloads&amp;cmd='.$objCategory->getId().'" title="'.htmlentities($objCategory->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET).'">'.htmlentities($objCategory->getName($_LANGID), ENT_QUOTES, CONTREXX_CHARSET)."</a></li>\n";
                $objCategory->next();
            }
            $output .= "</ul>\n";

            $page_content = str_replace('{'.$objGroup->getPlaceholder().'}', $output, $page_content);
            $objGroup->next();
        }
    }

    public static function sendNotificationEmails($objDownload, $objCategory)
    {
        global $_CONFIG;

        $objDownloadsMail = new Downloads_Setting_Mail();
        $mail2load = 'new_entry';
        if (
            (
                $objDownloadsMail->load($mail2load, LANG_ID) ||
                $objDownloadsMail->load($mail2load)
            ) &&
            (include_once ASCMS_LIBRARY_PATH.'/phpmailer/class.phpmailer.php') &&
            ($objMail = new PHPMailer()) !== false
        ) {
            if ($_CONFIG['coreSmtpServer'] > 0 && include_once ASCMS_CORE_PATH.'/SmtpSettings.class.php') {
                if (($arrSmtp = SmtpSettings::getSmtpAccount($_CONFIG['coreSmtpServer'])) !== false) {
                    $objMail->IsSMTP();
                    $objMail->Host = $arrSmtp['hostname'];
                    $objMail->Port = $arrSmtp['port'];
                    $objMail->SMTPAuth = true;
                    $objMail->Username = $arrSmtp['username'];
                    $objMail->Password = $arrSmtp['password'];
                }
            }

            $objMail->CharSet = CONTREXX_CHARSET;
            $objMail->From = $objDownloadsMail->getSenderMail();
            $objMail->FromName = $objDownloadsMail->getSenderName();
            $objMail->AddReplyTo($objDownloadsMail->getSenderMail());
            $objMail->Subject = $objDownloadsMail->getSubject();

            // add a is active status check fo objCategory->getNotificationGroupIds()
            $objPublisher = FWUser::getFWUserObject()->objUser->getUser($objDownload->getOwnerId());
            $arrGroupIds = $objCategory->getNotificationGroupIds();
            if (!empty($arrGroupIds)) {
                $objUser = FWUser::getFWUserObject()->objUser->getUsers(array('group_id' => $arrGroupIds, 'is_active' => true));
                if ($objUser) {
                    while (!$objUser->EOF) {
                        $objMail = self::parseNotificationEmailBody($objDownloadsMail, $objMail, $objDownload, $objCategory, $objPublisher, $objUser);
                        $objMail->AddAddress($objUser->getEmail());
                        $objMail->Send();
                        $objMail->ClearAllRecipients();
                        $objUser->next();
                    }
                }
            }
        }
    }

    private static function parseNotificationEmailBody($objDownloadsMail, $objMail, $objDownload, $objCategory, $objPublisher, $objRecipient)
    {
        global $_CONFIG;

        $category = $objCategory->getName($objRecipient->getFrontendLanguage());
        $downloadName = $objDownload->getName($objRecipient->getFrontendLanguage());
        $downloadDescription = $objDownload->getDescription($objRecipient->getFrontendLanguage());
        $image = 'http://'.$_CONFIG['domainUrl'].FWValidator::getEscapedSource($objDownload->getImage());
        $thumbnail= 'http://'.$_CONFIG['domainUrl'].FWValidator::getEscapedSource(ImageManager::getThumbnailFilename($objDownload->getImage()));

        if ($objPublisher) {
            $publisherUsername = $objPublisher->getUsername();
            $publisherGender = $objPublisher->getProfileAttribute('gender');
            $publisherFirstname = $objPublisher->getProfileAttribute('firstname');
            $publisherLastname = $objPublisher->getProfileAttribute('lastname');
        } else {
            // cry
        }

        $recipientUsername = $objRecipient->getUsername();
        $recipientTitle = $objRecipient->getProfileAttribute('title');
        $recipientGender = $objRecipient->getProfileAttribute('gender');
        $recipientFirstname = $objRecipient->getProfileAttribute('firstname');
        $recipientLastname = $objRecipient->getProfileAttribute('lastname');

        // get recipient's title
        $objAttribute = FWUser::getFWUserObject()->objUser->objAttribute->getById('title');
        foreach ($objAttribute->getChildren() as $childAttributeId) {
            $objChildAtrribute = $objAttribute->getById($childAttributeId);
            if ($objChildAtrribute->getMenuOptionValue() == $objRecipient->getProfileAttribute($objAttribute->getId())) {
                $recipientTitle = $objChildAtrribute->getName();
                break;
            }
        }

        // get publisher & recipient's gender
        $objAttribute = FWUser::getFWUserObject()->objUser->objAttribute->getById('gender');
        foreach ($objAttribute->getChildren() as $childAttributeId) {
            $objChildAtrribute = $objAttribute->getById($childAttributeId);
            if ($objChildAtrribute->getMenuOptionValue() == $objPublisher->getProfileAttribute($objAttribute->getId())) {
                $publisherGender = $objChildAtrribute->getName();
            }
            if ($objChildAtrribute->getMenuOptionValue() == $objRecipient->getProfileAttribute($objAttribute->getId())) {
                $recipientGender = $objChildAtrribute->getName();
            }
        }

        if (in_array($objDownloadsMail->getFormat(), array('multipart', 'text'))) {
            $objDownloadsMail->getFormat() == 'text' ? $objMail->IsHTML(false) : false;
            $objMail->{($objDownloadsMail->getFormat() == 'text' ? '' : 'Alt').'Body'} = str_replace(
                array(
                    '[[ID]]',
                    '[[IMAGE]]',
                    '[[THUMBNAIL]]',
                    '[[HOST]]',
                    '[[HOST_LINK]]',
                    '[[SENDER]]',
                    '[[LINK]]',
                    '[[CATEGORY]]',
                    '[[NAME]]',
                    '[[DESCRIPTION]]',
                    '[[PUBLISHER_GENDER]]',
                    '[[PUBLISHER_USERNAME]]',
                    '[[PUBLISHER_FIRSTNAME]]',
                    '[[PUBLISHER_LASTNAME]]',
                    '[[RECIPIENT_TITLE]]',
                    '[[RECIPIENT_GENDER]]',
                    '[[RECIPIENT_USERNAME]]',
                    '[[RECIPIENT_FIRSTNAME]]',
                    '[[RECIPIENT_LASTNAME]]',
                ),
                array(
                    $objDownload->getId(),
                    $image,
                    $thumbnail,
                    $_CONFIG['domainUrl'],
                    'http://'.$_CONFIG['domainUrl'],
                    $objDownloadsMail->getSenderName(),
                    'http://'.$_CONFIG['domainUrl'].CONTREXX_SCRIPT_PATH.'?section=downloads&category='.$objCategory->getId().'&id='.$objDownload->getId(),
                    $category,
                    $downloadName,
                    $downloadDescription,
                    $publisherGender,
                    $publisherUsername,
                    $publisherFirstname,
                    $publisherLastname,
                    $recipientTitle,
                    $recipientGender,
                    $recipientUsername,
                    $recipientFirstname,
                    $recipientLastname,
                ),
                $objDownloadsMail->getBodyText()
            );
        }
        if (in_array($objDownloadsMail->getFormat(), array('multipart', 'html'))) {
            $objDownloadsMail->getFormat() == 'html' ? $objMail->IsHTML(true) : false;
            $objMail->Body = str_replace(
                array(
                    '[[ID]]',
                    '[[IMAGE]]',
                    '[[THUMBNAIL]]',
                    '[[HOST]]',
                    '[[HOST_LINK]]',
                    '[[SENDER]]',
                    '[[LINK]]',
                    '[[CATEGORY]]',
                    '[[NAME]]',
                    '[[DESCRIPTION]]',
                    '[[PUBLISHER_GENDER]]',
                    '[[PUBLISHER_USERNAME]]',
                    '[[PUBLISHER_FIRSTNAME]]',
                    '[[PUBLISHER_LASTNAME]]',
                    '[[RECIPIENT_TITLE]]',
                    '[[RECIPIENT_GENDER]]',
                    '[[RECIPIENT_USERNAME]]',
                    '[[RECIPIENT_FIRSTNAME]]',
                    '[[RECIPIENT_LASTNAME]]',
                ),
                array(
                    $objDownload->getId(),
                    $image,
                    $thumbnail,
                    $_CONFIG['domainUrl'],
                    'http://'.$_CONFIG['domainUrl'],
                    htmlentities($objDownloadsMail->getSenderName(), ENT_QUOTES, CONTREXX_CHARSET),
                    'http://'.$_CONFIG['domainUrl'].CONTREXX_SCRIPT_PATH.'?section=downloads&amp;category='.$objCategory->getId().'&amp;id='.$objDownload->getId(),
                    htmlentities($category, ENT_QUOTES, CONTREXX_CHARSET),
                    htmlentities($downloadName, ENT_QUOTES, CONTREXX_CHARSET),
                    htmlentities($downloadDescription, ENT_QUOTES, CONTREXX_CHARSET),
                    htmlentities($publisherGender, ENT_QUOTES, CONTREXX_CHARSET),
                    htmlentities($publisherUsername, ENT_QUOTES, CONTREXX_CHARSET),
                    htmlentities($publisherFirstname, ENT_QUOTES, CONTREXX_CHARSET),
                    htmlentities($publisherLastname, ENT_QUOTES, CONTREXX_CHARSET),
                    htmlentities($recipientTitle, ENT_QUOTES, CONTREXX_CHARSET),
                    htmlentities($recipientGender, ENT_QUOTES, CONTREXX_CHARSET),
                    htmlentities($recipientUsername, ENT_QUOTES, CONTREXX_CHARSET),
                    htmlentities($recipientFirstname, ENT_QUOTES, CONTREXX_CHARSET),
                    htmlentities($recipientLastname, ENT_QUOTES, CONTREXX_CHARSET),
                ),
                $objDownloadsMail->getBodyHtml()
            );
        }

        return $objMail;
    }
}

?>
