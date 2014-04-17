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
$_DBCONFIG['host'] = 'localhost'; // This is normally set to localhost
$_DBCONFIG['database'] = 'com_live_aac'; // Database name
$_DBCONFIG['tablePrefix'] = 'contrexx_'; // Database table prefix
$_DBCONFIG['user'] = 'a_com_live_aac'; // Database username
$_DBCONFIG['password'] = 'QXMM56dy'; // Database password
$_DBCONFIG['dbType'] = 'mysql';    // Database type (e.g. mysql,postgres ..)
$_DBCONFIG['charset'] = 'utf8'; // Charset (default, latin1, utf8, ..)
$_DBCONFIG['timezone'] = 'Europe/Zurich'; // Timezone

/**
* -------------------------------------------------------------------------
* Site path specific configuration
* -------------------------------------------------------------------------
*/
$_PATHCONFIG['ascms_root'] = '/home/httpd/vhosts/h1.cloudrexx.com/httpdocs';
$_PATHCONFIG['ascms_root_offset'] = ''; // example: '/cms';
$_PATHCONFIG['ascms_installation_root'] = '/home/httpd/vhosts/h1.cloudrexx.com/httpdocs'; // document root where the files are
$_PATHCONFIG['ascms_installation_offset'] = ''; // path offset where the files are

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
@ini_set('default_charset', $_CONFIG['coreCharacterEncoding']);

// Set output url seperator
@ini_set('arg_separator.output', '&amp;');

// Set url rewriter tags
@ini_set('url_rewriter.tags', 'a=href,area=href,frame=src,iframe=src,input=src,form=,fieldset=');

