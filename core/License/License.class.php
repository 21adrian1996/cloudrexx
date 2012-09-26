<?php
/* 
 * To change this template, choose Tools | Templates
 * and open the template in the editor.
 */
namespace Cx\Core\License;

/**
 * Description of License
 *
 * @author ritt0r
 */
class License {
    const LICENSE_OK = 'OK';
    const LICENSE_NOK = 'NOK';
    const LICENSE_DEMO = 'DEMO';
    const LICENSE_ERROR = 'ERROR';
    private $state;
    private $frontendLocked = false;
    private $editionName;
    private $legalComponents;
    private $legalFrontendComponents;
    private $validTo;
    private $instId;
    private $licenseKey;
    private $messages;
    private $version;
    private $partner;
    private $customer;
    private $grayzoneTime;
    private $frontendLockTime;
    private $requestInterval;
    private $firstFailedUpdate;
    private $lastSuccessfulUpdate;
    
    public function __construct(
            $state = self::LICENSE_DEMO,
            $editionName = '',
            $legalComponents = array('license'),
            $validTo = '',
            $instId = '',
            $licenseKey = '',
            $messages = array(),
            $version = '',
            $partner = '',
            $customer = '',
            $grayzoneTime = 14,
            $frontendLockTime = 10,
            $requestInterval = 1,
            $firstFailedUpdate = 0,
            $lastSuccessfulUpdate = 0
    ) {
        $this->state = $state;
        $this->editionName = $editionName;
        $this->legalComponents = $legalComponents;
        $this->validTo = $validTo;
        $this->instId = $instId;
        $this->licenseKey = $licenseKey;
        $this->messages = $messages;
        $this->version = $version;
        $this->partner = $partner;
        $this->customer = $customer;
        $this->grayzoneTime = $grayzoneTime;
        $this->frontendLockTime = $frontendLockTime;
        $this->requestInterval = $requestInterval;
        $this->setFirstFailedUpdateTime($firstFailedUpdate);
        $this->setLastSuccessfulUpdateTime($lastSuccessfulUpdate);
    }
    
    public function getState() {
        return $this->state;
    }
    
    public function setState($state) {
        $this->state = $state;
        if ($this->state == self::LICENSE_ERROR) {
            $this->setFirstFailedUpdateTime(time());
        }
    }
    
    public function isFrontendLocked() {
        return $this->frontendLocked;
    }
    
    public function getEditionName() {
        return $this->editionName;
    }
    
    public function getLegalComponentsList() {
        return $this->legalComponents;
    }
    
    public function isInLegalComponents($componentName) {
        return in_array($componentName, $this->legalComponents);
    }
    
    public function getLegalFrontendComponentsList() {
        if (!$this->legalFrontendComponents) {
            return $this->getLegalComponentsList();
        }
        return $this->legalFrontendComponents;
    }
    
    public function isInLegalFrontendComponents($componentName) {
        return in_array($componentName, $this->getLegalFrontendComponentsList());
    }
    
    public function getValidToDate() {
        return $this->validTo;
    }
    
    public function setValidToDate($timestamp) {
        $this->validTo = $timestamp;
    }
    
    public function getInstallationId() {
        return $this->instId;
    }
    
    public function getLicenseKey() {
        return $this->licenseKey;
    }
    
    public function setLicenseKey($key) {
        $this->licenseKey = $key;
    }
    
    public function getMessages() {
        return $this->messages;
    }
    
    /**
     *
     * @return Message
     */
    public function getMessage($langCode) {
        if (!isset($this->messages[$langCode])) {
            return null;
        }
        return $this->messages[$langCode];
    }
    
    public function getVersion() {
        return $this->version;
    }
    
    public function getPartner() {
        return $this->partner;
    }
    
    public function getCustomer() {
        return $this->customer;
    }
    
    public function getGrayzoneTime() {
        return $this->grayzoneTime;
    }
    
