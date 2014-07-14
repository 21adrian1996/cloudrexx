<?php
/**
 * Main controller for Access
 * 
 * @copyright   Comvation AG
 * @author      Project Team SS4U <info@comvation.com>
 * @package     contrexx
 * @subpackage  coremodule_access
 */

namespace Cx\Core_Modules\Access\Controller;

/**
 * Main controller for Access
 * 
 * @copyright   Comvation AG
 * @author      Project Team SS4U <info@comvation.com>
 * @package     contrexx
 * @subpackage  coremodule_access
 */
class ComponentController extends \Cx\Core\Core\Model\Entity\SystemComponentController {
    public function getControllerClasses() {
        // Return an empty array here to let the component handler know that there
        // does not exist a backend, nor a frontend controller of this component.
        return array();
    }

     /**
     * Load your component.
     * 
     * @param \Cx\Core\ContentManager\Model\Entity\Page $page       The resolved page
     */
    public function load(\Cx\Core\ContentManager\Model\Entity\Page $page) {
        global $page_metatitle, $_CORELANG, $subMenuTitle, $objTemplate;
        switch ($this->cx->getMode()) {
            case \Cx\Core\Core\Controller\Cx::MODE_FRONTEND:
                $objAccess = new Access(\Env::get('cx')->getPage()->getContent());
                $pageTitle = \Env::get('cx')->getPage()->getTitle();
                \Env::get('cx')->getPage()->setContent($objAccess->getPage($page_metatitle, $pageTitle));
                break;

            case \Cx\Core\Core\Controller\Cx::MODE_BACKEND:
                $this->cx->getTemplate()->addBlockfile('CONTENT_OUTPUT', 'content_master', 'content_master.html');
                $objTemplate = $this->cx->getTemplate();
                
                $subMenuTitle = $_CORELANG['TXT_COMMUNITY'];
                $objAccessManager = new AccessManager();
                $objAccessManager->getPage();
                break;

            default:
                break;
        }
    }
    
    /**
     * Do something before content is loaded from DB
     * 
     * @param \Cx\Core\ContentManager\Model\Entity\Page $page       The resolved page
     */
    public function preContentLoad(\Cx\Core\ContentManager\Model\Entity\Page $page) {
        switch ($this->cx->getMode()) {
            case \Cx\Core\Core\Controller\Cx::MODE_FRONTEND:
                $content = \Env::get('cx')->getPage()->getContent();
                \FWUser::parseLoggedInOutBlocks($content);
                \Env::get('cx')->getPage()->setContent($content);
                break;

            default:
                break;
        }
    }
    
    
    /**
     * Do something after content is loaded from DB
     * 
     * @param \Cx\Core\ContentManager\Model\Entity\Page $page       The resolved page
     */
    public function postContentLoad(\Cx\Core\ContentManager\Model\Entity\Page $page) {
        switch ($this->cx->getMode()) {
            case \Cx\Core\Core\Controller\Cx::MODE_FRONTEND:
                $objTemplate = $this->cx->getTemplate();

                // ACCESS: parse access_logged_in[1-9] and access_logged_out[1-9] blocks
                \FWUser::parseLoggedInOutBlocks($objTemplate);

                // currently online users
                $objAccessBlocks = false;
                if ($objTemplate->blockExists('access_currently_online_member_list')) {
                    if (\FWUser::showCurrentlyOnlineUsers() && ( $objTemplate->blockExists('access_currently_online_female_members') || $objTemplate->blockExists('access_currently_online_male_members') || $objTemplate->blockExists('access_currently_online_members'))) {
                            $objAccessBlocks = new AccessBlocks();
                        if ($objTemplate->blockExists('access_currently_online_female_members'))
                            $objAccessBlocks->setCurrentlyOnlineUsers('female');
                        if ($objTemplate->blockExists('access_currently_online_male_members'))
                            $objAccessBlocks->setCurrentlyOnlineUsers('male');
                        if ($objTemplate->blockExists('access_currently_online_members'))
                            $objAccessBlocks->setCurrentlyOnlineUsers();
                    } else {
                        $objTemplate->hideBlock('access_currently_online_member_list');
                    }
                }

                // last active users
                if ($objTemplate->blockExists('access_last_active_member_list')) {
                    if (\FWUser::showLastActivUsers() && ( $objTemplate->blockExists('access_last_active_female_members') || $objTemplate->blockExists('access_last_active_male_members') || $objTemplate->blockExists('access_last_active_members'))) {
                        if (!$objAccessBlocks)
                            $objAccessBlocks = new AccessBlocks();
                        if ($objTemplate->blockExists('access_last_active_female_members'))
                            $objAccessBlocks->setLastActiveUsers('female');
                        if ($objTemplate->blockExists('access_last_active_male_members'))
                            $objAccessBlocks->setLastActiveUsers('male');
                        if ($objTemplate->blockExists('access_last_active_members'))
                            $objAccessBlocks->setLastActiveUsers();
                    } else {
                        $objTemplate->hideBlock('access_last_active_member_list');
                    }
                }

                // latest registered users
                if ($objTemplate->blockExists('access_latest_registered_member_list')) {
                    if (\FWUser::showLatestRegisteredUsers() && ( $objTemplate->blockExists('access_latest_registered_female_members') || $objTemplate->blockExists('access_latest_registered_male_members') || $objTemplate->blockExists('access_latest_registered_members'))) {
                        if (!$objAccessBlocks)
                            $objAccessBlocks = new AccessBlocks();
                        if ($objTemplate->blockExists('access_latest_registered_female_members'))
                            $objAccessBlocks->setLatestRegisteredUsers('female');
                        if ($objTemplate->blockExists('access_latest_registered_male_members'))
                            $objAccessBlocks->setLatestRegisteredUsers('male');
                        if ($objTemplate->blockExists('access_latest_registered_members'))
                            $objAccessBlocks->setLatestRegisteredUsers();
                    } else {
                        $objTemplate->hideBlock('access_latest_registered_member_list');
                    }
                }

                // birthday users
                if ($objTemplate->blockExists('access_birthday_member_list')) {
                    if (\FWUser::showBirthdayUsers() && ( $objTemplate->blockExists('access_birthday_female_members') || $objTemplate->blockExists('access_birthday_male_members') || $objTemplate->blockExists('access_birthday_members'))) {
                        if (!$objAccessBlocks)
                            $objAccessBlocks = new AccessBlocks();
                        if ($objAccessBlocks->isSomeonesBirthdayToday()) {
                            if ($objTemplate->blockExists('access_birthday_female_members'))
                                $objAccessBlocks->setBirthdayUsers('female');
                            if ($objTemplate->blockExists('access_birthday_male_members'))
                                $objAccessBlocks->setBirthdayUsers('male');
                            if ($objTemplate->blockExists('access_birthday_members'))
                                $objAccessBlocks->setBirthdayUsers();
                            $objTemplate->touchBlock('access_birthday_member_list');
                        } else {
                            $objTemplate->hideBlock('access_birthday_member_list');
                        }
                    } else {
                        $objTemplate->hideBlock('access_birthday_member_list');
                    }
                }
                break;


            default:
                break;
        }
    }
    
