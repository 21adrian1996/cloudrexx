<?php
/**
* User Management
* @copyright    CONTREXX CMS - COMVATION AG
* @author       COMVATION Development Team <info@comvation.com>
* @package      contrexx
* @subpackage   core_module_access
* @version      1.0.0
*/

/**
 * @ignore
 */
require_once ASCMS_CORE_MODULE_PATH.'/access/lib/AccessLib.class.php';

/**
* Frontend for the user management
* @copyright    CONTREXX CMS - COMVATION AG
* @author       COMVATION Development Team <info@comvation.com>
* @package      contrexx
* @subpackage   core_module_access
* @version      1.0.0
*/
class Access extends AccessLib
{
    private $arrStatusMsg = array('ok' => array(), 'error' => array());

    public function __construct($pageContent)
    {
        parent::__construct();

        $this->_objTpl = new HTML_Template_Sigma('.');
        $this->_objTpl->setErrorHandling(PEAR_ERROR_DIE);
        $this->_objTpl->setTemplate($pageContent);
    }

    public function getPage(&$metaPageTitle, &$pageTitle)
    {
        $cmd = isset($_REQUEST['cmd']) ? explode('_', $_REQUEST['cmd']) : array(0 => null);



        switch ($cmd[0]) {
            case 'signup':
                $this->signUp();
                break;

            case 'settings':
                $this->settings();
                break;

            case 'members':
                $this->members();
                break;

            case 'user':
                $this->user($metaPageTitle, $pageTitle);
                break;

            default:
                $this->dashboard();
                break;
        }

        return $this->_objTpl->get();
    }

    public function dashboard()
    {

    }

    private function user(&$metaPageTitle, &$pageTitle)
    {
        global $_CONFIG;

        $objFWUser = FWUser::getFWUserObject();
        $objUser = $objFWUser->objUser->getUser(!empty($_REQUEST['id']) ? intval($_REQUEST['id']) : 0);
        if ($objUser) {

            if ($objUser->getProfileAccess() != 'everyone') {
                if (!$objFWUser->objUser->login()) {
                    header('Location: '.CONTREXX_DIRECTORY_INDEX.'?section=login&redirect='.base64_encode(ASCMS_PROTOCOL.'://'.$_CONFIG['domainUrl'].CONTREXX_SCRIPT_PATH.'?section=access&cmd=user&id='.$objUser->getId()));
                    exit;
                }

                if ($objUser->getId() != $objFWUser->objUser->getId()
                    && $objUser->getProfileAccess() == 'nobody'
                    && !$objFWUser->objUser->getAdminStatus()
                ) {
                    header('Location: '.CONTREXX_DIRECTORY_INDEX.'?section=login&cmd=noaccess&redirect='.base64_encode(ASCMS_PROTOCOL.'://'.$_CONFIG['domainUrl'].CONTREXX_SCRIPT_PATH.'?section=access&cmd=user&id='.$objUser->getId()));
                    exit;
                }
            }

            $metaPageTitle = $objUser->getUsername()."'s Profil";
            $pageTitle = htmlentities($objUser->getUsername(), ENT_QUOTES, CONTREXX_CHARSET)."'s Profil";
            $this->_objTpl->setGlobalVariable(array(
                'ACCESS_USER_ID'        => $objUser->getId(),
                'ACCESS_USER_USERNAME'  => htmlentities($objUser->getUsername(), ENT_QUOTES, CONTREXX_CHARSET)
            ));

            if ($objUser->getEmailAccess() == 'everyone' ||
                $objFWUser->objUser->login() &&
                (
                    $objUser->getId() == $objFWUser->objUser->getId() ||
                    $objUser->getEmailAccess() == 'members_only' ||
                    $objFWUser->objUser->getAdminStatus()
                )
            ) {
                $this->parseAccountAttribute($objUser, 'email');
            } elseif ($this->_objTpl->blockExists('access_user_email')) {
                $this->_objTpl->hideBlock('access_user_email');
            }

            $nr = 0;
            while (!$objUser->objAttribute->EOF) {
                $objAttribute = $objUser->objAttribute->getById($objUser->objAttribute->getId());
                $this->parseAttribute($objUser, $objAttribute->getId(), 0, false, false, false, false, true, array('_CLASS' => $nr % 2 + 1)) ? $nr++ : false;
                $objUser->objAttribute->next();
            }

            $this->_objTpl->setVariable("ACCESS_REFERER", $_SERVER['HTTP_REFERER']);
        } else {
            // or would it be better to redirect to the home page?
            header('Location: index.php?section=access&cmd=members');
            exit;
        }
    }

