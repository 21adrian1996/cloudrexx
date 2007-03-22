<?php
/**
* @exclude
*
* Contrexx CMS Web Installer
* Please use the Contrexx CMS installer to configure this file
* or edit this file and configure the parameters for your site and
* database manually.
*/

/**
* -------------------------------------------------------------------------
* Set installation status
* -------------------------------------------------------------------------
*/
define('CONTEXX_INSTALLED', true);

/**
* -------------------------------------------------------------------------
* Database configuration section
* -------------------------------------------------------------------------
*/
$_DBCONFIG['host'] = "%DB_HOST%"; // This is normally set to localhost
$_DBCONFIG['database'] = "%DB_NAME%"; // Database name
$_DBCONFIG['tablePrefix'] = "%DB_TABLE_PREFIX%";  // Database table prefix
$_DBCONFIG['user'] = "%DB_USER%"; // Database username
$_DBCONFIG['password'] = "%DB_PASSWORD%"; // Database password
$_DBCONFIG['dbType'] = "mysql";	// Database type (e.g. mysql,postgres ..)

/**
* -------------------------------------------------------------------------
* Site path specific configuration
* -------------------------------------------------------------------------
*/
$_PATHCONFIG['ascms_root'] = "%PATH_ROOT%";
$_PATHCONFIG['ascms_root_offset'] = "%PATH_ROOT_OFFSET%"; // example: "/cms";


/**
* -------------------------------------------------------------------------
* Ftp specific configuration
* -------------------------------------------------------------------------
*/
$_FTPCONFIG['is_activated'] = %FTP_STATUS%;	// Ftp support true or false
$_FTPCONFIG['use_passive'] = %FTP_PASSIVE%;	// Use passive ftp mode
$_FTPCONFIG['host']	= "%FTP_HOST%";	// This is normally set to localhost
$_FTPCONFIG['port'] = %FTP_PORT%; // Ftp remote port
$_FTPCONFIG['username'] = "%FTP_USER%";	// Ftp login username
$_FTPCONFIG['password']	= "%FTP_PASSWORD%";	// Ftp login password
$_FTPCONFIG['path']	= "%FTP_PATH%";	// Ftp path to cms


/**
* -------------------------------------------------------------------------
* Optional customizing exceptions
* Shopnavbar : If set to TRUE the shopnavbar will appears on each page
* -------------------------------------------------------------------------
*/
$_CONFIGURATION['custom']['shopnavbar'] = FALSE;  // TRUE|FALSE
$_CONFIGURATION['custom']['shopJsCart'] = false;		// true|false


/**
* -------------------------------------------------------------------------
* Set constants
* -------------------------------------------------------------------------
*/
require_once dirname(__FILE__).'/set_constants.php';
?>