    public function getFrontendLockTime() {
        return $this->frontendLockTime;
    }
    
    public function getFirstFailedUpdateTime() {
        return $this->firstFailedUpdate;
    }
    
    public function setFirstFailedUpdateTime($time) {
        if ($this->firstFailedUpdate == 0) {
            $this->firstFailedUpdate = $time;
        }
    }
    
    public function getLastSuccessfulUpdateTime() {
        return $this->lastSuccessfulUpdate;
    }
    
    public function setLastSuccessfulUpdateTime($time) {
        if ($time > $this->firstFailedUpdate) {
            $this->firstFailedUpdate = 0;
            $this->lastSuccessfulUpdate = $time;
        }
    }
    
    public function check() {
        $validTo = 0;
        switch ($this->state) {
            case self::LICENSE_DEMO:
            case self::LICENSE_OK:
                $validTo = $this->validTo;
                break;
            case self::LICENSE_ERROR:
                $this->setFirstFailedUpdateTime(time());
                $validTo = $this->getFirstFailedUpdateTime() + 60*60*24*$this->grayzoneTime;
                break;
        }
        if (empty($this->instId) || empty($this->licenseKey) || $validTo < time() || $this->state == self::LICENSE_NOK) {
            $this->state = self::LICENSE_NOK;
            $this->legalFrontendComponents = $this->legalComponents;
            $this->legalComponents = array('license');
            if ($validTo + 60*60*24*$this->frontendLockTime >= time()) {
                $this->frontendLocked = true;
                $this->legalFrontendComponents = array('license');
            }
        }
        $this->setValidToDate($validTo);
    }
    