    private function members()
    {
        global $_ARRAYLANG, $_CONFIG;

        $groupId = isset($_REQUEST['groupId']) ? intval($_REQUEST['groupId']) : 0;
        $search = isset($_REQUEST['search']) && !empty($_REQUEST['search']) ? preg_split('#\s+#', $_REQUEST['search']) : array();
        $limitOffset = isset($_GET['pos']) ? intval($_GET['pos']) : 0;
        $usernameFilter = isset($_REQUEST['username_filter']) && $_REQUEST['username_filter'] != '' && in_array(ord($_REQUEST['username_filter']), array_merge(array(48), range(65, 90))) ? $_REQUEST['username_filter'] : null;
        $userFilter = array('active' => true);

        $this->parseLetterIndexList('index.php?section=access&amp;cmd=members&amp;groupId='.$groupId, 'username_filter', $usernameFilter);

        $this->_objTpl->setVariable('ACCESS_SEARCH_VALUE', htmlentities(join(' ', $search), ENT_QUOTES, CONTREXX_CHARSET));

        if ($groupId) {
            $userFilter['group_id'] = $groupId;
        }
        if ($usernameFilter !== null) {
            $userFilter['username'] = array('REGEXP' => '^'.($usernameFilter == '0' ? '[0-9]|-|_' : $usernameFilter));
        }

        $objFWUser = FWUser::getFWUserObject();
        $objFWUser->objGroup->load($groupId);
        if ($objFWUser->objGroup->getType() == 'frontend' && $objFWUser->objGroup->getUserCount() > 0 && ($objUser = $objFWUser->objUser->getUsers($userFilter, $search, array('username' => 'asc'), null, $_CONFIG['corePagingLimit'], $limitOffset)) && $userCount = $objUser->getFilteredSearchUserCount()) {

            if ($userCount > $_CONFIG['corePagingLimit']) {
                $this->_objTpl->setVariable('ACCESS_USER_PAGING', getPaging($userCount, $limitOffset, "&amp;section=access&amp;cmd=members&amp;groupId=".$groupId."&amp;search=".htmlspecialchars(implode(' ',$search), ENT_QUOTES, CONTREXX_CHARSET)."&amp;username_filter=".$usernameFilter, "<strong>".$_ARRAYLANG['TXT_ACCESS_MEMBERS']."</strong>"));
            }

            $this->_objTpl->setVariable('ACCESS_GROUP_NAME', $objFWUser->objGroup->load($groupId) ? htmlentities($objFWUser->objGroup->getName(), ENT_QUOTES, CONTREXX_CHARSET) : $_ARRAYLANG['TXT_ACCESS_MEMBERS']);

            $nr = 0;
            while (!$objUser->EOF) {
                $this->parseAccountAttributes($objUser);
                $this->_objTpl->setVariable('ACCESS_USER_ID', $objUser->getId());
                $this->_objTpl->setVariable('ACCESS_USER_CLASS', $nr++ % 2 + 1);

                if ($objUser->getProfileAccess() == 'everyone' ||
                    $objFWUser->objUser->login() &&
                    (
                        $objUser->getId() == $objFWUser->objUser->getId() ||
                        $objUser->getProfileAccess() == 'members_only' ||
                        $objFWUser->objUser->getAdminStatus()
                    )
                ) {
                    $objUser->objAttribute->first();

                    while (!$objUser->objAttribute->EOF) {
                        $objAttribute = $objUser->objAttribute->getById($objUser->objAttribute->getId());
                        $this->parseAttribute($objUser, $objAttribute->getId(), 0, false, false, false, false, false);
                        $objUser->objAttribute->next();
                    }
                } else {
                    $this->parseAttribute($objUser, 'picture', 0, false, false, false, false, false);
                    $this->parseAttribute($objUser, 'gender', 0, false, false, false, false, false);
                }

                $this->_objTpl->parse('access_user');
                $objUser->next();
            }

            $this->_objTpl->parse('access_members');
        } else {
            $this->_objTpl->hideBlock('access_members');
        }
    }

