<?php
global $_DBCONFIG, $_PATHCONFIG, $_FTPCONFIG;
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
$_DBCONFIG['host'] = 'localhost'; // This is normally set to localhost
$_DBCONFIG['database'] = 'dev'; // Database name
$_DBCONFIG['tablePrefix'] = 'contrexx_'; // Database table prefix
$_DBCONFIG['user'] = 'root'; // Database username
$_DBCONFIG['password'] = ''; // Database password
$_DBCONFIG['dbType'] = 'mysql';    // Database type (e.g. mysql,postgres ..)
$_DBCONFIG['charset'] = 'utf8'; // Charset (default, latin1, utf8, ..)
$_DBCONFIG['timezone'] = $_CONFIG['timezone']; // Timezone

/**
* -------------------------------------------------------------------------
* Site path specific configuration
* -------------------------------------------------------------------------
*/
$_PATHCONFIG['ascms_root'] = '/home/user/web/cm23';
$_PATHCONFIG['ascms_root_offset'] = '/cm_2_3'; // example: '/cms';
$_PATHCONFIG['ascms_installation_root'] = $_PATHCONFIG['ascms_root'];
$_PATHCONFIG['ascms_installation_offset'] = $_PATHCONFIG['ascms_root_offset']; // example: '/cms';

/**
* -------------------------------------------------------------------------
* Ftp specific configuration
* -------------------------------------------------------------------------
*/
$_FTPCONFIG['is_activated'] = false; // Ftp support true or false
$_FTPCONFIG['use_passive'] = false;    // Use passive ftp mode
$_FTPCONFIG['host']    = 'localhost';// This is normally set to localhost
$_FTPCONFIG['port'] = 21; // Ftp remote port
$_FTPCONFIG['username'] = ''; // Ftp login username
$_FTPCONFIG['password'] = ''; // Ftp login password
$_FTPCONFIG['path'] = ''; // Ftp path to cms (must not include ascms_root_offset)

/**
* -------------------------------------------------------------------------
* Base setup (altering might break the system!)
* -------------------------------------------------------------------------
*/
// Set character encoding
$_CONFIG['coreCharacterEncoding'] = 'UTF-8'; // example 'UTF-8'

// @todo MOVE TO Cx CLASS

@ini_set('default_charset', $_CONFIG['coreCharacterEncoding']);

// Set output url seperator
@ini_set('arg_separator.output', '&amp;');

// Set url rewriter tags
@ini_set('url_rewriter.tags', 'a=href,area=href,frame=src,iframe=src,input=src,form=,fieldset=');

// Set timezone
@ini_set('date.timezone', $_DBCONFIG['timezone']);


