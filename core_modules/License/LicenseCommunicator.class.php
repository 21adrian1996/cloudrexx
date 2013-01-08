<?php
/*
 * This file contains the LicenseCommunicator, used to
 * update a license
 */
namespace Cx\Core_Modules\License;

/**
 * Communicates with "the internet" to update a license
 * @author Michael Ritter <michael.ritter@comvation.com>
 */
class LicenseCommunicator {
    private static $instance = null;
    private $requestInterval = 1;
    private $lastUpdate;
    private static $javascriptRegistered = false;
    
    public function __construct(&$_CONFIG) {
        if (self::$instance) {
            throw new \BadMethodCallException('Cannot construct a second instance, use ::getInstance()');
        }
        $this->requestInterval = $_CONFIG['licenseUpdateInterval'];
        $this->lastUpdate = $_CONFIG['licenseSuccessfulUpdate'];
        $this->installationId = $_CONFIG['installationId'];
        $this->licenseKey = $_CONFIG['licenseKey'];
        $this->licenseState = $_CONFIG['licenseState'];
        $this->coreCmsEdition = $_CONFIG['coreCmsEdition'];
        $this->coreCmsVersion = $_CONFIG['coreCmsVersion'];
        $this->coreCmsStatus = $_CONFIG['coreCmsStatus'];
        $this->domainUrl = $_CONFIG['domainUrl'];
        
        self::$instance = $this;
    }
    
    /**
     * Singleton accessor
     * @return \Cx\Core_Modules\License\LicenseCommunicator 
     */
    public static function getInstance(&$_CONFIG) {
        if (!self::$instance) {
            new self($_CONFIG);
        }
        return self::$instance;
    }
    
    /**
     * Tells wheter its time to update or not
     * @return boolean True if license is outdated, false otherwise
     */
    public function isTimeToUpdate() {
        if ($this->licenseState == License::LICENSE_ERROR) {
            return true;
        }
        $offset = $this->requestInterval *60*60;
        // if offset date lies in future, we do not update yet
        return ($this->lastUpdate + $offset <= time());
    }
    