    private function settings()
    {
        global $_CONFIG, $_ARRAYLANG;

        $objFWUser = FWUser::getFWUserObject();
        if (!$objFWUser->objUser->login()) {
            header('Location: '.CONTREXX_DIRECTORY_INDEX.'?section=login&redirect='.base64_encode(ASCMS_PROTOCOL.'://'.$_CONFIG['domainUrl'].CONTREXX_SCRIPT_PATH.'?section=access&cmd='.rawurlencode($_REQUEST['cmd'])));
            exit;
        }
        $settingsDone = false;

        if (isset($_POST['access_delete_account'])) {
            // delete account
            if ($objFWUser->objUser->checkPassword(isset($_POST['access_user_password']) ? $_POST['access_user_password'] : null)) {
                if ($objFWUser->objUser->isAllowedToDeleteAccount()) {
                    if ($objFWUser->objUser->delete(true)) {
                        $this->_objTpl->setVariable('ACCESS_SETTINGS_MESSAGE', $_ARRAYLANG['TXT_ACCESS_YOUR_ACCOUNT_SUCCSESSFULLY_DELETED']);
                        if ($this->_objTpl->blockExists('access_settings')) {
                            $this->_objTpl->hideBlock('access_settings');
                        }
                        if ($this->_objTpl->blockExists('access_settings_done')) {
                            $this->_objTpl->touchBlock('access_settings_done');
                        }
                        return;
                    } else {
                        $this->_objTpl->setVariable('ACCESS_SETTINGS_MESSAGE', implode('<br />', $objFWUser->objUser->getErrorMsg()));
                    }
                } else {
                    $this->_objTpl->setVariable('ACCESS_SETTINGS_MESSAGE', $_ARRAYLANG['TXT_ACCESS_NOT_ALLOWED_TO_DELETE_ACCOUNT']);
                }
            } else {
                $this->_objTpl->setVariable('ACCESS_SETTINGS_MESSAGE', $_ARRAYLANG['TXT_ACCESS_INVALID_EXISTING_PASSWORD']);
            }
        } elseif (isset($_POST['access_change_password'])) {
            // change password
            if (!empty($_POST['access_user_current_password']) && $objFWUser->objUser->checkPassword(trim(contrexx_stripslashes($_POST['access_user_current_password'])))) {
                $this->_objTpl->setVariable(
                    'ACCESS_SETTINGS_MESSAGE',
                    ($objFWUser->objUser->setPassword(
                        isset($_POST['access_user_password']) ?
                            trim(contrexx_stripslashes($_POST['access_user_password']))
                            : '',
                        isset($_POST['access_user_password_confirmed']) ?
                            trim(contrexx_stripslashes($_POST['access_user_password_confirmed']))
                            : '',
                        true
                    ) && $objFWUser->objUser->store()) ?
                        $_ARRAYLANG['TXT_ACCESS_PASSWORD_CHANGED_SUCCESSFULLY'].(($settingsDone = true) && false)
                        : implode('<br />', $objFWUser->objUser->getErrorMsg())
                );
            } else {
                $this->_objTpl->setVariable('ACCESS_SETTINGS_MESSAGE', $_ARRAYLANG['TXT_ACCESS_INVALID_EXISTING_PASSWORD']);
            }
        } elseif (isset($_POST['access_store'])) {
            // store profile
            $status = true;

            isset($_POST['access_user_username']) ? $objFWUser->objUser->setUsername(trim(contrexx_stripslashes($_POST['access_user_username']))) : null;
            $objFWUser->objUser->setEmail(isset($_POST['access_user_email']) ? trim(contrexx_stripslashes($_POST['access_user_email'])) : $objFWUser->objUser->getEmail());

            $currentLangId = $objFWUser->objUser->getFrontendLanguage();
            $objFWUser->objUser->setFrontendLanguage(isset($_POST['access_user_frontend_language']) ? intval($_POST['access_user_frontend_language']) : $objFWUser->objUser->getFrontendLanguage());
            $objFWUser->objUser->setEmailAccess(isset($_POST['access_user_email_access']) && $objFWUser->objUser->isAllowedToChangeEmailAccess() ? contrexx_stripslashes($_POST['access_user_email_access']) : $objFWUser->objUser->getEmailAccess());
            $objFWUser->objUser->setProfileAccess(isset($_POST['access_user_profile_access']) && $objFWUser->objUser->isAllowedToChangeProfileAccess() ? contrexx_stripslashes($_POST['access_user_profile_access']) : $objFWUser->objUser->getProfileAccess());

            if (isset($_POST['access_profile_attribute']) && is_array($_POST['access_profile_attribute'])) {
                $arrProfile = $_POST['access_profile_attribute'];

                if (isset($_FILES['access_profile_attribute_images'])
                    && is_array($_FILES['access_profile_attribute_images'])
                    && ($result = $this->addUploadedImagesToProfile($objFWUser->objUser, $arrProfile, $_FILES['access_profile_attribute_images'])) !== true
                ) {
                    $status = false;
                }

                $objFWUser->objUser->setProfile($arrProfile);
            }

            if ($status) {
                if ($objFWUser->objUser->store()) {
                    $msg = $_ARRAYLANG['TXT_ACCESS_USER_ACCOUNT_STORED_SUCCESSFULLY'];
                    $settingsDone = true;
                    $this->setLanguageCookie($currentLangId, $objFWUser->objUser->getFrontendLanguage());
                } else {
                    $msg = implode('<br />', $objFWUser->objUser->getErrorMsg());
                }
            } else {
                $msg = implode('<br />', $result);
            }
            $this->_objTpl->setVariable('ACCESS_SETTINGS_MESSAGE', $msg);
        }
        $this->parseAccountAttributes($objFWUser->objUser, true);

        while (!$objFWUser->objUser->objAttribute->EOF) {
            $objAttribute = $objFWUser->objUser->objAttribute->getById($objFWUser->objUser->objAttribute->getId());

            if (!$objAttribute->isProtected() ||
                (
                    Permission::checkAccess($objAttribute->getAccessId(), 'dynamic', true) ||
                    $objAttribute->checkModifyPermission()
                )
            ) {
                $this->parseAttribute($objFWUser->objUser, $objAttribute->getId(), 0, true);
            }

            $objFWUser->objUser->objAttribute->next();
        }

        $this->attachJavaScriptFunction('accessSetWebsite');
        $this->attachJavaScriptFunction('jscalendarIncludes');

        $this->_objTpl->setVariable(array(
            'ACCESS_DELETE_ACCOUNT_BUTTON'  => '<input type="submit" name="access_delete_account" value="'.$_ARRAYLANG['TXT_ACCESS_DELETE_ACCOUNT'].'" />',
            'ACCESS_USER_PASSWORD_INPUT'    => '<input type="password" name="access_user_password" />',
            'ACCESS_STORE_BUTTON'           => '<input type="submit" name="access_store" value="'.$_ARRAYLANG['TXT_ACCESS_SAVE'].'" />',
            'ACCESS_CHANGE_PASSWORD_BUTTON' => '<input type="submit" name="access_change_password" value="'.$_ARRAYLANG['TXT_ACCESS_CHANGE_PASSWORD'].'" />',
            'ACCESS_JAVASCRIPT_FUNCTIONS'   => $this->getJavaScriptCode()
        ));

        if ($this->_objTpl->blockExists('access_settings')) {
            $this->_objTpl->{$settingsDone ? 'hideBlock' : 'touchBlock'}('access_settings');
        }
        if ($this->_objTpl->blockExists('access_settings_done')) {
            $this->_objTpl->{$settingsDone ? 'touchBlock' : 'hideBlock'}('access_settings_done');
        }
    }

