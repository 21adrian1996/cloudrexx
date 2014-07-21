<?php
/**
 * Class ComponentController
 *
 * @copyright   CONTREXX CMS - Comvation AG Thun
 * @author      Ueli Kramer <ueli.kramer@comvation.com>
 * @author      Sudhir Parmar <sudhirparmar@cdnsol.com>
 * @package     contrexx
 * @subpackage  coremodule_multisite
 * @version     1.0.0
 */

namespace Cx\Core_Modules\MultiSite\Controller;

/**
 * Class MultisiteException
 */
class MultiSiteException extends \Exception {}

/**
 * Class ComponentController
 *
 * The main Multisite component
 *
 * @copyright   CONTREXX CMS - Comvation AG Thun
 * @author      Ueli Kramer <ueli.kramer@comvation.com>
 * @author      Sudhir Parmar <sudhirparmar@cdnsol.com>
 * @package     contrexx
 * @subpackage  coremodule_multisite
 * @version     1.0.0
 */
class ComponentController extends \Cx\Core\Core\Model\Entity\SystemComponentController implements \Cx\Core\Json\JsonAdapter {
   // const MAX_WEBSITE_NAME_LENGTH = 18; 
    private $messages = '';
    private $reminders = array(3, 14);
    protected $db;
    /*
     * Constructor
     */
    public function __construct(\Cx\Core\Core\Model\Entity\SystemComponent $systemComponent, \Cx\Core\Core\Controller\Cx $cx) {
        parent::__construct($systemComponent, $cx);
        //multisite configuration setting
        self::errorHandler();
    }
    
    public function getControllersAccessableByJson() { 
        return array(
            'RoutingAdapter', 'ComponentController', 'JsonMultiSite'
        );
    }

    public function getAccessableMethods() {
        return array('cleanup', 'sendMails', 'lostPassword');
    }

    public function getMessagesAsString() {
        return $this->messages;
    }

    public function getName() {
        return 'MultiSite';
    }

    /**
     * Returns default permission as object
     * @return Object
     */
    public function getDefaultPermissions() {
        return null;
    }
    
    /**
     * @param array $params the parameters
     */
    public function sendMails($params) {
// TODO: refactor whole method
//       -> cronjob might be running on Website Manager Server
//       -> there we have all information about the websites in the repository
//       no need for strange methods like $website->getDefaultLanguageId()
throw new MultiSiteException('Refactor this method!');

        if ($_SERVER['SERVER_ADDR'] != $_SERVER['REMOTE_ADDR']) {
            exit;
        }
        $get = $params['get'];
        $daysInPast = intval($get['days']);
        if (!in_array($daysInPast, $this->reminders)) {
            throw new MultiSiteException("The day " . $daysInPast . " is not possible");
        }
        $instRepo = new \Cx\Core_Modules\MultiSite\Model\Repository\WebsiteRepository();

        $mktime = strtotime('-' . $daysInPast . 'days');
        $start = strtotime(date('Y-m-d 00:00:00', $mktime));
        $end = strtotime(date('Y-m-d 23:59:59', $mktime));

        $websites = $instRepo->findByCreatedDateRange($this->websitePath, $start, $end);

        \MailTemplate::init('MultiSite');
        foreach ($websites as $website) {
            if (!\MailTemplate::send(array(
                'lang_id' => $website->getOwner()->getBackendLanguage(),
                'section' => 'MultiSite',
                'key' => 'reminder' . $daysInPast . 'days',
                'to' => $website->getMail(),
                'search' => array(),
                'replace' => array(),
            ))) {
                throw new MultiSiteException('Could not send reminder to ' . $website->getMail() . ' (Mail send failed)');
            }
        }
        return true;
    }

