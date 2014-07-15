<?php
/**
 * This handles exceptions for new Component structure. This is old code
 * and should be replaced so that this class becomes unnecessary
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Michael Ritter <michael.ritter@comvation.com>
 * @package     contrexx
 * @subpackage  core_core
 * @link        http://www.contrexx.com/ contrexx homepage
 * @since       v3.1.0
 * @todo: Remove this code (move all exceptions to components)
 */

namespace Cx\Core\Core\Controller;

/**
 * This handles exceptions for new Component structure. This is old code
 * and should be replaced so that this class becomes unnecessary
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Michael Ritter <michael.ritter@comvation.com>
 * @package     contrexx
 * @subpackage  core_core
 * @link        http://www.contrexx.com/ contrexx homepage
 * @since       v3.1.0
 * @todo: Remove this code (move all exceptions to components)
 */
class LegacyComponentHandler {
    /**
     * This is the list of exceptions
     *
     * array[
     *     frontend|
     *     backend
     * ][
     *     preResolve|
     *     postResolve|
     *     preContentLoad|
     *     preContentParse|
     *     load|
     *     postContentParse|
     *     postContentLoad|
     *     preFinalize|
     *     postFinalize
     * ] = {callable}
     * @var array
     */
    private $exceptions = array();

    /**
     * Tells wheter there is an exception for a certain action and component or not
     * @param boolean $frontend Are we in frontend mode or not
     * @param string $action Name of action
     * @param string $componentName Component name
     * @return boolean True if there is an exception listed, false otherwise
     */
    public function hasExceptionFor($frontend, $action, $componentName) {
        if (!isset($this->exceptions[$frontend ? 'frontend' : 'backend'][$action])) {
            return false;
        }
        return isset($this->exceptions[$frontend ? 'frontend' : 'backend'][$action][$componentName]);
    }

    /**
     * Checks if the component is active and in the list of legal components (license)
     * @param  boolean $frontend      Are we in frontend mode or not
     * @param  string  $componentName Component name
     * @return boolean True if the component is active and legal, false otherwise
     */
    public function isActive($frontend, $componentName) {
        $cx = \Env::get('cx');
        $cn = strtolower($componentName);
        $mc = \Cx\Core\ModuleChecker::getInstance($cx->getDb()->getEntityManager(), $cx->getDb()->getAdoDb(), $cx->getClassLoader());

        if (!in_array($cn, $mc->getModules())) {
            return true;
        }

        if ($frontend) {
            if (!$cx->getLicense()->isInLegalFrontendComponents($cn)) {
                return false;
            }
        } else {
            if (!$cx->getLicense()->isInLegalComponents($cn)) {
                return false;
            }
        }

        if (!$mc->isModuleInstalled($cn)) {
            return false;
        }

        return true;
    }

    /**
     * Executes an exception (if any) for a certain action and component
     * @param boolean $frontend Are we in frontend mode or not
     * @param string $action Name of action
     * @param string $componentName Component name
     * @return mixed Return value of called exception (most of them return null)
     */
    public function executeException($frontend, $action, $componentName) {
        if (!$this->hasExceptionFor($frontend, $action, $componentName)
            || !$this->isActive($frontend, $componentName)) {
            return false;
        }

        return $this->exceptions[$frontend ? 'frontend' : 'backend'][$action][$componentName]();
    }