    private function setLanguageCookie($currentLangId, $newLangId)
    {
        global $objInit;

        // set a new cookie if the language id had been changed
        if ($currentLangId != $newLangId) {
            // check if the desired language is active at all. otherwise set default language
    $objInit->arrLang[$newLangId]['frontend'];
            if ($objInit->arrLang[$newLangId]['frontend'] || ($newLangId = $objInit->defaultFrontendLangId)) {
                setcookie("langId", $newLangId, time()+3600*24*30, ASCMS_PATH_OFFSET.'/');
            }
        }
    }

    private function confirmSignUp($userId, $restoreKey)
    {
        global $_ARRAYLANG, $_CONFIG;

        $objFWUser = FWUser::getFWUserObject();
        if (($objUser = $objFWUser->objUser->getUser($userId)) && $objUser->getRestoreKey() == $restoreKey) {
            $arrSettings = User_Setting::getSettings();
            if (!$arrSettings['user_activation_timeout']['status'] || $objUser->getRestoreKeyTime() >= time()) {
                if ($objUser->finishSignUp()) {
                    return true;
                } else {
                    $this->arrStatusMsg['error'] = array_merge($this->arrStatusMsg['error'], $objUser->getErrorMsg());
                }
            } else {
                $this->arrStatusMsg['error'][] = $_ARRAYLANG['TXT_ACCESS_ACTIVATION_TIME_EXPIRED'];
                $this->arrStatusMsg['error'][] = '<a href="'.CONTREXX_DIRECTORY_INDEX.'?section=access&amp;cmd=signup" title="'.$_ARRAYLANG['TXT_ACCESS_REGISTER_NEW_ACCOUNT'].'">'.$_ARRAYLANG['TXT_ACCESS_REGISTER_NEW_ACCOUNT'].'</a>';
            }
        } else {
            $mailSubject = str_replace('%HOST%', 'http://'.$_CONFIG['domainUrl'], $_ARRAYLANG['TXT_ACCESS_ACCOUNT_ACTIVATION_NOT_POSSIBLE']);
            $adminEmail = '<a href="mailto:'.$_CONFIG['coreAdminEmail'].'?subject='.$mailSubject.'" title="'.$_CONFIG['coreAdminEmail'].'">'.$_CONFIG['coreAdminEmail'].'</a>';
            $this->arrStatusMsg['error'][] = str_replace('%EMAIL%', $adminEmail, $_ARRAYLANG['TXT_ACCESS_INVALID_USERNAME_OR_ACTIVATION_KEY']);
        }

        return false;
    }