    /**
     * The user lost the password
     *
     * @param array $params the parameters of post and get array
     * @return bool
     * @throws MultiSiteRoutingException
     * @throws MultiSiteException
     * @throws \Exception
     */
    public function lostPassword($params) {
        global $_ARRAYLANG;

        if (empty($params['post'])) {
            $rawPostData = file_get_contents("php://input");
            if (!empty($rawPostData) && ($arrRawPostData = explode('&', $rawPostData)) && !empty($arrRawPostData)) {
                $arrPostData = array();
                foreach ($arrRawPostData as $postData) {
                    if (!empty($postData)) {
                        list($postKey, $postValue) = explode('=', $postData);
                        $arrPostData[$postKey] = $postValue;
                    }
                }
                $params['post'] = $arrPostData;
            }
        }
        
        if (empty($params['get']['name']) && empty($params['post']['name'])) {
            if (preg_match('/https:\/\/(.+)\.'.\Cx\Core\Setting\Controller\Setting::getValue('multiSiteDomain').'/', $_SERVER['HTTP_REFERER'], $matches)) {
                $params['post']['name'] = $matches[1];
            } else {
                throw new \Exception("not enough arguments!");
            }
        }

        $lang = 'de';
        if (isset($params['get']) && isset($params['get']['language'])) {
            $lang = $params['get']['language'];
        }
        if (isset($params['post']) && isset($params['post']['lang'])) {
            $lang = $params['post']['lang'];
            $params['post']['language'] = $lang;
        }
        $langId = \FWLanguage::getLanguageIdByCode($lang);
        \Env::get('ClassLoader')->loadFile(ASCMS_CORE_MODULE_PATH.'/MultiSite/lang/' . $lang . '/backend.php');

        $instRepo = new \Cx\Core_Modules\MultiSite\Model\Repository\WebsiteRepository();
        $websiteName = isset($params['get']['name']) ? $params['get']['name'] : $params['post']['name'];
        /**
         * @var \Cx\Core_Modules\MultiSite\Model\Entity\Websites $website
         */
        $website = $instRepo->findByName($this->websitePath, $websiteName);
        if (!$website) {
            throw new MultiSiteRoutingException($_ARRAYLANG['TXT_CORE_MODULE_MULTISITE_NO_SUCH_WEBSITE_WITH_NAME']);
        }

        $jd = new \Cx\Core\Json\JsonData();
        // used by jsonUser
        $params['post']['email'] = $website->getMail();
        $params['post']['sendMail'] = false;

        // used by routing of a.
        // index.php?cmd=jsondata&object=RoutingAdapter&act=route&mail=" + $("#email").val() + "&adapter=user&method=lostPassword
        $get = array(
            'adapter' => 'user',
            'method' => 'lostPassword',
            'mail' => $website->getMail(),
        );
        $get = array_merge($params['get'], $get);
        $response = $jd->jsondata('RoutingAdapter', 'route', array('get' => $get, 'post' => $params['post']));
        $response = json_decode($response);
        if ($response->status !== 'success') {
            throw new MultiSiteException('Unable to restore password for website!');
        }
        $restoreLink = isset($response->data->restoreLink) ? $response->data->restoreLink : null;
        if (!$restoreLink) {
            throw new MultiSiteException('Something went wrong. Could not restore the user.');
        }

        \MailTemplate::init('MultiSite');
        if (!\MailTemplate::send(array(
            'section' => 'MultiSite',
            'lang_id' => $langId,
            'key' => 'lostPassword',
            'to' => $website->getMail(),
            'search' => array('[[WEBSITE_NAME]]', '[[WEBSITE_MAIL]]', '[[WEBSITE_RESTORE_LINK]]'),
            'replace' => array($website->getName(), $website->getMail(), $restoreLink),
        ))) {
            throw new MultiSiteException('Could not restore password (Mail send failed)');
        }

        $this->messages = $response->message;
        return true;
    }

