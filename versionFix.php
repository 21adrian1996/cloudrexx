<?php

/**
 * versionFix.php
 *
 * This script sets the correct version for
 * Contrexx 3.0 SP 1 installations with incorrect version and
 * sets the empty module distributor values to 'Comvation AG'.
 */

$documentRoot = dirname(__FILE__);

require_once($documentRoot . '/lib/FRAMEWORK/DBG/DBG.php');
\DBG::deactivate();

require_once $documentRoot . '/config/settings.php';
require_once $documentRoot . '/config/configuration.php';
require_once $documentRoot . '/core/Env.class.php';
require_once $documentRoot . '/core/ClassLoader/ClassLoader.class.php';
$cl = new \Cx\Core\ClassLoader\ClassLoader($documentRoot, true);
\Env::set('ClassLoader', $cl);
\Env::set('config', $_CONFIG);
\Env::set('ftpConfig', $_FTPCONFIG);
require_once $documentRoot . '/core/API.php';
require_once $documentRoot . '/core/Init.class.php';
require_once $documentRoot . '/core/settings.class.php';

$objDatabase = getDatabaseObject($errorMsg, true);
$objInit = new \InitCMS('frontend', null);
$_CORELANG = $objInit->loadLanguageData('core');

if (!empty($_CONFIG['coreCmsVersion']) && $_CONFIG['coreCmsVersion'] < '3.0.1' && $_CONFIG['coreCmsVersion'] >= '3.0.0') {
    $arrColumns = $objDatabase->MetaColumns(DBPREFIX . 'module_contact_form');
    $arrColumns = array_keys(array_change_key_case($arrColumns, CASE_LOWER));
    // Check if the installation is a SP1 based on an existing table field
    if (in_array('use_email_of_sender', $arrColumns)) {
        // Update version in database
        $objDatabase->Execute('
            UPDATE  `' . DBPREFIX . 'settings`
               SET  `setvalue` =  \'3.0.1\'
             WHERE  `setname` = \'coreCmsVersion\';
        ');

        // Update version in settings file
        $objSettings = new \settingsManager();
        $objSettings->writeSettingsFile();

        echo 'Die Version wurde angepasst.<br />';
    } else {
        echo 'Die Version ist bereits angepasst.<br />';
    }
} else {
    echo 'Die Version ist bereits angepasst.<br />';
}

// Set emtpy module distributor values to 'Comvation AG'
$objResult = $objDatabase->Execute('
    SELECT `id`
      FROM `' . DBPREFIX . 'modules`
     WHERE `distributor` = \'\'
');
if ($objResult->RecordCount()) {
    while (!$objResult->EOF) {
        $objDatabase->Execute('
            UPDATE `' . DBPREFIX . 'modules`
               SET `distributor` = \'Comvation AG\'
             WHERE `id` = ' . $objResult->fields['id'] . '
        ');
        $objResult->MoveNext();
    }
    echo 'Die Modultabelle wurde korrigiert.<br />';
} else {
    echo 'Die Modultabelle ist bereits korrigiert.<br />';
}
echo 'Skript erfolgreich ausgeführt.';