    private function signUp()
    {
        global $_ARRAYLANG;

        $arrProfile = array();

        if (!empty($_GET['u']) && !empty($_GET['k'])) {
            $this->_objTpl->hideBlock('access_signup_store_success');
            $this->_objTpl->hideBlock('access_signup_store_error');

            if ($this->confirmSignUp(intval($_GET['u']), contrexx_stripslashes($_GET['k']))) {
                $this->_objTpl->setVariable('ACCESS_SIGNUP_MESSAGE', $_ARRAYLANG['TXT_ACCESS_ACCOUNT_SUCCESSFULLY_ACTIVATED']);
                $this->_objTpl->parse('access_signup_confirm_success');
                $this->_objTpl->hideBlock('access_signup_confirm_error');
            } else {
                $this->_objTpl->setVariable('ACCESS_SIGNUP_MESSAGE', implode('<br />', $this->arrStatusMsg['error']));
                $this->_objTpl->parse('access_signup_confirm_error');
                $this->_objTpl->hideBlock('access_signup_confirm_success');
            }

            return;
        } else {
            $this->_objTpl->hideBlock('access_signup_confirm_success');
            $this->_objTpl->hideBlock('access_signup_confirm_error');
        }

        $objUser = new User();

        if (isset($_POST['access_signup'])) {
            $arrSettings = User_Setting::getSettings();

            $objUser->setUsername(isset($_POST['access_user_username']) ? trim(contrexx_stripslashes($_POST['access_user_username'])) : '');
            $objUser->setEmail(isset($_POST['access_user_email']) ? trim(contrexx_stripslashes($_POST['access_user_email'])) : '');
            $objUser->setFrontendLanguage(isset($_POST['access_user_frontend_language']) ? intval($_POST['access_user_frontend_language']) : 0);

            $objUser->setGroups(explode(',', $arrSettings['assigne_to_groups']['value']));

            if (
                (
                    // either no profile attributes are set
                    (!isset($_POST['access_profile_attribute']) || !is_array($_POST['access_profile_attribute']))
                    ||
                    // otherwise try to adopt them
                    (
                        ($arrProfile = $_POST['access_profile_attribute'])
                        && (
                            // either no profile images are set
                            (!isset($_FILES['access_profile_attribute_images']) || !is_array($_FILES['access_profile_attribute_images']))
                            ||
                            // otherwise try to upload them
                            ($uploadImageError = $this->addUploadedImagesToProfile($objUser, $arrProfile, $_FILES['access_profile_attribute_images'])) === true
                        )
                        && $objUser->setProfile($arrProfile)
                    )
                )
                && $objUser->setPassword(
                    isset($_POST['access_user_password']) ?
                        trim(contrexx_stripslashes($_POST['access_user_password']))
                    :   '',
                    isset($_POST['access_user_password_confirmed'])?
                        trim(contrexx_stripslashes($_POST['access_user_password_confirmed']))
                    :    ''
                )
                && $objUser->checkMandatoryCompliance()
                && $objUser->signUp()
            ) {
                if ($this->handleSignUp($objUser)) {
                    $this->_objTpl->setVariable('ACCESS_SIGNUP_MESSAGE', implode('<br />', $this->arrStatusMsg['ok']));
                    $this->_objTpl->parse('access_signup_store_success');
                    $this->_objTpl->hideBlock('access_signup_store_error');
                } else {
                    $this->_objTpl->setVariable('ACCESS_SIGNUP_MESSAGE', implode('<br />', $this->arrStatusMsg['error']));
                    $this->_objTpl->parse('access_signup_store_error');
                    $this->_objTpl->hideBlock('access_signup_store_success');
                }

                $this->_objTpl->hideBlock('access_signup_form');
                return;
            } else {
                if (is_array($uploadImageError)) {
                    $this->arrStatusMsg['error'] = array_merge($this->arrStatusMsg['error'], $uploadImageError);
                }
                $this->arrStatusMsg['error'] = array_merge($this->arrStatusMsg['error'], $objUser->getErrorMsg());

                $this->_objTpl->hideBlock('access_signup_store_success');
                $this->_objTpl->hideBlock('access_signup_store_error');
            }
        } else {
            $this->_objTpl->hideBlock('access_signup_store_success');
            $this->_objTpl->hideBlock('access_signup_store_error');
        }

        $this->parseAccountAttributes($objUser, true);

        while (!$objUser->objAttribute->EOF) {
            $objAttribute = $objUser->objAttribute->getById($objUser->objAttribute->getId());

            if (!$objAttribute->isProtected() ||
                (
                    Permission::checkAccess($objAttribute->getAccessId(), 'dynamic', true) ||
                    $objAttribute->checkModifyPermission()
                )
            ) {
                $this->parseAttribute($objUser, $objAttribute->getId(), 0, true);
            }

            $objUser->objAttribute->next();
        }

        $this->attachJavaScriptFunction('accessSetWebsite');
        $this->attachJavaScriptFunction('jscalendarIncludes');

        $this->_objTpl->setVariable(array(
            'ACCESS_SIGNUP_BUTTON'          => '<input type="submit" name="access_signup" value="'.$_ARRAYLANG['TXT_ACCESS_CREATE_ACCOUNT'].'" />',
            'ACCESS_JAVASCRIPT_FUNCTIONS'   => $this->getJavaScriptCode(),
            'ACCESS_SIGNUP_MESSAGE'         => implode("<br />\n", $this->arrStatusMsg['error'])
        ));
        $this->_objTpl->parse('access_signup_form');
    }