    /**
     * Fixes database errors.   
     *
     * @return  boolean                 False.  Always.
     * @throws  \Cx\Lib\Update_DatabaseException
     */
    static function errorHandler()
    {
        global $_CONFIG;
        
        try {
            \Cx\Core\Setting\Controller\Setting::init('MultiSite', '','FileSystem');

            // abort in case the Contrexx installation is in MultiSite website operation mode
            if (\Cx\Core\Setting\Controller\Setting::getValue('mode') == 'website') {
                return false;
            }

            // config group
            \Cx\Core\Setting\Controller\Setting::init('MultiSite', 'config','FileSystem');
            if (\Cx\Core\Setting\Controller\Setting::getValue('mode') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('mode','none', 1,
                \Cx\Core\Setting\Controller\Setting::TYPE_DROPDOWN, 'none:none,manager:manager,service:service,hybrid:hybrid', 'config')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Database Mode");
            }

            // setup group
            \Cx\Core\Setting\Controller\Setting::init('MultiSite', 'setup','FileSystem');
            if (\Cx\Core\Setting\Controller\Setting::getValue('websiteController') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('websiteController','xampp', 1,
                \Cx\Core\Setting\Controller\Setting::TYPE_DROPDOWN, 'xampp:XAMPP,plesk:Plesk', 'setup')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Database user website Controller");
            }
            if (\Cx\Core\Setting\Controller\Setting::getValue('multiSiteDomain') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('multiSiteDomain',$_CONFIG['domainUrl'], 2,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXT, null, 'setup')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Database multiSite Domain");
            }
            if (\Cx\Core\Setting\Controller\Setting::getValue('unavailablePrefixes') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('unavailablePrefixes', 'account,admin,demo,dev,mail,media,my,staging,test,www', 3,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXTAREA, null, 'setup')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Unavailable website names");
            }
            if (\Cx\Core\Setting\Controller\Setting::getValue('websiteNameMaxLength') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('websiteNameMaxLength',80, 4,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXT, null, 'setup')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Maximal length of website names");
            }
            if (\Cx\Core\Setting\Controller\Setting::getValue('websiteNameMinLength') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('websiteNameMinLength',4, 5,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXT, null, 'setup')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Minimal length of website names");
            }

            // websiteSetup group
            \Cx\Core\Setting\Controller\Setting::init('MultiSite', 'websiteSetup','FileSystem');
            if (\Cx\Core\Setting\Controller\Setting::getValue('websitePath') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('websitePath',\Env::get('cx')->getCodeBaseDocumentRootPath().'/websites', 1,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXT, null, 'websiteSetup')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for websites path");
            }
            if (\Cx\Core\Setting\Controller\Setting::getValue('defaultCodeBase') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('defaultCodeBase',\Env::get('cx')->getCodeBaseDocumentRootPath(), 2,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXT, null, 'websiteSetup')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add SettingDb entry for Database Default code base");
            }
            if (\Cx\Core\Setting\Controller\Setting::getValue('websiteDatabaseHost') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('websiteDatabaseHost','localhost', 3,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXT, null, 'websiteSetup')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for website database host");
            }
            if (\Cx\Core\Setting\Controller\Setting::getValue('websiteDatabasePrefix') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('websiteDatabasePrefix','cloudrexx_', 4,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXT, null, 'websiteSetup')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Database prefix for websites");
            }
            if (\Cx\Core\Setting\Controller\Setting::getValue('websiteDatabaseUserPrefix') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('websiteDatabaseUserPrefix','clx_', 5,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXT, null, 'websiteSetup')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Database user prefix for websites");
            }

            // websiteManager group
            \Cx\Core\Setting\Controller\Setting::init('MultiSite', 'websiteManager','FileSystem');
            if (\Cx\Core\Setting\Controller\Setting::getValue('managerHostname') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('managerHostname',$_CONFIG['domainUrl'], 1,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXT, null, 'websiteManager')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Database Manager Hostname");
            }
            if (\Cx\Core\Setting\Controller\Setting::getValue('managerSecretKey') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('managerSecretKey','', 2,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXT, null, 'websiteManager')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Database Manager Secret Key");
            }
            if (\Cx\Core\Setting\Controller\Setting::getValue('managerInstallationId') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('managerInstallationId','', 3,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXT, null, 'websiteManager')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Database Manager Installation Id");
            }
            if (\Cx\Core\Setting\Controller\Setting::getValue('managerHttpAuthMethod') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('managerHttpAuthMethod','', 4,
                \Cx\Core\Setting\Controller\Setting::TYPE_DROPDOWN, 'none:none, basic:basic, digest:digest', 'websiteManager')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Database Manager HTTP Authentication Method");
            }
            if (\Cx\Core\Setting\Controller\Setting::getValue('managerHttpAuthUsername') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('managerHttpAuthUsername','', 5,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXT, null, 'websiteManager')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Database Manager HTTP Authentication Username");
            }
            if (\Cx\Core\Setting\Controller\Setting::getValue('managerHttpAuthPassword') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('managerHttpAuthPassword','', 6,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXT, null, 'websiteManager')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Database Manager HTTP Authentication Password");
            }
            
            // plesk group
            \Cx\Core\Setting\Controller\Setting::init('MultiSite', 'plesk','FileSystem');
            if (\Cx\Core\Setting\Controller\Setting::getValue('pleskHost') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('pleskHost','localhost', 1,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXT, null, 'plesk')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Database user plesk Host");
            }
            if (\Cx\Core\Setting\Controller\Setting::getValue('pleskLogin') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('pleskLogin','', 2,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXT, null, 'plesk')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Database user plesk Login");
            }
            if (\Cx\Core\Setting\Controller\Setting::getValue('pleskPassword') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('pleskPassword','', 3,
                \Cx\Core\Setting\Controller\Setting::TYPE_PASSWORD,'plesk')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Database user plesk Password");
            }
            if (\Cx\Core\Setting\Controller\Setting::getValue('pleskIp') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('pleskIp','', 4,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXT, null, 'plesk')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Database user plesk IP");
            }
            if (\Cx\Core\Setting\Controller\Setting::getValue('pleskWebsitesSubscriptionId') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('pleskWebsitesSubscriptionId',0, 5,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXT, null, 'plesk')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Database user plesk Subscription Id");
            }
            if (\Cx\Core\Setting\Controller\Setting::getValue('pleskMasterSubscriptionId') === NULL
                && !\Cx\Core\Setting\Controller\Setting::add('pleskMasterSubscriptionId',0, 6,
                \Cx\Core\Setting\Controller\Setting::TYPE_TEXT, null, 'plesk')){
                    throw new \Cx\Lib\Update_DatabaseException("Failed to add Setting entry for Database ID of master subscription");
            }
        } catch (\Exception $e) {
            \DBG::msg($e->getMessage());
        }
        // Always
        return false;
    }

