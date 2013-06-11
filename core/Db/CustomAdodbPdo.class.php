<?php
/**
 * This class is needed in order to make AdoDB use an existing PDO connection
 * @copyright   Comvation AG
 * @author      Michael Ritter <michael.ritter@comvation.com>
 * @package     contrexx
 * @subpackage  core_db
 */

namespace Cx\Core\Db;

\Env::get('ClassLoader')->loadFile(ASCMS_LIBRARY_PATH . '/adodb/drivers/adodb-pdo.inc.php');

/**
 * This class is needed in order to make AdoDB use an existing PDO connection
 * @copyright   Comvation AG
 * @author      Michael Ritter <michael.ritter@comvation.com>
 * @package     contrexx
 * @subpackage  core_db
 */
class CustomAdodbPdo extends \ADODB_pdo 
{
    
    /**
     * Initializes Adodb with an existing PDO connection
     * @param \PDO $pdo PDO connection to use
     * @return boolean True on success, false otherwise
     */
    function __construct($pdo) 
    { 
        try { 
            $this->_connectionID = $pdo; 
        } catch (Exception $e) { 
            $this->_connectionID = false; 
            $this->_errorno = -1; 
            $this->_errormsg = 'Connection attempt failed: '.$e->getMessage(); 
            return false; 
        } 

        if ($this->_connectionID) { 
            $this->dsnType = strtolower($pdo->getAttribute(\PDO::ATTR_DRIVER_NAME));

            switch (ADODB_ASSOC_CASE) { 
                case 0: $m = \PDO::CASE_LOWER; break; 
                case 1: $m = \PDO::CASE_UPPER; break; 
                default: 
                case 2: $m = \PDO::CASE_NATURAL; break; 
            } 

            $this->_connectionID->setAttribute(\PDO::ATTR_CASE,$m); 

            $class = 'ADODB_pdo_'.$this->dsnType; 

            switch ($this->dsnType) { 
                case 'oci': 
                case 'mysql': 
                case 'pgsql': 
                case 'mssql': 
                    include_once(ADODB_DIR.'/drivers/adodb-pdo_'.$this->dsnType.'.inc.php'); 
                    break; 
            } 
            if (class_exists($class)) 
                $this->_driver = new $class(); 
            else 
                $this->_driver = new \ADODB_pdo_base(); 

            $this->_driver->_connectionID = $this->_connectionID; 
            $this->_UpdatePDO(); 
            return true; 
        } 
        $this->_driver = new \ADODB_pdo_base(); 
        return false; 
    } 
}