    function handleSignUp($objUser)
    {
        global $_ARRAYLANG, $_CONFIG, $_LANGID;

        $objFWUser = FWUser::getFWUserObject();
        $objUserMail = $objFWUser->getMail();
        $arrSettings = User_Setting::getSettings();

        if ($arrSettings['user_activation']['status']) {
            $mail2load = 'reg_confirm';
            $mail2addr = $objUser->getEmail();
        } else {
            $mail2load = 'new_user';
            $mail2addr = $arrSettings['notification_address']['value'];
        }

        if (
            (
                $objUserMail->load($mail2load, $_LANGID) ||
                $objUserMail->load($mail2load)
            ) &&
            (include_once ASCMS_LIBRARY_PATH.'/phpmailer/class.phpmailer.php') &&
            ($objMail = new PHPMailer()) !== false
        ) {
            if ($_CONFIG['coreSmtpServer'] > 0 && @include_once ASCMS_CORE_PATH.'/SmtpSettings.class.php') {
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
            $objMail->From = $objUserMail->getSenderMail();
            $objMail->FromName = $objUserMail->getSenderName();
            $objMail->AddReplyTo($objUserMail->getSenderMail());
            $objMail->Subject = $objUserMail->getSubject();

            if (in_array($objUserMail->getFormat(), array('multipart', 'text'))) {
                $objUserMail->getFormat() == 'text' ? $objMail->IsHTML(false) : false;
                $objMail->{($objUserMail->getFormat() == 'text' ? '' : 'Alt').'Body'} = str_replace(
                    array(
                        '[[HOST]]',
                        '[[USERNAME]]',
                        '[[ACTIVATION_LINK]]',
                        '[[HOST_LINK]]',
                        '[[SENDER]]',
                        '[[LINK]]'
                    ),
                    array(
                        $_CONFIG['domainUrl'],
                        $objUser->getUsername(),
                        'http://'.$_CONFIG['domainUrl'].CONTREXX_SCRIPT_PATH.'?section=access&cmd=signup&u='.($objUser->getId()).'&k='.$objUser->getRestoreKey(),
                        'http://'.$_CONFIG['domainUrl'],
                        $objUserMail->getSenderName(),
                        'http://'.$_CONFIG['domainUrl'].ASCMS_PATH_OFFSET.ASCMS_BACKEND_PATH.'/index.php?cmd=access&act=user&tpl=modify&id='.$objUser->getId()
                    ),
                    $objUserMail->getBodyText()
                );
            }
            if (in_array($objUserMail->getFormat(), array('multipart', 'html'))) {
                $objUserMail->getFormat() == 'html' ? $objMail->IsHTML(true) : false;
                $objMail->Body = str_replace(
                    array(
                        '[[HOST]]',
                        '[[USERNAME]]',
                        '[[ACTIVATION_LINK]]',
                        '[[HOST_LINK]]',
                        '[[SENDER]]',
                        '[[LINK]]'
                    ),
                    array(
                        $_CONFIG['domainUrl'],
                        htmlentities($objUser->getUsername(), ENT_QUOTES, CONTREXX_CHARSET),
                        'http://'.$_CONFIG['domainUrl'].CONTREXX_SCRIPT_PATH.'?section=access&cmd=signup&u='.($objUser->getId()).'&k='.$objUser->getRestoreKey(),
                        'http://'.$_CONFIG['domainUrl'],
                        htmlentities($objUserMail->getSenderName(), ENT_QUOTES, CONTREXX_CHARSET),
                        'http://'.$_CONFIG['domainUrl'].ASCMS_PATH_OFFSET.ASCMS_BACKEND_PATH.'/index.php?cmd=access&act=user&tpl=modify&id='.$objUser->getId()
                    ),
                    $objUserMail->getBodyHtml()
                );
            }

            $objMail->AddAddress($mail2addr);

            if ($objMail->Send()) {
                $this->arrStatusMsg['ok'][] = $_ARRAYLANG['TXT_ACCESS_ACCOUNT_SUCCESSFULLY_CREATED'];
                if ($arrSettings['user_activation']['status']) {
                    $timeoutStr = '';
                    if ($arrSettings['user_activation_timeout']['status']) {
                        if ($arrSettings['user_activation_timeout']['value'] > 1) {
                            $timeoutStr = $arrSettings['user_activation_timeout']['value'].' '.$_ARRAYLANG['TXT_ACCESS_HOURS_IN_STR'];
                        } else {
                            $timeoutStr = ' '.$_ARRAYLANG['TXT_ACCESS_HOUR_IN_STR'];
                        }

                        $timeoutStr = str_replace('%TIMEOUT%', $timeoutStr, $_ARRAYLANG['TXT_ACCESS_ACTIVATION_TIMEOUT']);
                    }
                    $this->arrStatusMsg['ok'][] = str_replace('%TIMEOUT%', $timeoutStr, $_ARRAYLANG['TXT_ACCESS_ACTIVATION_BY_USER_MSG']);
                } else {
                    $this->arrStatusMsg['ok'][] = str_replace("%HOST%", $_CONFIG['domainUrl'], $_ARRAYLANG['TXT_ACCESS_ACTIVATION_BY_SYSTEM']);
                }
                return true;
            }
        }

        $mailSubject = str_replace("%HOST%", "http://".$_CONFIG['domainUrl'], $_ARRAYLANG['TXT_ACCESS_COULD_NOT_SEND_ACTIVATION_MAIL']);
        $adminEmail = '<a href="mailto:'.$_CONFIG['coreAdminEmail'].'?subject='.$mailSubject.'" title="'.$_CONFIG['coreAdminEmail'].'">'.$_CONFIG['coreAdminEmail'].'</a>';
        $this->arrStatusMsg['error'][] = str_replace("%EMAIL%", $adminEmail, $_ARRAYLANG['TXT_ACCESS_COULD_NOT_SEND_EMAIL']);
        return false;
    }
}
?>
