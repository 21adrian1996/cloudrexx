<?php

/**
 * Module Checker
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team <info@comvation.com>
 * @version     2.0.0
 * @package     contrexx
 * @subpackage  core
 */

namespace Cx\Core
{

    /**
     * Module Checker
     * Checks for installed and activated modules
     *
     * @copyright   CONTREXX CMS - COMVATION AG
     * @author      Comvation Development Team <info@comvation.com>
     * @version     2.0.0
     * @package     contrexx
     * @subpackage  core
     */
    class ModuleChecker
    {

        /**
         * Entity Manager
         *
         * @access  private
         * @var     EntityManager
         */
        private $em = null;

        /**
         * Database
         *
         * @access  private
         * @var     ADONewConnection
         */
        private $db = null;

        /**
         * Names of all core modules
         *
         * @access  private
         * @var     array
         */
        private $arrCoreModules = array();

        /**
         * Names of all modules (except core modules)
         *
         * @access  private
         * @var     array
         */
        private $arrModules = array();

        /**
         * Names of active modules
         * 
         * @access  private
         * @var     array
         */
        private $arrActiveModules = array();

        /**
         * Names of installed modules
         * 
         * @access  private
         * @var     array
         */
        private $arrInstalledModules = array();


        /**
         * Constructor
         *
         * @access  public
         * @param   EntityManager       $em
         * @param   ADONewConnection    $db
         */
        public function __construct($em, $db){
            $this->em = $em;
            $this->db = $db;

            $this->init();
        }

        /**
         * Initialisation
         *
         * @access  private
         */
        private function init()
        {
            // check the content for installed and used modules
            $arrCmActiveModules = array();
            $arrCmInstalledModules = array();
            $qb = $this->em->createQueryBuilder();
            $qb->add('select', 'p')
                ->add('from', 'Cx\Core\ContentManager\Model\Doctrine\Entity\Page p')
                ->add('where',
                    $qb->expr()->andx(
                        $qb->expr()->eq('p.lang', FRONTEND_LANG_ID),
// TODO: what is the proper syntax for non-empty values?
// TODO: add additional check for module != NULL
                        $qb->expr()->neq('p.module', $qb->expr()->literal(''))
                    ));
            $pages = $qb->getQuery()->getResult();
            foreach ($pages as $page) {
                $arrCmInstalledModules[] = $page->getModule();
                if ($page->isActive()) {
                    $arrCmActiveModules[] = $page->getModule();
                }
            }

            $arrCmInstalledModules = array_unique($arrCmInstalledModules);
            $arrCmActiveModules = array_unique($arrCmActiveModules);

            // add static modules
            $arrCmInstalledModules[] = 'block';
            $arrCmActiveModules[] = 'block';
            $arrCmInstalledModules[] = 'upload';
            $arrCmActiveModules[] = 'upload';

            $objResult = $this->db->Execute('SELECT `name`, `is_core`, `is_required` FROM `'.DBPREFIX.'modules`');
            if ($objResult !== false) {
                while (!$objResult->EOF) {
                    $moduleName = $objResult->fields['name'];

                    if (!empty($moduleName)) {
                        $isCore = $objResult->fields['is_core'];

                        if ($isCore == 1) {
                            $this->arrCoreModules[] = $moduleName;
                        } else {
                            $this->arrModules[] = $moduleName;
                        }

                        if ((in_array($moduleName, $arrCmInstalledModules)) &&
                            ($isCore || (!$isCore && is_dir(ASCMS_MODULE_PATH.'/'.$moduleName)))
                        ) {
                            $this->arrInstalledModules[] = $moduleName;
                        }

                        if ((in_array($moduleName, $arrCmActiveModules)) &&
                            ($isCore || (!$isCore && is_dir(ASCMS_MODULE_PATH.'/'.$moduleName)))
                        ) {
                            $this->arrActiveModules[] = $moduleName;
                        }
                    }

                    $objResult->MoveNext();
                }
            }
        }

        /**
         * Checks if the passed module is a core module.
         *
         * @access  public
         * @param   string      $moduleName
         * @return  boolean
         */
        public function isCoreModule($moduleName)
        {
            return in_array($moduleName, $this->arrCoreModules);
        }

        /**
         * Checks if the passed module is active
         * (application page exists and is active).
         *
         * @access  public
         * @param   string      $moduleName
         * @return  boolean
         */
        public function isModuleActive($moduleName)
        {
            return in_array($moduleName, $this->arrActiveModules);
        }

        /**
         * Checks if the passed module is installed
         * (application page exists).
         *
         * @access  public
         * @param   string      $moduleName
         * @return  boolean
         */
        public function isModuleInstalled($moduleName)
        {
            return in_array($moduleName, $this->arrInstalledModules);
        }
    }
}