    /**
     * Do something after resolving is done
     * 
     * @param \Cx\Core\ContentManager\Model\Entity\Page $page       The resolved page
     */
    public function postResolve(\Cx\Core\ContentManager\Model\Entity\Page $page) {
        switch ($this->cx->getMode()) {
            case \Cx\Core\Core\Controller\Cx::MODE_BACKEND:
                
                global $plainCmd, $isRegularPageRequest;
                $objTemplate = $this->cx->getTemplate();
                $objFWUser = \FWUser::getFWUserObject();

                /* authentification */
                $loggedIn = $objFWUser->objUser->login(true); //check if the user is already logged in
                if (!empty($_POST) && !$loggedIn &&
                        (
                        (!isset($_GET['cmd']) || $_GET['cmd'] !== 'Login') &&
                        (!isset($_GET['act']) || $_GET['act'] !== 'resetpw')
                        )) { //not logged in already - do captcha and password checks
                    $objFWUser->checkAuth();
                }

                // User only gets the backend if he's logged in
                if (!$objFWUser->objUser->login(true)) {
                    $plainCmd = 'Login';
                    // If the user isn't logged in, the login mask will be showed.
                    // This mask has its own template handling.
                    // So we don't need to load any templates in the index.php.
                    $isRegularPageRequest = false;
                } else {
                    $userData = array(
                        'id' => \FWUser::getFWUserObject()->objUser->getId(),
                        'name' => \FWUser::getFWUserObject()->objUser->getUsername(),
                    );
                    \Env::get('cx')->getDb()->setUsername(json_encode($userData));
                }

                $objUser = \FWUser::getFWUserObject()->objUser;
                $firstname = $objUser->getProfileAttribute('firstname');
                $lastname = $objUser->getProfileAttribute('lastname');

                if (!empty($firstname) && !empty($lastname)) {
                    $txtProfile = $firstname . ' ' . $lastname;
                } else {
                    $txtProfile = $objUser->getUsername();
                }

                $objTemplate->setVariable(array(
                    'TXT_PROFILE' => $txtProfile,
                    'USER_ID' => $objFWUser->objUser->getId(),
                ));


                if (isset($_POST['redirect']) && preg_match('/\.php/', $_POST['redirect'])) {
                    $redirect = \FWUser::getRedirectUrl(urlencode($_POST['redirect']));
                    \Cx\Core\Csrf\Controller\ComponentController::header('location: ' . $redirect);
                }
                break;

            default:
                break;
        }
    }
    
    
}