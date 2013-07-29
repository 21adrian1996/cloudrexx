<?php
/**
 * This file loads everything needed to load Contrexx. Just require this file
 * and execute \init($mode); while $mode is optional. $mode can be one of
 * 'frontend', 'backend', 'cli' and 'minimal'
 * 
 * This is just a wrapper to load the contrexx class
 * It is used in order to display a proper error message on hostings without
 * PHP 5.3 or newer.
 * 
 * DO NOT USE NAMESPACES WITHIN THIS FILE or else the error message won't be
 * displayed on these hostings.
 * 
 * Checks PHP version, loads debugger and initial config, checks if installed
 * and loads the Contrexx class
 * @version 3.1.0
 * @author Michael Ritter <michael.ritter@comvation.com>
 */

// Check php version (5.3 or newer is required)
$php = phpversion();
if (version_compare($php, '5.3.0') < 0) {
    die('Das Contrexx CMS ben&ouml;tigt mindestens PHP in der Version 5.3.<br />Auf Ihrem System l&auml;uft PHP '.$php);
}

global $_PATHCONFIG;
/**
 * Load config for this instance
 */
include_once dirname(dirname(dirname(__FILE__))).'/config/configuration.php';

/**
 * Debug level, see lib/FRAMEWORK/DBG/DBG.php
 *   DBG_PHP             - show PHP errors/warnings/notices
 *   DBG_ADODB           - show ADODB queries
 *   DBG_ADODB_TRACE     - show ADODB queries with backtrace
 *   DBG_ADODB_ERROR     - show ADODB queriy errors only
 *   DBG_LOG_FILE        - DBG: log to file (/dbg.log)
 *   DBG_LOG_FIREPHP     - DBG: log via FirePHP
 *
 * Use DBG::activate($level) and DBG::deactivate($level)
 * to activate/deactivate a debug level.
 * Calling these methods without specifying a debug level
 * will either activate or deactivate all levels.
 */
require_once dirname(dirname(dirname(__FILE__))).'/lib/FRAMEWORK/DBG/DBG.php';

/**
 * If you activate debugging here, it will be activated everywhere (even in cronjobs, since they should base on this too)
 */
//\DBG::activate(DBG_PHP);

require_once dirname(dirname(dirname(__FILE__))).'/core/Core/Controller/Cx.class.php';
