<?php
/**
 * Class PleskDbController
 *
 * This is the PleskDb Controller class.
 *
 * @copyright   CONTREXX CMS - Comvation AG Thun
 * @author      Sudhir Parmar 
 * @package     contrexx
 * @subpackage  coremodule_MultiSite
 * @version     1.0.0
 */

namespace Cx\Core_Modules\MultiSite\Controller;
/**

 * Reports error during API RPC request

 */
class ApiRequestException extends DbControllerException {}
/**
 * Class PleskDbController
 *
 * Controller clask to call plesk api for database creation,
 * database user creation etc.
 *
 * @copyright   CONTREXX CMS - Comvation AG Thun
 * @author      Sudhir Parmar
 * @package     contrexx
 * @subpackage  coremodule_MultiSite
 * @version     1.0.0
 */
class PleskController implements \Cx\Core_Modules\MultiSite\Controller\DbController,
                                 \Cx\Core_Modules\MultiSite\Controller\SubscriptionController,
                                 \Cx\Core_Modules\MultiSite\Controller\FtpController,
                                 \Cx\Core_Modules\MultiSite\Controller\DnsController {
    
    /**
     * hostname for the plesk panel 
     */
    protected $host;
    
    /**
     * login username for the plesk panel 
     */
    protected $login;
    
    /**
     * login password for the plesk panel 
     */
    protected $password;
    
    protected $webspaceId;
    
    /** 
     * Password for the newly created database user
     */
    protected $dbPassword;   
    
    /**
     * Version of the plesk api rpc
     */
    const API_RPC_VERSION = '1.6.5.0';
    
    /**
     * Constructor
     */
    public function __construct($host, $login, $password){
        $this->host = $host;
        $this->login = $login;
        $this->password = $password;
    }

    /**
     * function to set webspace id
     * @param $webspaceId webspace id to set
     */
    public function setWebspaceId($webspaceId){
        $this->webspaceId = $webspaceId;    
    }
    
    /**
     * function to get webspace id
     * @return webspace id
     */
    public function getWebspaceId(){
        return $this->webspaceId;
    }

    /**
     * Creates a DB user
     * @param string $name Name for the new user
     */
    public function createDbUser(\Cx\Core\Model\Model\Entity\DbUser $user){
        return $user;
    }

    /**
     * Creates a DB
     * @param string $name Name for the new database
     * @param \Cx\Core\Model\Model\Entity\DbUser $user (optional) Database user to grant rights for this DB, if null is given a new User is created
     * @return \Cx\Core\Model\Model\Entity\Db Abstract representation of the created database
     */
    public function createDb(\Cx\Core\Model\Model\Entity\Db $db, \Cx\Core\Model\Model\Entity\DbUser $user = null){
        $web_id = $this->getWebspaceId();
        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);
        $database = $xmldoc->createElement('database');  
        $packet->appendChild($database);            
        $add_db = $xmldoc->createElement('add-db');	
        $database->appendChild($add_db);
        $webspace_id = $xmldoc->createElement('webspace-id',$web_id);	
        $add_db->appendChild($webspace_id);
        $nameTag = $xmldoc->createElement('name',$db->getName());	
        $type = $xmldoc->createElement('type','mysql'); //type of the database ie postgray, mssql or mysql
        $add_db->appendChild($nameTag);
        $add_db->appendChild($type);
        $response = $this->executeCurl($xmldoc);
        $resultNode = $response->database->{'add-db'}->result;
        $systemError = $response->system->errtext;
        if ('error' == (string)$resultNode->status || $systemError) {
            \DBG::dump($xmldoc->saveXML());
            \DBG::dump($response);
            $error = (isset($systemError)?$systemError:$resultNode->errtext);
            throw new ApiRequestException("Error in creating database:{$error} ");
        } else {
            $dbId = (string)$resultNode->id;           
            $db->setId($dbId);
            if ($user !== null) {
                $this->grantRightsToDb($user, $db);// create a db user if $user is not null   
            }
        }
        return $db;
    }

    /**
     * Removes a db user
     * @param \Cx\Core\Model\Model\Entity\DbUser $dbUser User to remove
     * @throws MultiSiteDbException On error
     */
    public function removeDbUser(\Cx\Core\Model\Model\Entity\DbUser $dbUser, \Cx\Core\Model\Model\Entity\Db $db ){
        \DBG::msg("MultiSite (PleskController): Removing Database User.");
       
        $dbUserName = $dbUser->getName();
        $dbId = $this->getDbId($db->getName());
        //if database does not exist return false
        if(empty($dbId)){
            return false;
        }
        $dbUserId   = $this->getDbUserId($dbUserName, $dbId);
        if (!empty($dbUserId)) {
            $xmldoc = $this->getXmlDocument();
            $packet = $this->getRpcPacket($xmldoc);

            $database = $xmldoc->createElement('database');
            $packet->appendChild($database);

            $getDbUsers = $xmldoc->createElement('del-db-user');
            $database->appendChild($getDbUsers);

            $filter = $xmldoc->createElement('filter');
            $getDbUsers->appendChild($filter);

            $id = $xmldoc->createElement('id', $dbUserId);
            $filter->appendChild($id);

            $response = $this->executeCurl($xmldoc);
            $resultNode = $response->{'database'}->{'del-db-user'}->result;
            $responseJson = json_encode($resultNode);
            $respArr = json_decode($responseJson, true);
            $systemError = $response->system->errtext;

            if ('error' == (string) $resultNode->status || $systemError) {
                \DBG::dump($xmldoc->saveXML());
                \DBG::dump($response);
                $error = (isset($systemError) ? $systemError : $resultNode->errtext);
                throw new ApiRequestException("Error in removing database user:{$error} ");
            }
            return $respArr;
        }
        return true;
    }
    
    /**
     * Removes a db
     * @param \Cx\Core\Model\Model\Entity\Db $db Database to remove
     * @throws MultiSiteDbException On error
     */
    public function removeDb(\Cx\Core\Model\Model\Entity\Db $db){
        \DBG::msg("MultiSite (PleskController): Removing Database.");
        
        $dbName = $db->getName();
        $databaseId = $this->getDbId($dbName); //get database id byname
        if ($databaseId) {
            $xmldoc = $this->getXmlDocument();
            $packet = $this->getRpcPacket($xmldoc);
            $database = $xmldoc->createElement('database');
            $packet->appendChild($database);
            $delDb = $xmldoc->createElement('del-db');
            $database->appendChild($delDb);
            $filter = $xmldoc->createElement('filter');
            $delDb->appendChild($filter);
            $dbId = $xmldoc->createElement('id', $databaseId);
            $filter->appendChild($dbId);
            $response = $this->executeCurl($xmldoc);
            $systemError = $response->system->errtext;
            $resultNode = $response->database->{'del-db'}->result;
            $responseJson = json_encode($resultNode);
            $respArr = json_decode($responseJson, true);
            if ('error' == (string) $resultNode->status || $systemError) {
                \DBG::dump($xmldoc->saveXML());
                \DBG::dump($response);
                $error = (isset($systemError) ? $systemError : $resultNode->errtext);
                throw new ApiRequestException("Error in removing database:{$error} ");
            }
            return $respArr;
        }
        
        return false;
    }

    /**
     * get id of a particular db by its name
     * @param $name name of the db we need id
     * @return id of the db
     */
    protected function getDbId($name){
        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);
        $database = $xmldoc->createElement('database');  
        $packet->appendChild($database);            
        $getDb = $xmldoc->createElement('get-db');	
        $database->appendChild($getDb);
        $filter = $xmldoc->createElement('filter');
        $getDb->appendChild($filter);
        $domainId = $this->webspaceId;
        $domainIdTag = $xmldoc->createElement('webspace-id',$domainId);
        $filter->appendChild($domainIdTag);
        //echo $xmldoc->saveXML();die;
        $response = $this->executeCurl($xmldoc);
        $responseJson = json_encode($response->database->{'get-db'});
        $respArr = json_decode($responseJson,true); 
        $systemError = $response->system->errtext;
        $resultNode = $response->database->{'get-db'}->result;
        if ('error' == (string)$resultNode->status || $systemError) {
            \DBG::dump($xmldoc->saveXML());
            \DBG::dump($response);
            $error = (isset($systemError)?$systemError:$resultNode->errtext);
            throw new ApiRequestException("Error in getting database ID : {$error} ");
        }
        if (!empty($respArr)) {
            $responseArr = $this->getFormattedResponse($respArr);
            foreach($responseArr as $res) {
                if ($res['name'] == $name) {
                    return $res['id'];
                }
            }
        }
    }
    
    /**
     * get id of a particular db user by its name
     * 
     * @param type $name name of the db user we need id
     * 
     * @return integer id of the db user
     * @throws ApiRequestException
     */
    public function getDbUserId($name, $dbId ) {
        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);       
        
        $database = $xmldoc->createElement('database');
        $packet->appendChild($database);
        
        $getDbUsers = $xmldoc->createElement('get-db-users');
        $database->appendChild($getDbUsers);
        
        $filter = $xmldoc->createElement('filter');
        $getDbUsers->appendChild($filter);
        
        $domainIdTag = $xmldoc->createElement('db-id', $dbId);
        $filter->appendChild($domainIdTag);
        
        $response = $this->executeCurl($xmldoc);
        $resultNode = $response->database->{'get-db-users'}->result;
        $responseJson = json_encode($response->database->{'get-db-users'});
        $respArr = json_decode($responseJson, true); 
        $systemError = $response->system->errtext;
        
        if ('error' == (string) $resultNode->status || $systemError) {
            \DBG::dump($xmldoc->saveXML());
            \DBG::dump($response);
            $error = (isset($systemError) ? $systemError : $resultNode->errtext);
            throw new ApiRequestException("Error in getting database User ID : {$error} ");
        }      
        
        $dbUserId    = 0;
        if (!empty($respArr)) {
            $responseArr = $this->getFormattedResponse($respArr);            
            foreach($responseArr as $result) {
                if (isset($result['login']) && $result['login'] == $name) {
                    $dbUserId = $result['id'];
                    break;
                }
            }
        }
        \DBG::dump($dbUserId);
        return $dbUserId;
    }
    
     /**
      * Grants user $user usage rights on database $database
      * @param \Cx\Core\Model\Model\Entity\DbUser $user Database user to grant rights for
      * @param \Cx\Core\Model\Model\Entity\Db $db Database to work on
      * @throws MultiSiteDbException On error
      */
    public function grantRightsToDb(\Cx\Core\Model\Model\Entity\DbUser $user, \Cx\Core\Model\Model\Entity\Db $database){
        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);
        $databaseTag = $xmldoc->createElement('database');  
        $packet->appendChild($databaseTag);            
        $addDbUser = $xmldoc->createElement('add-db-user');
        $databaseTag->appendChild($addDbUser);
        $databaseId = $database->getId();
        $dbId = $xmldoc->createElement('db-id',$databaseId);
        $addDbUser->appendChild($dbId);
        $dbUserName = $user->getName();
        $login = $xmldoc->createElement('login',$dbUserName);
        $addDbUser->appendChild($login);
        $dbpassword = $user->getPassword();
        $password = $xmldoc->createElement('password',htmlentities($dbpassword));
        $addDbUser->appendChild($password);
        $response = $this->executeCurl($xmldoc);
        $resultNode = $response->database->{'add-db-user'}->result;
        $systemError = $response->system->errtext;
        if ('error' == (string)$resultNode->status || $systemError) {
            \DBG::dump($xmldoc->saveXML());
            \DBG::dump($response);
            $error = (isset($systemError)?$systemError:$resultNode->errtext);
            throw new ApiRequestException("Error in granting database rights to user:{$error} ");
        }
        return $resultNode;    
    }

    /**
     * Revokes user $user all rights on database $database
     * @param \Cx\Core\Model\Model\Entity\DbUser $user Database user to revoke rights of
     * @param \Cx\Core\Model\Model\Entity\Db $db Database to work on
     * @throws MultiSiteDbException On error
     */
    public function revokeRightsToDb(\Cx\Core\Model\Model\Entity\DbUser $user, \Cx\Core\Model\Model\Entity\Db $database){
        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);
        $databaseTag = $xmldoc->createElement('database');  
        $packet->appendChild($databaseTag);            
        $delDbUser = $xmldoc->createElement('del-db-user');	
        $databaseTag->appendChild($delDbUser);
        $filter = $xmldoc->createElement('filter');	
        $delDbUser->appendChild($filter);
        $dbUserId = $user->getId(); 
        $id = $xmldoc->createElement('id',$dbUserId);	
        $filter->appendChild($id);	
        $response = $this->executeCurl($xmldoc);
        $resultNode = $response->database->{'del-db-user'}->result;
        $errcode = $resultNode->errcode;
        $systemError = $response->system->errtext;
        if ('error' == (string)$resultNode->status || $systemError) {
            \DBG::dump($xmldoc->saveXML());
            \DBG::dump($response);
            $error = (isset($systemError)?$systemError:$resultNode->errtext);
            throw new ApiRequestException("Error in revoking database rights from user:{$error} ");
        }
        return $response;	
    }
    
    /**
     * Set database password for new user
     * @param $dbPass password to set for db user
     */
    public function setDbPassword($dbPass){
        $this->dbPassword = $dbPas;
    }

    /**
     * get database password
     * @return dbPassword
     */
    public function getDbPassword(){
        return $this->dbPassword;
    }
    
    /**
     * Prepares CURL to perform the Panel API request
     * @return resource
     */
    protected function curlInit($host, $login, $password)
    {
          $curl = curl_init();

          curl_setopt($curl, CURLOPT_URL, "https://{$host}:8443/enterprise/control/agent.php");
          curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
          curl_setopt($curl, CURLOPT_POST,           true);
          curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, false);
          curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, false);
          curl_setopt($curl, CURLOPT_HTTPHEADER,

                 array("HTTP_AUTH_LOGIN: {$login}",
                        "HTTP_AUTH_PASSWD: {$password}",
                        "HTTP_PRETTY_PRINT: TRUE",
                        "Content-Type: text/xml")
          );
          return $curl;
    }

    /**
     * Performs a Panel API request, returns raw API response text
     *
     * @return string
     * @throws ApiRequestException
     */
    protected function sendRequest($curl, $packet)
    {
          curl_setopt($curl, CURLOPT_POSTFIELDS, $packet);

          $result = curl_exec($curl);

          if (curl_errno($curl)) {

                 $errmsg  = curl_error($curl);

                 $errcode = curl_errno($curl);

                 curl_close($curl);

                 throw new ApiRequestException($errmsg, $errcode);
          }
          curl_close($curl);
          return $result;
    }

    /**
     * Looks if API responded with correct data
     *
     * @return SimpleXMLElement
     * @throws ApiRequestException
     */
    protected function parseResponse($responseString)
    {
        $xml = new \SimpleXMLElement($responseString);
        if (!is_a($xml, 'SimpleXMLElement')) {
            throw new ApiRequestException("Cannot parse server response: {$response_string}");
        }
        return $xml;
    }

    /**
     * Send request to the plesk api
     * @param $requestXML request packet xml
     * @return SimpleXMLElement
     * @throws ApiRequestException
     */
    protected function executeCurl($requestXML){
        $curl = $this->curlInit($this->host, $this->login, $this->password);
        try {
              $response = $this->sendRequest($curl, $requestXML->saveXML());
              $responseXml = $this->parseResponse($response);
              return $responseXml;
        } catch (ApiRequestException $e) {
              return $e;
              die();
        }	
    }
    
    /**
     * Static function to set default configuration 
     */
    public static function fromConfig(){
        $pleskHost=\Cx\Core\Setting\Controller\Setting::getValue('pleskHost');
        $pleskLogin=\Cx\Core\Setting\Controller\Setting::getValue('pleskLogin');
        $pleskPassword=\Cx\Core\Setting\Controller\Setting::getValue('pleskPassword');
        return new static($pleskHost, $pleskLogin, $pleskPassword);
    }
    
    
    /**
     * Create a Customer
     * @param \Cx\Core_Modules\MultiSite\Model\Entity\Customer $customer
     * @throws $response array
     */
    public function createCustomer(\Cx\Core_Modules\MultiSite\Model\Entity\Customer $customer){
        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);
        $customerTag = $xmldoc->createElement('customer');
        $packet->appendChild($customerTag);
        $addTag = $xmldoc->createElement('add');
        $customerTag->appendChild($addTag);        
        $genInfo = $xmldoc->createElement('gen_info');
        $addTag->appendChild($genInfo);
        $customerArr = $customer->getCustomerInfo();//need to pass preformatted array on calling
        foreach($customerArr as $key=>$val){
            $customerInfo = $xmldoc->createElement($key,htmlentities($val));
            $genInfo->appendChild($customerInfo);
        }
        $response = $this->executeCurl($xmldoc);
        $resultNode = $response->customer->{'add'}->result;
        $errcode = $resultNode->errcode;
        $systemError = $response->system->errtext;
        if ('error' == (string)$resultNode->status || $systemError){
            \DBG::dump($xmldoc->saveXML());
            \DBG::dump($response);
            $error = (isset($systemError)?$systemError:$resultNode->errtext);
            throw new ApiRequestException("Error in creating Customer: {$error}");
        }
        return $resultNode;	
    }
    
    /**
     * Create a Customer
     * @param \Cx\Core_Modules\MultiSite\Model\Entity\Customer $customer
     * @param \Cx\Core\Model\Model\Entity\SubscriptionInfo $subscription
     * @return $response array
     */
    public function createSubscription(\Cx\Core_Modules\MultiSite\Model\Entity\Customer $customer,\Cx\Core_Modules\MultiSite\Model\Entity\SubscriptionInfo $subscription){
        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);       
        $webspace = $xmldoc->createElement('webspace');
        $packet->appendChild($webspace);
        $addTag = $xmldoc->createElement('add');
        $webspace->appendChild($addTag);
        $genSetup = $xmldoc->createElement('gen_setup');
        $addTag->appendChild($genSetup);
        /*--gen_setup data Start--*/
        $subscriptionName = $xmldoc->createElement('name',$subscription->getSubscriptionName());
        $genSetup->appendChild($subscriptionName);
        $ownerId = $xmldoc->createElement('owner-id',$customer->getCustomerId());
        $genSetup->appendChild($ownerId);
        
        $ipAddress = $xmldoc->createElement('ip_address',$this->ip);
        $genSetup->appendChild($ipAddress);
        $status = $xmldoc->createElement('status', $subscription->getSubscriptionStatus());      
        $genSetup->appendChild($status);
        /*--gen_setup data End--*/
        $response = $this->executeCurl($xmldoc);
        $resultNode = $response->webspace->{'add'}->result;
        $errcode = $resultNode->errcode;
        $systemError = $response->system->errtext;
        if ('error' == (string)$resultNode->status || $systemError) {
            \DBG::dump($xmldoc->saveXML());
            \DBG::dump($response);
            $error = (isset($systemError)?$systemError:$resultNode->errtext);
            throw new ApiRequestException("Error in creating Subscription: {$error}");
        }
        return $resultNode;	
    }
    
    /**
     * Removes a Subscription
     * @param \Cx\Core\Model\Model\Entity\Subscription
     * @throws MultiSiteDbException On error
     */
    function removeSubscription(\Cx\Core_Modules\MultiSite\Model\Entity\SubscriptionInfo $subscription){
        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);
        $domain = $xmldoc->createElement('domain');
        $packet->appendChild($domain);
        $subscriptionId = $subscription->getSubscriptionId();
        $delTag = $xmldoc->createElement('del',$subscriptionId);
        $domain->appendChild($delTag);
        $response = $this->executeCurl($xmldoc);
        $resultNode = $response->domain->{'del'}->result;
        $errcode = $resultNode->errcode;
        $systemError = $response->system->errtext;
        if ('error' == (string)$resultNode->status || $systemError) {
            \DBG::dump($xmldoc->saveXML());
            \DBG::dump($response);
            $error = (isset($systemError)?$systemError:$resultNode->errtext);
            throw new ApiRequestException("Error in deleting Subscription: {$error}");
        }
        return $response;    
    }
    
    /**
     * Add DNS records
     * @param string    $type   DNS-Record type
     * @param string    $host   DNS-Record host
     * @param string    $value  DNS-Record value
     * @param string    $zone   Name of DNS-Zone
     * @param integer   $zoneId Id of plesk subscription to add the record to
     */
    public function addDnsRecord($type = 'A', $host, $value, $zone, $zoneId){
        \DBG::msg("MultiSite (PleskController): add DNS-record: $type / $host / $value / $zone / $zoneId");
        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);
        $dns = $xmldoc->createElement('dns');
        $packet->appendChild($dns);
        $addRec = $xmldoc->createElement('add_rec');
        $dns->appendChild($addRec);
        
        $siteIdTag = $xmldoc->createElement('site-id', $zoneId);
        $addRec->appendChild($siteIdTag);
        
        $recordType = $xmldoc->createElement('type',$type);
        $addRec->appendChild($recordType);

        // In case the record is a subdomain of the DNS-zone, then
        // we'll have to strip the DNS-zone part from the record.
        // I.e.:
        //      DNS-zone ($zone):   example.com
        //      DNS-record ($host): foo.example.com
        //      strip $host to:     foo
        if (strrpos($host, $zone) === strlen(substr($host, 0, -strlen($zone)))) {
            $host = rtrim(substr($host, 0, -strlen($zone)), '.');
        }

        $host = rtrim($host, '.');
        $host = $xmldoc->createElement('host', $host);
        $addRec->appendChild($host);

        $value = $xmldoc->createElement('value', $value);
        $addRec->appendChild($value);
        
        $response = $this->executeCurl($xmldoc);
        $resultNode = $response->dns->{'add_rec'}->result;
        $errcode = $resultNode->errcode;
        $systemError = $response->system->errtext;
        if ('error' == (string)$resultNode->status || $systemError) {
            \DBG::dump($xmldoc->saveXML());
            \DBG::dump($response);
            $error = (isset($systemError)?$systemError:$resultNode->errtext);
            throw new ApiRequestException("Error in adding DNS Record: {$error}");
        }
        return intval($resultNode->id);
    }

    /**
     * Remove DNS records
     * @param string    $type       DNS-Record-type
     * @param string    $host       DNS-Record-host
     * @param integer   $recordId   DNS-Record-Id of the plesk subscription
     */
    public function removeDnsRecord($type, $host, $recordId) {
        \DBG::msg("MultiSite (PleskController): remove DNS-record: $type / $host / $recordId");
        if (empty($recordId)) {
            return false;
        }
        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);
        $dns = $xmldoc->createElement('dns');
        $packet->appendChild($dns);       
        $delRec = $xmldoc->createElement('del_rec');
        $dns->appendChild($delRec);

        $filter = $xmldoc->createElement('filter');
        $delRec->appendChild($filter);       

        $id = $xmldoc->createElement('id', $recordId);
        $filter->appendChild($id);

        $response = $this->executeCurl($xmldoc);
        $resultNode = $response->dns->{'del_rec'}->result;
        $errcode = $resultNode->errcode;
        $systemError = $response->system->errtext;
        if ('error' == (string)$resultNode->status || $systemError) {
            \DBG::dump($xmldoc->saveXML());
            \DBG::dump($response);
            $error = (isset($systemError)?$systemError:$resultNode->errtext);
            throw new ApiRequestException("Error in deleting DNS Record: {$error}");
        }
        return $response; 
    }

    public function updateDnsRecord($type, $host, $value, $zone, $zoneId, $recordId){
        \DBG::msg("MultiSite (PleskController): update DNS-record: $type / $host / $value / $zone / $zoneId / $recordId");

        if (!$recordId) {
            \DBG::msg("MultiSite (PleskController): None existant DNS-record -> going to add DNS-record");
            return $this->addDnsRecord($type, $host, $value, $zone, $zoneId);
        }

        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);
        $dns = $xmldoc->createElement('dns');
        $packet->appendChild($dns);

        $getRec = $xmldoc->createElement('get_rec');
        $dns->appendChild($getRec);

        $filter = $xmldoc->createElement('filter');
        $getRec->appendChild($filter);       
        
        //$siteIdTag = $xmldoc->createElement('site-id', $zoneId);
        //$filter->appendChild($siteIdTag);

        $id = $xmldoc->createElement('id', $recordId);
        $filter->appendChild($id);
        
        $response = $this->executeCurl($xmldoc);
        $resultNode = $response->dns->{'get_rec'}->result;
        $errcode = $resultNode->errcode;
        $systemError = $response->system->errtext;
        if ('error' == (string)$resultNode->status || $systemError) {
            \DBG::dump($xmldoc->saveXML());
            \DBG::dump($response);
            $error = (isset($systemError)?$systemError:$resultNode->errtext);
            throw new ApiRequestException("Error in fetching DNS Record: {$error}");
        }

        $recordType = $resultNode->data->type;
        $recordHost = substr($resultNode->data->host, 0, -1);
        $recordValue = $resultNode->data->value;
        if ($recordType == 'CNAME') {
            $recordValue = substr($recordValue, 0, -1);
        }
        if (   $recordType != $type
            || $recordHost != $host
            || $recordValue != $value
        ) {
            \DBG::msg("MultiSite (PleskController): DNS-record has changed -> going to update DNS-record");
            $this->removeDnsRecord($type, $host, $recordId);
            return $this->addDnsRecord($type, $host, $value, $zone, $zoneId);
        }

        // record is up to date -> return existing record-ID
        return $recordId;
    }
    
    /**
     * Get dom document object with request packet
     * return object (DomDocument)
     */
    protected function getXmlDocument(){
        $xmldoc = new \DomDocument('1.0', 'UTF-8');
        $xmldoc->formatOutput = true;
        return $xmldoc;        
    }
    
    protected function getRpcPacket($xmldoc){
        $packet = $xmldoc->createElement('packet');
        $packet->setAttribute('version', self::API_RPC_VERSION);
        $xmldoc->appendChild($packet);
        return $packet;
    }
    
    /**
     * Create new FTP Account
     * 
     * @param string  $userName       FTP user name
     * @param string  $password       FTP password
     * @param string  $homePath       FTP accessible path
     * @param integer $subscriptionId webspace id
     * 
     * @return object
     * @throws ApiRequestException
     */
    public function addFtpAccount($userName, $password, $homePath, $subscriptionId) {
        \DBG::msg("MultiSite (PleskController): Creating Ftp Account.");
        if (empty($userName) || empty($password) || empty($homePath) || empty($subscriptionId)) {
            return;
        }
        
        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);       

        $ftpUser = $xmldoc->createElement('ftp-user');
        $packet->appendChild($ftpUser);

        $addTag = $xmldoc->createElement('add');
        $ftpUser->appendChild($addTag);

        $ftpLogin = $xmldoc->createElement('name', $userName);
        $addTag->appendChild($ftpLogin);

        $ftpPasswordValue = $xmldoc->createTextNode($password);
        $ftpPassword = $xmldoc->createElement('password');
        $ftpPassword->appendChild($ftpPasswordValue);
        $addTag->appendChild($ftpPassword);

        $home = $xmldoc->createElement('home', $homePath);
        $addTag->appendChild($home);

        $permissions = $xmldoc->createElement('permissions');
        $addTag->appendChild($permissions);

        $permissionReadAccess = $xmldoc->createElement('read', true);
        $permissions->appendChild($permissionReadAccess);

        $permissionWriteAccess = $xmldoc->createElement('write', true);
        $permissions->appendChild($permissionWriteAccess);

        $webspaceId = $xmldoc->createElement('webspace-id', $subscriptionId);
        $addTag->appendChild($webspaceId);
        
        $response = $this->executeCurl($xmldoc);
        $resultNode = $response->{'ftp-user'}->{'add'}->result;
        $systemError = $response->system->errtext;
        if ('error' == (string)$resultNode->status || $systemError) {
            \DBG::dump($xmldoc->saveXML());
            \DBG::dump($response);
            $error = (isset($systemError) ? $systemError : $resultNode->errtext);
            throw new ApiRequestException("Error in creating Ftp Account: {$error}");
        }
        return $resultNode->id;	
    }
    
    /**
     * Delete the FTP Account
     * 
     * @param string $userName FTP user name
     * 
     * @return object
     * @throws ApiRequestException
     */
    public function removeFtpAccount($userName) {
        \DBG::msg("MultiSite (PleskController): Deleting Ftp Account.");
        if (empty($userName)) {
            return;
        }
        
        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);       
        $ftpUser = $xmldoc->createElement('ftp-user');
        $packet->appendChild($ftpUser);
        $delTag = $xmldoc->createElement('del');
        $ftpUser->appendChild($delTag);
        $filterTag = $xmldoc->createElement('filter');
        $delTag->appendChild($filterTag);
        $ftpLogin = $xmldoc->createElement('name', $userName);
        $filterTag->appendChild($ftpLogin);
        
        $response = $this->executeCurl($xmldoc);
        $resultNode = $response->{'ftp-user'}->{'del'}->result;
        $systemError = $response->system->errtext;
        if ('error' == (string)$resultNode->status || $systemError) {
            \DBG::dump($xmldoc->saveXML());
            \DBG::dump($response);
            $error = (isset($systemError) ? $systemError : $resultNode->errtext);
            throw new ApiRequestException("Error in deleting Ftp Account: {$error}");
        }
        return $response;    
    }
    
    /**
     * Change the FTP Account password
     * 
     * @param string $userName FTP user name
     * @param string $password FTP password
     * 
     * @return object
     * @throws ApiRequestException
     */
    public function changeFtpAccountPassword($userName, $password) {
        \DBG::msg("MultiSite (PleskController): Changing Ftp Account Password.");
        if (empty($userName) || empty($password)) {
            return;
        }
        
        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);       

        $ftpUser = $xmldoc->createElement('ftp-user');
        $packet->appendChild($ftpUser);

        $setTag = $xmldoc->createElement('set');
        $ftpUser->appendChild($setTag);

        $filterTag = $xmldoc->createElement('filter');
        $setTag->appendChild($filterTag);

        $ftpLogin = $xmldoc->createElement('name', $userName);
        $filterTag->appendChild($ftpLogin);

        $valuesTag = $xmldoc->createElement('values');
        $setTag->appendChild($valuesTag);

        $ftpPasswordValue = $xmldoc->createTextNode($password);
        $ftpPassword = $xmldoc->createElement('password');
        $ftpPassword->appendChild($ftpPasswordValue);
        $valuesTag->appendChild($ftpPassword);
        
        $response = $this->executeCurl($xmldoc);
        $resultNode = $response->{'ftp-user'}->{'set'}->result;
        $systemError = $response->system->errtext;
        if ('error' == (string)$resultNode->status || $systemError) {
            \DBG::dump($xmldoc->saveXML());
            \DBG::dump($response);
            $error = (isset($systemError) ? $systemError : $resultNode->errtext);
            throw new ApiRequestException("Error in changing the Ftp Account password: {$error}");
        }
        return $response;    
    }
    
    /**
     * Get All the DNS records
     * 
     * @return array
     * @throws ApiRequestException
     */
    public function getDnsRecords() {
        \DBG::msg("MultiSite (PleskController): get DNS-record: $this->webspaceId");
        if (empty($this->webspaceId)) {
            return false;
        }
        
        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);
        $dns = $xmldoc->createElement('dns');
        $packet->appendChild($dns);       
        $getRec = $xmldoc->createElement('get_rec');
        $dns->appendChild($getRec);

        $filter = $xmldoc->createElement('filter');
        $getRec->appendChild($filter);       

        $id = $xmldoc->createElement('site-id', $this->webspaceId);
        $filter->appendChild($id);

        \DBG::dump($xmldoc->saveXML());
        $response     = $this->executeCurl($xmldoc);
        $resultNode   = $response->dns->{'get_rec'}->result;
        $responseJson = json_encode($response->dns->{'get_rec'});
        $responseArr  = json_decode($responseJson,true); 
        $systemError  = $response->system->errtext;
        if ('error' == (string)$resultNode->status || $systemError) {
            $error = (isset($systemError)?$systemError:$resultNode->errtext);
            throw new ApiRequestException("Error in getting DNS records : {$error} ");
        }      
        
        $resultArray = array();
        if (!empty($responseArr)) {
            foreach($responseArr['result'] as $result) {
                $resultArray[$result['id']] = $result['data']['host'];
            }
            return $resultArray;
        }
    }
    
    /**
     * Get All FtpAccounts
     * 
     * @return array
     * @throws ApiRequestException
     */
    public function getFtpAccounts() {
        
        \DBG::msg("MultiSite (PleskController): get all Ftp Accounts: $this->webspaceId");
        if (empty($this->webspaceId)) {
            return false;
        }
        
        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);       

        $ftpUser = $xmldoc->createElement('ftp-user');
        $packet->appendChild($ftpUser);

        $getTag = $xmldoc->createElement('get');
        $ftpUser->appendChild($getTag);

        $filterTag = $xmldoc->createElement('filter');
        $getTag->appendChild($filterTag);
        
        $webspaceTag = $xmldoc->createElement('webspace-id', $this->webspaceId);
        $filterTag->appendChild($webspaceTag);
        
        $response       = $this->executeCurl($xmldoc);
        $resultNode     = $response->{'ftp-user'}->{'get'}->result;
        $responseJson   = json_encode($response->{'ftp-user'}->{'get'});
        $respArr        = json_decode($responseJson,true); 
        $systemError    = $response->system->errtext;
        if ('error' == (string)$resultNode->status || $systemError) {
            \DBG::dump($xmldoc->saveXML());
            \DBG::dump($response);
            $error = (isset($systemError)?$systemError:$resultNode->errtext);
            throw new ApiRequestException("Error in getting Ftp Accounts : {$error} ");
        }      

        if (!empty($respArr)) {
            $resultArr   = array();
            $responseArr = (count($respArr['result']) == count($respArr['result'], COUNT_RECURSIVE)) ? $respArr : $respArr['result'];
            foreach ($responseArr as $result) {
                $resultArr[$result['id']] = $result['name'];
            }
        }
        return $resultArr;
        
    }
    
    /**
     * Create new domain alias
     * 
     * @param string $aliasName alias name
     * 
     * @return boolean true on success false otherwise
     */
    public function createDomainAlias($aliasName)
    {
        \DBG::msg("MultiSite (PleskController): create domain alias: $this->webspaceId");
        if (empty($this->webspaceId)) {
            return false;
        }
        
        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);
        
        $siteAlias = $xmldoc->createElement('site-alias');
        $packet->appendChild($siteAlias);
        
        $createTag = $xmldoc->createElement('create');
        $siteAlias->appendChild($createTag);
        
        $siteIdTag = $xmldoc->createElement('site-id', $this->webspaceId);
        $createTag->appendChild($siteIdTag);
        
        $nameTag = $xmldoc->createElement('name', $aliasName);
        $createTag->appendChild($nameTag);
        
        $response       = $this->executeCurl($xmldoc);
        $resultNode     = $response->{'site-alias'}->{'create'}->result;
        $systemError = $response->system->errtext;
        if ('error' == (string)$resultNode->status || $systemError){
            \DBG::dump($xmldoc->saveXML());
            \DBG::dump($response);
            $error = (isset($systemError) ? $systemError : $resultNode->errtext);
            throw new ApiRequestException("Error in creating Domain alias: {$error}");
        }
        
        return true;
    }
    
    /**
     * Rename the domain alias
     * 
     * @param string $oldAliasName old alias name
     * @param string $newAliasName new alias name
     * 
     * @return boolean true on success false otherwise
     */
    public function renameDomainAlias($oldAliasName, $newAliasName)
    {
        \DBG::msg("MultiSite (PleskController): rename domain alias");
        if (empty($oldAliasName) || empty($newAliasName)) {
            return false;
        }
        
        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);
        
        $siteAlias = $xmldoc->createElement('site-alias');
        $packet->appendChild($siteAlias);
        
        $renameTag = $xmldoc->createElement('rename');
        $siteAlias->appendChild($renameTag);
        
        $nameTag = $xmldoc->createElement('name', $oldAliasName);
        $renameTag->appendChild($nameTag);
        
        $newNameTag = $xmldoc->createElement('new_name', $newAliasName);
        $renameTag->appendChild($newNameTag);
        
        $response       = $this->executeCurl($xmldoc);
        $resultNode     = $response->{'site-alias'}->{'rename'}->result;
        $systemError = $response->system->errtext;
        if ('error' == (string)$resultNode->status || $systemError){
            \DBG::dump($xmldoc->saveXML());
            \DBG::dump($response);
            $error = (isset($systemError) ? $systemError : $resultNode->errtext);
            throw new ApiRequestException("Error in renaming Domain alias: {$error}");
        }
        
        return true;
    }
    
    /**
     * Remove the domain alias by name
     * 
     * @param string $aliasName alias name to delete
     * 
     * @return boolean true on success false otherwise
     */
    public function deleteDomainAlias($aliasName)
    {
        \DBG::msg("MultiSite (PleskController): delete domain alias");
        if (empty($aliasName)) {
            return false;
        }
        
        $xmldoc = $this->getXmlDocument();
        $packet = $this->getRpcPacket($xmldoc);
        
        $siteAlias = $xmldoc->createElement('site-alias');
        $packet->appendChild($siteAlias);
        
        $deleteTag = $xmldoc->createElement('delete');
        $siteAlias->appendChild($deleteTag);
        
        $filterTag = $xmldoc->createElement('filter');
        $deleteTag->appendChild($filterTag);
        
        $nameTag = $xmldoc->createElement('name', $aliasName);
        $filterTag->appendChild($nameTag);
        
        $response       = $this->executeCurl($xmldoc);
        $resultNode     = $response->{'site-alias'}->{'delete'}->result;
        $systemError = $response->system->errtext;
        if ('error' == (string)$resultNode->status || $systemError){
            \DBG::dump($xmldoc->saveXML());
            \DBG::dump($response);
            $error = (isset($systemError) ? $systemError : $resultNode->errtext);
            throw new ApiRequestException("Error in deleting Domain alias: {$error}");
        }
        
        return true;
    }
    
    /**
     * Get formatted array from the given response array
     * 
     * @param  array $respArr response array. 
     * @return array
     */
    public function getFormattedResponse($respArr) {
        if (count($respArr['result']) == count($respArr['result'], COUNT_RECURSIVE)) {
            return $respArr;
        } else {
            return $respArr['result'];
        }
    }

}