    /**
     * Updates the license
     * @param \Cx\Core_Modules\License\License $license The license to update
     * @param array $_CONFIG The configuration array
     * @param boolean $forceUpdate (optional) If set to true, update is performed even if time to update is not reached yet
     * @param boolean $forceTemplate (optional) If set to true, the server is requested to send the template
     * @param array $_CORELANG (optional) Core language array
     * @param string $response (optional) Server response as JSON. If this is set, no HTTP request is perfomed
     * @return null 
     */
    public function update(&$license, $_CONFIG, $forceUpdate = false, $forceTemplate = false, $_CORELANG = array(), $response = '') {
        if (!$forceUpdate && !$this->isTimeToUpdate($_CONFIG) && empty($response)) {
            return;
        }
        if ($response) {
            $response = json_decode($response);
        } else {
            $v = preg_split('#\.#', $_CONFIG['coreCmsVersion']);
            $e = $_CONFIG['coreCmsEdition'];

            $version = current($v);
            unset($v[key($v)]);
            foreach ($v as $part) {
                $version *= 100;
                $version += $part;
            }
            
            $srvUri = 'updatesrv1.contrexx.com';
            $srvPath = '/';

            $data = array(
                'installationId' => $license->getInstallationId(),
                'licenseKey' => $license->getLicenseKey(),
                'edition' => $license->getEditionName(),
                'version' => $this->coreCmsVersion,
                'versionstate' => $this->coreCmsStatus,
                'domainName' => $this->domainUrl,
                'sendTemplate' => $forceTemplate,
            );
            $a = $_SERVER['REMOTE_ADDR'];

            $request = new \HTTP_Request2('http://' . $srvUri . $srvPath . '?v=' . $version, \HTTP_Request2::METHOD_POST);
            $request->setHeader('X-Edition', $e);
            $request->setHeader('X-Remote-Addr', $a);
            $jd = new \Cx\Core\Json\JsonData();
            $request->addPostParameter('data', $jd->json($data));
            try {
                $objResponse = $request->send();
                if ($objResponse->getStatus() !== 200) {
                    $license->setState(License::LICENSE_ERROR);
                    $license->setGrayzoneMessages(array(\FWLanguage::getLanguageCodeById(LANG_ID) => new Message(\FWLanguage::getLanguageCodeById(LANG_ID), $_CORELANG['TXT_LICENSE_COMMUNICATION_ERROR'])));
                    $license->check();
                    return;
                } else {
                    \DBG::dump($objResponse->getBody());
                    $response = json_decode($objResponse->getBody());
                }
            } catch (\HTTP_Request2_Exception $objException) {
                $license->setState(License::LICENSE_ERROR);
                $license->setGrayzoneMessages(array(\FWLanguage::getLanguageCodeById(LANG_ID) => new Message(\FWLanguage::getLanguageCodeById(LANG_ID), $_CORELANG['TXT_LICENSE_COMMUNICATION_ERROR'])));
                $license->check();
                throw $objException;
            }
        }
        
        $upgradeUrl = $response->license->upgradeUrl;
        if ($response->license->partner->upgradeUrl) {
            $upgradeUrl = $response->license->partner->upgradeUrl;
        }
        
        // create new license
        $installationId = $license->getInstallationId();
        $licenseKey = $license->getLicenseKey();
        if ($response->license->installationId != null) {
            $installationId = $response->license->installationId;
        }
        if ($response->license->key != null) {
            $licenseKey = $response->license->key;
        }
        if (!empty($response->common->template)) {
            if (\FWUser::getFWUserObject()->objUser->getAdminStatus()) {
                try {
                    $file = new \Cx\Lib\FileSystem\File(ASCMS_TEMP_PATH.'/licenseManager.html');
                    $file->write($response->common->template);
                } catch (\Cx\Lib\FileSystem\FileSystemException $e) {}
            }
        }
        $this->requestInterval = $response->license->settings->requestInterval;
        if (!is_int($this->requestInterval) || $this->requestInterval < 0 || $this->requestInterval > (365*24)) {
            $this->requestInterval = 1;
        }
        $dashboardMessages = array();
        foreach ($response->license->messages->dashboard as $lang=>$message) {
            $dashboardMessages[$lang] = new \Cx\Core_Modules\License\Message(
                $lang,
                $message->text,
                $message->type,
                $message->link,
                $message->linkTarget,
                $message->showInDashboard
            );
        }
        $licenseManagementMessages = array();
        foreach ($response->license->messages->licenseManagement as $lang=>$message) {
            $licenseManagementMessages[$lang] = new \Cx\Core_Modules\License\Message(
                $lang,
                $message->text,
                $message->type,
                $message->link,
                $message->linkTarget,
                $message->showInDashboard
            );
        }
        $gzMessages = array();
        foreach ($response->license->messages->grayZone as $lang=>$message) {
            $gzMessages[$lang] = new \Cx\Core_Modules\License\Message(
                $lang,
                $message->text,
                $message->type,
                $message->link,
                $message->linkTarget,
                $message->showInDashboard
            );
        }
        $partner = new \Cx\Core_Modules\License\Person(
            $response->license->partner->companyName,
            $response->license->partner->title,
            $response->license->partner->firstname,
            $response->license->partner->lastname,
            $response->license->partner->address,
            $response->license->partner->zip,
            $response->license->partner->city,
            $response->license->partner->country,
            $response->license->partner->phone,
            $response->license->partner->url,
            $response->license->partner->mail
        );
        $customer = new \Cx\Core_Modules\License\Person(
            $response->license->customer->companyName,
            $response->license->customer->title,
            $response->license->customer->firstname,
            $response->license->customer->lastname,
            $response->license->customer->address,
            $response->license->customer->zip,
            $response->license->customer->city,
            $response->license->customer->country,
            $response->license->customer->phone,
            $response->license->customer->url,
            $response->license->customer->mail
        );
        $version = new \Cx\Core_Modules\License\Version(
            $response->versions->currentStable->number,
            $response->versions->currentStable->name,
            $response->versions->currentStable->codeName,
            $response->versions->currentStable->state,
            $response->versions->currentStable->releaseDate
        );
        $license = new \Cx\Core_Modules\License\License(
            $response->license->state,
            $response->license->edition,
            $response->license->availableComponents,
            $response->license->legalComponents,
            $response->license->validTo,
            $response->license->createdAt,
            $response->license->registeredDomains,
            $installationId,
            $licenseKey,
            $licenseManagementMessages,
            $license->getVersion(),
            $partner,
            $customer,
            $response->license->settings->grayZoneTime,
            $gzMessages,
            $response->license->settings->frontendLockTime,
            $this->requestInterval,
            0,
            time(),
            $upgradeUrl,
            $response->license->isUpgradable == 'true',
            $dashboardMessages
        );
        
        $license->check();

        return;
    }
    