    /**
     *
     * @global type $_POST
     * @param \settingsManager $settingsManager
     * @param \ADONewConnection $objDb 
     */
    public function save($settingsManager, $objDb) {
        // WARNING, this is the ugly way:
        global $_POST;
        $oldpost = $_POST;
        unset($_POST);
        
        $_POST['setvalue'][75] = $this->getInstallationId();                // installationId
        $_POST['setvalue'][76] = $this->getLicenseKey();                    // licenseKey
        $_POST['setvalue'][90] = $this->getState();                         // licenseState
        $_POST['setvalue'][91] = $this->getValidToDate();                   // licenseValidTo
        $_POST['setvalue'][92] = $this->getEditionName();                   // coreCmsEdition
        
        $_POST['setvalue'][93] = serialize($this->getMessages());           // messageText --> licenseMessage
        
        $_POST['setvalue'][97] = $this->getVersion()->getNumber();          // coreCmsVersion
        $_POST['setvalue'][98] = $this->getVersion()->getCodeName();        // coreCmsCodeName
        $_POST['setvalue'][99] = $this->getVersion()->getState();           // coreCmsStatus
        $_POST['setvalue'][100] = $this->getVersion()->getReleaseDate();    // coreCmsReleaseDate
        
        $_POST['setvalue'][101] = serialize($this->getPartner());           // licenseHolderCompany --> licensePartner
        $_POST['setvalue'][102] = serialize($this->getCustomer());          // licenseHolderTitle --> licenseCustomer
        
        $_POST['setvalue'][112] = $this->getVersion()->getName();           // coreCmsName
        
        $_POST['setvalue'][114] = $this->getGrayzoneTime();                 // licenseGrayzoneTime
        $_POST['setvalue'][115] = $this->getFrontendLockTime();             // licenseLockTime
        $_POST['setvalue'][116] = $this->requestInterval;                   // licenseUpdateInterval
        
        $_POST['setvalue'][117] = $this->getFirstFailedUpdateTime();        // licenseFailedUpdate
        $_POST['setvalue'][118] = $this->getLastSuccessfulUpdateTime();     // licenseSuccessfulUpdate
        
        $settingsManager->updateSettings();
        $settingsManager->writeSettingsFile();
        
        $query = '
            UPDATE
                '.DBPREFIX.'modules
            SET
                `is_active` = \'0\'
        ';
        $objDb->Execute($query);
        $query = '
            UPDATE
                '.DBPREFIX.'modules
            SET
                `is_active` = \'1\'
            WHERE
                `name` IN(\'' . implode('\', \'', $this->getLegalComponentsList()) . '\')
        ';
        $objDb->Execute($query);
        unset($_POST);
        $_POST = $oldpost;
    }
    
    /**
     * @param \SettingDb $settings Reference to the settings manager object
     * @return \Cx\Core\License\License
     */
    public static function getCached(&$_CONFIG, $objDb) {
        $state = isset($_CONFIG['licenseState']) ? $_CONFIG['licenseState'] : self::LICENSE_DEMO;
        $validTo = isset($_CONFIG['licenseValidTo']) ? $_CONFIG['licenseValidTo'] : null;
        $editionName = isset($_CONFIG['coreCmsEdition']) ? $_CONFIG['coreCmsEdition'] : null;
        $instId = isset($_CONFIG['installationId']) ? $_CONFIG['installationId'] : null;
        $licenseKey = isset($_CONFIG['licenseKey']) ? $_CONFIG['licenseKey'] : null;
        
        $messages = isset($_CONFIG['licenseMessage']) ? unserialize(htmlspecialchars_decode($_CONFIG['licenseMessage'])) : array();
        
        $partner = isset($_CONFIG['licensePartner']) ? unserialize(htmlspecialchars_decode($_CONFIG['licensePartner'])) : null;
        $customer = isset($_CONFIG['licenseCustomer']) ? unserialize(htmlspecialchars_decode($_CONFIG['licenseCustomer'])) : null;
        
        $versionNumber = isset($_CONFIG['coreCmsVersion']) ? $_CONFIG['coreCmsVersion'] : null;
        $versionName = isset($_CONFIG['coreCmsName']) ? $_CONFIG['coreCmsName'] : null;
        $versionCodeName = isset($_CONFIG['coreCmsCodeName']) ? $_CONFIG['coreCmsCodeName'] : null;
        $versionState = isset($_CONFIG['coreCmsStatus']) ? $_CONFIG['coreCmsStatus'] : null;
        $versionReleaseDate = isset($_CONFIG['coreCmsReleaseDate']) ? $_CONFIG['coreCmsReleaseDate'] : null;
        $version = new Version($versionNumber, $versionName, $versionCodeName, $versionState, $versionReleaseDate);
        
        $grayzoneTime = isset($_CONFIG['licenseGrayzoneTime']) ? $_CONFIG['licenseGrayzoneTime'] : null;
        $lockTime = isset($_CONFIG['licenseLockTime']) ? $_CONFIG['licenseLockTime'] : null;
        $updateInterval = isset($_CONFIG['licenseUpdateInterval']) ? $_CONFIG['licenseUpdateInterval'] : null;
        $failedUpdate = isset($_CONFIG['licenseFailedUpdate']) ? $_CONFIG['licenseFailedUpdate'] : null;
        $successfulUpdate = isset($_CONFIG['licenseSuccessfulUpdate']) ? $_CONFIG['licenseSuccessfulUpdate'] : null;
        
        $query = '
            SELECT
                `name`
            FROM
                '.DBPREFIX.'modules
            WHERE
                `is_active` = \'1\'
        ';
        $result = $objDb->execute($query);
        $activeComponents = array();
        if ($result) {
            while (!$result->EOF) {
                $activeComponents[] = $result->fields['name'];
                $result->MoveNext();
            }
        }
        return new static(
            $state,
            $editionName,
            $activeComponents,
            $validTo,
            $instId,
            $licenseKey,
            $messages,
            $version,
            $partner,
            $customer,
            $grayzoneTime,
            $lockTime,
            $updateInterval,
            $failedUpdate,
            $successfulUpdate
        );
    }
}
