<?php

/**
 * JSON Adapter for Multisite
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Sudhir Parmar <sudhirparmar@cdnsol.com>
 * @version     4.0.0
 * @package     contrexx
 * @subpackage  Multisite
*/

namespace Cx\Core_Modules\MultiSite\Controller;

class MultiSiteJsonException extends \Exception {}
/**
 * JSON Adapter for Multisite
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Sudhir Parmar <sudhirparmar@cdnsol.com>
 * @version     4.0.0
 * @package     contrexx
 * @subpackage  Multisite
*/
class JsonMultiSite implements \Cx\Core\Json\JsonAdapter {

    /**
    * Returns the internal name used as identifier for this adapter
    * @return String Name of this adapter
    */
    public function getName() {
        return 'MultiSite';
    }

    /**
    * Returns an array of method names accessable from a JSON request
    * @return array List of method names
    */
    public function getAccessableMethods() {
        return array(
            'signup'        => new \Cx\Core\Access\Model\Entity\Permission(array('https'), array('post'), false),
            'createWebsite'=> new \Cx\Core\Access\Model\Entity\Permission(array('https'), array('post'), false, array($this, 'auth')),
        );
    }

    /**
    * Returns all messages as string
    * @return String HTML encoded error messages
    */
    public function getMessagesAsString() {
        return '';
    }
    
    /**
     * Returns default permission as object
     * @return Object
     */
    public function getDefaultPermissions() {
        return null;
    }
    
    /**
    * function signup 
    */
    public function signup($params){
        // load text-variables of module MultiSite
        global $_ARRAYLANG, $objInit;
        $langData = $objInit->loadLanguageData('MultiSite');
        $_ARRAYLANG = array_merge($_ARRAYLANG, $langData);

        $objUser = new \Cx\Core_Modules\MultiSite\Model\Entity\User();
        if (!empty($params['post'])) {
            $post = $params['post'];
            $websiteName = contrexx_input2raw($post['websiteName']);
            //set email of the new user
            $objUser->setEmail(contrexx_input2raw($post['email']));
            //set frontend language id 
            $objUser->setFrontendLanguage(contrexx_input2raw($post['langId']));
            //set backend language id 
            $objUser->setBackendLanguage(contrexx_input2raw($post['langId']));
            //set password 
            $objUser->setPassword($objUser->make_password(8, true));
            //call \User\store function to store all the info of new user
            $objUser->store();
            //call createWebsite method.
            return $this->createWebsite($objUser,$websiteName);
        }
    }
   
    /**
     * Creates a new website
     * @param type $params  
    */
    public function createWebsite($params,$websiteName='') {
        // load text-variables of module MultiSite
        global $_ARRAYLANG, $objInit;
        if (is_array($params)) {
            $objUser = new \Cx\Core_Modules\MultiSite\Model\Entity\User();
            //set email of the new user
            $objUser->setEmail(contrexx_input2raw($params['post']['userEmail']));
            //set user id of the new user
            $objUser->setId(contrexx_input2raw($params['post']['userId']));
            $websiteId = contrexx_input2raw($params['post']['websiteId']);
            $websiteName = contrexx_input2raw($params['post']['websiteName']);
        } else {
            $objUser = $params;
            $websiteId = '';
        }
        //load language file 
        $langData = $objInit->loadLanguageData('MultiSite');
        $_ARRAYLANG = array_merge($_ARRAYLANG, $langData);
        
        $basepath = \Cx\Core\Setting\Controller\Setting::getValue('websitePath');
        $websiteServiceServer = null;
        if (\Cx\Core\Setting\Controller\Setting::getValue('mode') == 'manager') {
            //get default service server
            $defaultWebsiteServiceServer = \Env::get('em')->getRepository('Cx\Core_Modules\MultiSite\Model\Entity\WebsiteServiceServer')
            ->findBy(array('isDefault' => 1));
            $websiteServiceServer = $defaultWebsiteServiceServer[0];
        }

        try {
            $ObjWebsite = new \Cx\Core_Modules\MultiSite\Model\Entity\Website($basepath, $websiteName, $websiteServiceServer, $objUser, false);
            if($websiteId!=''){
                $ObjWebsite->setId($websiteId);
            }
            \Env::get('em')->persist($ObjWebsite);
            \Env::get('em')->flush();
            return $ObjWebsite->setup();
        } catch (\Cx\Core_Modules\MultiSite\Model\Entity\WebsiteException $e) {
            throw new MultiSiteJsonException($e->getMessage());    
        }
    }
    /*
     * query method called from json request
     * @param $params parameter passed by jsonData method
     * */
    /* TODO: remove this method after fully tested with the 
     * createWebsite()
    public function query($params){
        try {
            if (!empty($params['post']['command'])
                && $params['post']['command'] == 'createWebsite') {
                $objUser = new \Cx\Core_Modules\MultiSite\Model\Entity\User();
                //set email of the new user
                $objUser->setEmail($params['post']['userEmail']);
                //set user id of the new user
                $objUser->setId($params['post']['userId']);
                $websiteId = $params['post']['websiteId'];
           
                return $this->createWebsite($objUser, $params['post']['websiteName']);
            }
        } catch (\Exception $e) {
            return $e->getMessage();
        }
       
    }
     */ 
    /**
     *  callback authentication for verifing secret key and installation id based on mode
     * 
     * @return boolean
     */
    public function auth(array $params = array()) 
    {
        $authenticationValue = isset($params['post']['auth']) ? json_decode($params['post']['auth'], true) : '';

        if (empty($authenticationValue) || !is_array($authenticationValue)) {
            return false;
        }
    
        switch (\Cx\Core\Setting\Controller\Setting::getValue('mode')) {
            case 'manager':
                try {
                    $WebsiteServiceServerRepository = \Env::get('em')->getRepository('\Cx\Core_Modules\MultiSite\Model\Entity\WebsiteServiceServer');
                    $objWebsiteService = $WebsiteServiceServerRepository->findBy(array('hostName' => $authenticationValue['sender']));
                    $secretKey = $objWebsiteService->getSecretKey();
                    $installationId = $objWebsiteService->getInstallationId();

                    if (md5($secretKey.$installationId) === $authenticationValue['key']) {
                        return true;
                    }
                } catch(\Exception $e) {
                    return $e->getMessage();
                }
                break;

            case 'service':
            case 'hybrid':
                $secretKey = \Cx\Core\Setting\Controller\Setting::getValue('managerSecretKey');
                $installationId = \Cx\Core\Setting\Controller\Setting::getValue('managerInstallationId');

                if (md5($secretKey.$installationId) === $authenticationValue['key']) {
                    return true;
                }
                break;
        }
        return false;
    }

    /**
     *  Get the Authentication Object
     * 
     * @param String $secretKey
     * @param String $remoteInstallationId
     * 
     * @return json
     */
    public static function getAuthenticationObject($secretKey, $remoteInstallationId) 
    {
        $key = md5($secretKey . $remoteInstallationId);
        $config = \Env::get('config');

        return json_encode(array(
            'key'     => $key,
            'sender' => $config['domainUrl'],
        ));
    }
    /**
     *  Get the auto-generated SecretKey
     * 
     * @return string 
     */
    public static function generateSecretKey(){
        return bin2hex(openssl_random_pseudo_bytes(16));    
    }
}
