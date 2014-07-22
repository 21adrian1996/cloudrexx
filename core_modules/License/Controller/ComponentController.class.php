<?php

/**
 * Main controller for License
 * 
 * @copyright   Comvation AG
 * @author      Project Team SS4U <info@comvation.com>
 * @package     contrexx
 * @subpackage  coremodule_license
 */

namespace Cx\Core_Modules\License\Controller;

/**
 * Main controller for License
 * 
 * @copyright   Comvation AG
 * @author      Project Team SS4U <info@comvation.com>
 * @package     contrexx
 * @subpackage  coremodule_license
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

        global $_CORELANG, $objTemplate, $objDatabase, $act;

        switch ($this->cx->getMode()) {
            case \Cx\Core\Core\Controller\Cx::MODE_BACKEND:
                $this->cx->getTemplate()->addBlockfile('CONTENT_OUTPUT', 'content_master', 'LegacyContentMaster.html');
                $objTemplate = $this->cx->getTemplate();

                \Permission::checkAccess(177, 'static');
                $objLicense = new \Cx\Core_Modules\License\LicenseManager($act, $objTemplate, $_CORELANG, \Env::get('config'), $objDatabase);
                $objLicense->getPage($_POST, $_CORELANG);
                break;

            default:
                break;
        }
    }

    /**
     * Do something before resolving is done
     * 
     * @param \Cx\Core\Routing\Url                      $request    The URL object for this request
     */
    public function preResolve(\Cx\Core\Routing\Url $request) {
// TODO: Deactivated license check for now. Implement new behavior.
        return true;

        global $objDatabase;

        $config = \Env::get('config');
        $license = \Cx\Core_Modules\License\License::getCached($config, $objDatabase);

        switch ($this->cx->getMode()) {
            case \Cx\Core\Core\Controller\Cx::MODE_FRONTEND:
                // make sure license data is up to date (this updates active available modules)
                // @todo move to core_module license

                $oldState = $license->getState();
                $license->check();
                if ($oldState != $license->getState()) {
                    $license->save(new \Cx\Core\Config\Controller\Config(), $objDatabase);
                }
                if ($license->isFrontendLocked()) {
                    // Since throwing an exception now results in showing offline.html, we can simply do
                    throw new \Exception('Frontend locked by license');
                }
                break;

            case \Cx\Core\Core\Controller\Cx::MODE_BACKEND:

                $objTemplate = $this->cx->getTemplate();
                if ($objTemplate->blockExists('upgradable')) {
                    if ($license->isUpgradable()) {
                        $objTemplate->touchBlock('upgradable');
                    } else {
                        $objTemplate->hideBlock('upgradable');
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
// TODO: Deactivated license check for now. Implement new behavior.
        return true;

        global $plainCmd, $objDatabase, $_CORELANG, $_LANGID, $section;

        $license = \Cx\Core_Modules\License\License::getCached(\Env::get('config'), $objDatabase);

        switch ($this->cx->getMode()) {
            case \Cx\Core\Core\Controller\Cx::MODE_FRONTEND:
                if (!($license->isInLegalComponents('fulllanguage')) && $_LANGID != \FWLanguage::getDefaultLangId()) {
                    $_LANGID = \FWLanguage::getDefaultLangId();
                    \Env::get('Resolver')->redirectToCorrectLanguageDir();
                }

                if (!empty($section) && !$license->isInLegalFrontendComponents($section)) {
                    if ($section == 'Error') {
                        // If the error module is not installed, show this
                        die($_CORELANG['TXT_THIS_MODULE_DOESNT_EXISTS']);
                    } else {
                        //page not found, redirect to error page.
                        \Cx\Core\Csrf\Controller\Csrf::header('Location: ' . \Cx\Core\Routing\Url::fromModuleAndCmd('Error'));
                        exit;
                    }
                }
                break;

            case \Cx\Core\Core\Controller\Cx::MODE_BACKEND:
                // check if the requested module is active:
                if (!in_array($plainCmd, array('Login', 'license', 'noaccess', ''))) {
                    $query = '
                                SELECT
                                    modules.is_licensed
                                FROM
                                    ' . DBPREFIX . 'modules AS modules,
                                    ' . DBPREFIX . 'backend_areas AS areas
                                WHERE
                                    areas.module_id = modules.id
                                    AND (
                                        areas.uri LIKE "%cmd=' . contrexx_raw2db($plainCmd) . '&%"
                                        OR areas.uri LIKE "%cmd=' . contrexx_raw2db($plainCmd) . '"
                                    )
                            ';
                    $res = $objDatabase->Execute($query);
                    if (!$res->fields['is_licensed']) {
                        $plainCmd = 'license';
                    }
                }

                // If logged in
                if (\Env::get('cx')->getUser()->objUser->login(true)) {
                    $license->check();
                    if ($license->getState() == \Cx\Core_Modules\License\License::LICENSE_NOK) {
                        $plainCmd = 'license';
                        $license->save(new \Cx\Core\Config\Controller\Config(), $objDatabase);
                    }
                    $lc = \Cx\Core_Modules\License\LicenseCommunicator::getInstance(\Env::get('config'));
                    $lc->addJsUpdateCode($_CORELANG, $license, $plainCmd == 'license');
                }
                break;

            default:
                break;
        }
    }

}