    public function preInit(\Cx\Core\Core\Controller\Cx $cx) {
        // Abort in case the request has not been made to either the frontend nor the backend
        if (!in_array($cx->getMode(), array($cx::MODE_FRONTEND, $cx::MODE_BACKEND))) {
            return;
        }

        // Abort in case this Contrexx installation has not been set up as a Website Service.
        // If the MultiSite module has not been configured, then 'mode' will be set to null.
        switch (\Cx\Core\Setting\Controller\Setting::getValue('mode')) {
            case 'service':
            case 'hybrid':
                $this->deployWebsite($cx);
                break;

            case 'website':
// TODO: Website specific customizings can be added at this point
//       Extensions like access restrictions to certain parts of the system, etc.
                break;

// TODO: workaround to load the themes from the CodeBase as no themes in the Data Repository of the Website do exist at this point
                //\Env::get('cx')->websiteThemesPath = \Env::get('cx')->getCodeBaseDocumentRootPath() . '/themes';
            default:
                break;
        }
    }

    private function deployWebsite(\Cx\Core\Core\Controller\Cx $cx) {
        $multiSiteRepo = new \Cx\Core_Modules\MultiSite\Model\Repository\WebsiteRepository();
// TODO: add support for requests to domain aliases (i.e.: example.com)
        $websiteName = substr($_SERVER['HTTP_HOST'], 0, -strlen('.'.\Cx\Core\Setting\Controller\Setting::getValue('multiSiteDomain')));
        $website = $multiSiteRepo->findByName(\Cx\Core\Setting\Controller\Setting::getValue('websitePath').'/', $websiteName);
        if ($website) {
            $configFile = \Cx\Core\Setting\Controller\Setting::getValue('websitePath').'/'.$websiteName.'/config/configuration.php';
            \DBG::msg("MultiSite: Loading customer Website {$website->getName()}...");
            \Cx\Core\Core\Controller\Cx::instanciate(\Env::get('cx')->getMode(), true, $configFile);
            exit;
        }

        // no website found. Abort website-deployment and let Contrexx process with the regular system initialization (i.e. most likely with the Website Service Website)
        return false;
    }
}