    /**
     * Pushes all the legacy code into our array of exceptions
     * @throws \Exception If frontend is locked by license
     */
    public function __construct() {
        // now follows the loooooooooooong list of old code:
        $this->exceptions = array(
            'frontend' => array(
                'preResolve' => array(
                    
                    'Upload' => function() {
                        global $isRegularPageRequest;

                        if (isset($_REQUEST['section']) && $_REQUEST['section'] == 'upload') {
                            $_REQUEST['standalone'] = 'true';
                        }
                    }
                ),
                'postResolve' => array(
                    'Upload' => function() {
                        global $sessionObj;

                        if (isset($_REQUEST['section']) && $_REQUEST['section'] == 'upload') {
                            if (!isset($sessionObj) || !is_object($sessionObj)) $sessionObj = \cmsSession::getInstance(); // initialize session object                            
                            $objUploadModule = new \Upload();
                            $objUploadModule->getPage();
                            //execution never reaches this point
                        }
                    },
                ),
                'preContentLoad' => array(
                ),
                'postContentLoad' => array(
                    /*'FrontendEditing' => function() {
                        $frontendEditing = new \Cx\Core_Modules\FrontendEditing\Controller\ComponentController();
                        $frontendEditing->preFinalize();
                    },*/
                ),
                'load' => array(
                ),
            ),
            'backend' => array(
                'preResolve' => array(
                    'ComponentHandler' => function() {
                        global $arrMatch, $plainCmd, $cmd;

                        // To clone any module, use an optional integer cmd suffix.
                        // E.g.: "shop2", "gallery5", etc.
                        // Mind that you *MUST* copy all necessary database tables, and fix any
                        // references to that module (section and cmd parameters, database tables)
                        // using the MODULE_INDEX constant in the right place both in your code
                        // *and* templates!
                        // See the Shop module for a working example and instructions on how to
                        // clone any module.
                        $arrMatch = array();
                        if (!isset($plainCmd)) {
                            $plainCmd = $cmd;
                        }
                        if (preg_match('/^(\D+)(\d+)$/', $cmd, $arrMatch)) {
                            // The plain section/module name, used below
                            $plainCmd = $arrMatch[1];
                        }
                        // The module index.
                        // Set to the empty string for the first instance (#1),
                        // and to an integer number of 2 or greater for any clones.
                        // This guarantees full backward compatibility with old code, templates
                        // and database tables for the default instance.
                        $moduleIndex = (empty($arrMatch[2]) ? '' : $arrMatch[2]);

                        /**
                        * @ignore
                        */
                        define('MODULE_INDEX', (intval($moduleIndex) == 0) ? '' : intval($moduleIndex));
                        // Simple way to distinguish any number of cloned modules
                        // and apply individual access rights.  This offset is added
                        // to any static access ID before checking it.
                        // @todo this is never used in Cx Init
                        //$intAccessIdOffset = intval(MODULE_INDEX)*1000;
                    },
                ),
                'postResolve' => array(
                    
                ),
                'load' => array(
                    'upload' => function() {
                        global $cl, $_CORELANG;

                        if (!$cl->loadFile(ASCMS_CORE_MODULE_PATH.'/upload/admin.class.php'))
                            die ($_CORELANG['TXT_THIS_MODULE_DOESNT_EXISTS']);
                        $objUploadModule = new \Upload();
                        $objUploadModule->getPage();
                        //execution never reaches this point
                    },
                    'noaccess' => function() {
                        global $cl, $_CORELANG, $objTemplate, $_CONFIG;

                        //Temporary no-acces-file and comment
                        $subMenuTitle = $_CORELANG['TXT_ACCESS_DENIED'];
                        $objTemplate->setVariable(array(
                            'CONTENT_NAVIGATION' => '<span id="noaccess_title">'.contrexx_raw2xhtml($_CONFIG['coreCmsName']).'</span>',
                            'ADMIN_CONTENT' =>
                                '<img src="images/no_access.png" alt="" /><br /><br />'.
                                $_CORELANG['TXT_ACCESS_DENIED_DESCRIPTION'],
                        ));
                    },
                    'logout' => function() {
                        \FWUser::getFWUserObject()->logout();
                        exit;
                    },
                    'country' => function() {
                        global $cl, $_CORELANG, $subMenuTitle;

                // TODO: Move this define() somewhere else, allocate the IDs properly
                        define('PERMISSION_COUNTRY_VIEW', 145);
                        define('PERMISSION_COUNTRY_EDIT', 146);
                        \Permission::checkAccess(PERMISSION_COUNTRY_VIEW, 'static');
                        if (!$cl->loadFile(ASCMS_CORE_PATH.'/Country.class.php'))
                            die($_CORELANG['TXT_THIS_MODULE_DOESNT_EXISTS']);
                        $subMenuTitle = $_CORELANG['TXT_CORE_COUNTRY'];
                        \Country::getPage();
                    },
                    
                ),
            ),
        );
    }
}