    /**
     * Registers the javascript code to update a license
     * @param array $_CORELANG Core language array
     * @param array $_CONFIG The configuration array
     * @param boolean $autoexec (optional) Wheter to perform update check automaticly or on form submit
     */
    public function addJsUpdateCode(&$_CORELANG, $license, $intern = false, $autoexec = true) {
        $v = preg_split('#\.#', $this->coreCmsVersion);
        $version = current($v);
        unset($v[key($v)]);
        foreach ($v as $part) {
            $version *= 100;
            $version += $part;
        }
        
        $userAgentRequestArguments = array(
            'data=' . urlencode(json_encode(array(
                'installationId' => $license->getInstallationId(),
                'licenseKey' => $license->getLicenseKey(),
                'edition' => $license->getEditionName(),
                'version' => $this->coreCmsVersion,
                'versionstate' => $this->coreCmsStatus,
                'domainName' => $this->domainUrl,
                'remoteAddr' => $_SERVER['REMOTE_ADDR'],
                'sendTemplate' => false,
            ))),
            'v=' . $version,
            'userAgentRequest=true',
        );
        
        if (!$autoexec || $this->isTimeToUpdate()) {
            if (self::$javascriptRegistered) {
                return;
            }
            self::$javascriptRegistered = true;
            
            \JS::activate('jquery');
            $jsCode = '
                jQuery(document).ready(function() {
                    var licenseMessage      = jQuery("#license_message");
                    var cloneLicenseMessage = jQuery("#license_message").clone();
                    
                    var revertMessage = function(setClass, setHref, setTarget, setText) {
                        setTimeout(function() {
                            newLicenseMessage = cloneLicenseMessage.clone();
                            if (setClass) {
                                newLicenseMessage.attr("class", "upgrade " + setClass);
                            }
                            if (setHref) {
                                licenseMessage.children("a:first").attr("href", setHref);
                            }
                            if (setTarget) {
                                licenseMessage.children("a:first").attr("target", setTarget);
                            }
                            if (setText) {
                                licenseMessage.children("a:first").html(setText);
                            }
                            licenseMessage.replaceWith(newLicenseMessage);
                            licenseMessage = newLicenseMessage;
                        }, 1000);
                    }
                    
                    var versionCheckResponseHandler = function(data, allowUserAgent) {';
            if (\FWUser::getFWUserObject()->objUser->getAdminStatus()) {
                $jsCode .= '
                        if (data == "false" && allowUserAgent) {
                            jQuery.getScript("http://updatesrv1.contrexx.com/?' . implode('&', $userAgentRequestArguments) . '", function() {
                                jQuery.post(
                                    "../core_modules/License/versioncheck.php?force=true",
                                    {"response": JSON.stringify(licenseUpdateUserAgentRequestResponse)}
                                ).success(function(data) {
                                    versionCheckResponseHandler(data, false)
                                }).error(function(data) {
                                    revertMessage();
                                });
                            });
                        }';
            }
            $jsCode .= '
                        var data = jQuery.parseJSON(data);
                        if (data == null) {
                            revertMessage();
                            return;
                        }
                        
                        revertMessage(data[\'class\'], data.link, data.target, data.text);
                        document.location.reload(true);
                    }
                    
                    var performRequest = function() {
                        licenseMessage.attr("class", "infobox");
                        licenseMessage.text("' . $_CORELANG['TXT_LICENSE_UPDATING'] . '");

                        jQuery.get(
                            "../core_modules/License/versioncheck.php?force=true"
                        ).success(function(data) {
                            versionCheckResponseHandler(data, true);
                        }).error(function(data) {
                            revertMessage();
                        });
                        return false;
                    }' . ($autoexec ? '()' : '') . ';
                    
                    ' . ($intern ? 'jQuery("input[name=update]").click(performRequest);' : '') . '
                });
            ';
            \JS::registerCode($jsCode);
        }
    }
}
