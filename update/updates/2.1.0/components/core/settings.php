<?php
function _updateSettings()
{
    global $objDatabase, $_ARRAYLANG, $_CORELANG, $_CONFIG, $arrSettings, $arrSettingsByName;

    $setVars = false;

    $arrSettings = array(
        3    => array(
            'setname'    => 'dnsServer',
            'setvalue'    => 'ns1.contrexxhosting.com',
            'setmodule'    => 1
        ),
        4    => array(
            'setname'    => 'bannerStatus',
            'setvalue'    => '0',
            'setmodule'    => 28
        ),
        5    => array(
            'setname'    => 'spamKeywords',
            'setvalue'    => 'sex, viagra',
            'setmodule'    => 1
        ),
        11    => array(
            'setname'    => 'coreAdminName',
            'setvalue'    => '',
            'setmodule'    => 1
        ),
        18    => array(
            'setname'    => 'corePagingLimit',
            'setvalue'    => '30',
            'setmodule'    => 1
        ),
        19    => array(
            'setname'    => 'searchDescriptionLength',
            'setvalue'    => '150',
            'setmodule'    => 5
        ),
        23    => array(
            'setname'    => 'coreIdsStatus',
            'setvalue'    => 'off',
            'setmodule'    => 1
        ),
        24    => array(
            'setname'    => 'coreAdminEmail',
            'setvalue'    => '',
            'setmodule'    => 1
        ),
        29    => array(
            'setname'    => 'contactFormEmail',
            'setvalue'    => '',
            'setmodule'    => 6
        ),
        34    => array(
            'setname'    => 'sessionLifeTime',
            'setvalue'    => '3600',
            'setmodule'    => 1
        ),
        35    => array(
            'setname'    => 'lastAccessId',
            'setvalue'    => '0',
            'setmodule'    => 1
        ),
        37    => array(
            'setname'    => 'newsTeasersStatus',
            'setvalue'    => '0',
            'setmodule'    => 8
        ),
        39    => array(
            'setname'    => 'feedNewsMLStatus',
            'setvalue'    => '0',
            'setmodule'    => 22
        ),
        40    => array(
            'setname'    => 'calendarheadlines',
            'setvalue'    => '1',
            'setmodule'    => 21
        ),
        41    => array(
            'setname'    => 'calendarheadlinescount',
            'setvalue'    => '5',
            'setmodule'    => 21
        ),
        42    => array(
            'setname'    => 'blockStatus',
            'setvalue'    => '1',
            'setmodule'    => 7
        ),
        43    => array(
            'setname'    => 'contentHistoryStatus',
            'setvalue'    => 'on',
            'setmodule'    => 1
        ),
        44    => array(
            'setname'    => 'calendarheadlinescat',
            'setvalue'    => '0',
            'setmodule'    => 21
        ),
        45    => array(
            'setname'    => 'calendardefaultcount',
            'setvalue'    => '30',
            'setmodule'    => 21
        ),
        48    => array(
            'setname'    => 'blockRandom',
            'setvalue'    => '1',
            'setmodule'    => 7
        ),
        49    => array(
            'setname'    => 'directoryHomeContent',
            'setvalue'    => '0',
            'setmodule'    => 12
        ),
        50    => array(
            'setname'    => 'cacheEnabled',
            'setvalue'    => 'off',
            'setmodule'    => 1
        ),
        51    => array(
            'setname'    => 'coreGlobalPageTitle',
            'setvalue'    => 'Contrexx Demo-Seite',
            'setmodule'    => 1
        ),
        52    => array(
            'setname'    => 'cacheExpiration',
            'setvalue'    => '86400',
            'setmodule'    => 1
        ),
        53    => array(
            'setname'    => 'domainUrl',
            'setvalue'    => 'localhost',
            'setmodule'    => 1
        ),
        54    => array(
            'setname'    => 'googleSitemapStatus',
            'setvalue'    => 'off',
            'setmodule'    => 1
        ),
        55    => array(
            'setname'    => 'systemStatus',
            'setvalue'    => 'on',
            'setmodule'    => 1
        ),
        56    => array(
            'setname'    => 'searchVisibleContentOnly',
            'setvalue'    => 'on',
            'setmodule'    => 1
        ),
        60    => array(
            'setname'    => 'forumHomeContent',
            'setvalue'    => '0',
            'setmodule'    => 20
        ),
        61    => array(
            'setname'    => 'aliasStatus',
            'setvalue'    => '',
            'setmodule'    => 41
        ),
        62    => array(
            'setname'    => 'coreSmtpServer',
            'setvalue'    => '0',
            'setmodule'    => 1
        ),
        63    => array(
            'setname'    => 'languageDetection',
            'setvalue'    => 'on',
            'setmodule'    => 1
        ),
        64    => array(
            'setname'    => 'podcastHomeContent',
            'setvalue'    => '0',
            'setmodule'    => 35
        ),
        65    => array(
            'setname'    => 'googleMapsAPIKey',
            'setvalue'    => '',
            'setmodule'    => 1
        ),
        66    => array(
            'setname'    => 'forumTagContent',
            'setvalue'    => '0',
            'setmodule'    => 20
        ),
        67    => array(
            'setname'    => 'useVirtualLanguagePath',
            'setvalue'    => 'off',
            'setmodule'    => 1
        ),
        68    => array(
            'setname'    => 'dataUseModule',
            'setvalue'    => '1',
            'setmodule'    => 48
        ),
        69    => array(
            'setname'    => 'frontendEditingStatus',
            'setvalue'    => 'on',
            'setmodule'    => 1
        ),
        72    => array(
            'setname'    => 'useKnowledgePlaceholders',
            'setvalue'    => '1',
            'setmodule'    => 56
        ),

        useKnowledgePlaceholders

    );

    $arrSettingsByName = array();
    foreach ($arrSettings as $setid => $data) {
        $arrSettingsByName[$data['setname']] = $setid;
    }


    // add coreGlobalPageTitle
    if (!empty($_POST['coreGlobalPageTitle'])) {
        $query = "SELECT setid FROM ".DBPREFIX."settings WHERE setname='coreGlobalPageTitle'";
        $objResult = $objDatabase->SelectLimit($query, 1);
        if ($objResult) {
            $coreGlobalPageTitle = contrexx_addslashes($_POST['coreGlobalPageTitle']);
            if ($objResult->RecordCount() == 1) {
                $query = "UPDATE ".DBPREFIX."settings SET setvalue='".$coreGlobalPageTitle."' WHERE setname='coreGlobalPageTitle'";
                if ($objDatabase->Execute($query) === false) {
                    return _databaseError($query, $objDatabase->ErrorMsg());
                }
            } else {
                $query = "INSERT INTO ".DBPREFIX."settings ( `setid` , `setname` , `setvalue` , `setmodule` ) VALUES (51, 'coreGlobalPageTitle', '".$coreGlobalPageTitle."', '1')";
                if ($objDatabase->Execute($query) === false) {
                    return _databaseError($query, $objDatabase->ErrorMsg());
                }
            }
        } else {
            return _databaseError($query, $objDatabase->ErrorMsg());
        }
    } else {
        $query = "SELECT setvalue FROM ".DBPREFIX."settings WHERE setname = 'coreGlobalPageTitle'";
        $objResult = $objDatabase->SelectLimit($query, 1);
        if ($objResult) {
            if ($objResult->RecordCount() == 1) {
                $coreGlobalPageTitle = $objResult->fields['setvalue'];
            } else {
                $coreGlobalPageTitle = 'Contrexx CMS Demo';
            }
        } else {
            return _databaseError($query, $objDatabase->ErrorMsg());
        }

        $setVars = true;
    }

    // add domainUrl
    if (empty($_CONFIG['domainUrl']) || empty($_CONFIG['coreGlobalPageTitle'])) {
        if (!empty($_POST['domainURL']) && strpos($_POST['domainURL'], 'http://') === false) {
            if (substr($_POST['domainURL'], -1) == '/') {
                $domainURL = substr($_POST['domainURL'], 0, -1);
            } else {
                $domainURL = $_POST['domainURL'];
            }

            $query = "SELECT setid from ".DBPREFIX."settings WHERE setname = 'domainUrl'";
            $objResult = $objDatabase->SelectLimit($query, 1);
            if ($objResult) {
                if ($objResult->RecordCount() == 0) {
                    // check if the needed setid is already used
                    $query = "SELECT setname FROM ".DBPREFIX."settings WHERE setid=53";
                    $objResult = $objDatabase->SelectLimit($query, 1);
                    if ($objResult) {
                        if ($objResult->RecordCount() == 0) {
                            // add domainUrl
                            $query = "INSERT INTO ".DBPREFIX."settings ( `setid` , `setname` , `setvalue` , `setmodule` ) VALUES (53, 'domainUrl', '".contrexx_addslashes($domainURL)."', '1')";
                            if ($objDatabase->Execute($query) === false) {
                                return _databaseError($query, $objDatabase->ErrorMsg());
                            }
                        } else {
                            setUpdateMsg(sprintf($_ARRAYLANG['TXT_SYSTEM_CONFIG_IN_USE_BY_ID'], 'domainUrl', 53));
                            return false;
                        }
                    } else {
                        return _databaseError($query, $objDatabase->ErrorMsg());
                    }
                } elseif ($objResult->fields['setid'] != 53) {
                    $oldDomainUrlSetId = intval($objResult->fields['setid']);

                    // check if the needed setid is already used
                    $query = "SELECT setname FROM ".DBPREFIX."settings WHERE setid=53";
                    $objResult = $objDatabase->SelectLimit($query, 1);
                    if ($objResult) {
                        if ($objResult->RecordCount() == 0) {
                            // delete old domainUrl
                            $query = "DELETE FROM ".DBPREFIX."settings WHERE setid=".$oldDomainUrlSetId;
                            if ($objDatabase->Execute($query) === false) {
                                return _databaseError($query, $objDatabase->ErrorMsg());
                            }

                            // add domainUrl
                            $query = "INSERT INTO ".DBPREFIX."settings ( `setid` , `setname` , `setvalue` , `setmodule` ) VALUES (53, 'domainUrl', '".contrexx_addslashes($domainURL)."', '1')";
                            if ($objDatabase->Execute($query) === false) {
                                return _databaseError($query, $objDatabase->ErrorMsg());
                            }
                        } else {
                            setUpdateMsg(sprintf($_ARRAYLANG['TXT_SYSTEM_CONFIG_IN_USE_BY_ID'], 'domainUrl', 53));
                            return false;
                        }
                    } else {
                        return _databaseError($query, $objDatabase->ErrorMsg());
                    }
                } else {
                    // update domainUrl
                    $query = "UPDATE ".DBPREFIX."settings SET setvalue='".contrexx_addslashes($domainURL)."' WHERE setid = 53";
                    if ($objDatabase->Execute($query) === false) {
                        return _databaseError($query, $objDatabase->ErrorMsg());
                    }
                }
            } else {
                return _databaseError($query, $objDatabase->ErrorMsg());
            }
        } else {
            if (isset($_POST['domainURL'])) {
                $domainURL = $_POST['domainURL'];
            } else {
                $query = "SELECT setvalue FROM ".DBPREFIX."settings WHERE setname = 'domainUrl'";
                $objResult = $objDatabase->SelectLimit($query, 1);
                if ($objResult) {
                    if ($objResult->RecordCount() == 1) {
                        $domainURL = $objResult->fields['setvalue'];
                    } else {
                        $domainURL = $_SERVER['SERVER_NAME'];
                    }
                } else {
                    return _databaseError($query, $objDatabase->ErrorMsg());
                }
            }

            $setVars = true;
        }

        if ($setVars) {
            setUpdateMsg($_ARRAYLANG['TXT_NEW_BASIC_ADMINISTRATION'], 'title');
            setUpdateMsg($_ARRAYLANG['TXT_GLOBAL_PAGE_TITLE_TXT']."<br /><br />"
                .'<input size="80" type="text" name="coreGlobalPageTitle" value="'.htmlentities($coreGlobalPageTitle).'" /><br /><br /><br />'
                .$_ARRAYLANG['TXT_DOMAIN_URL_TXT']."<br /><br />"
                .'<input size="80" type="text" name="domainURL" value="'.htmlentities($domainURL).'" /><br /><br />', 'msg');
            setUpdateMsg('<input type="submit" value="'.$_CORELANG['TXT_CONTINUE_UPDATE'].'" name="updateNext" /><input type="hidden" name="processUpdate" id="processUpdate" />', 'button');
            return false;
        }
    }

    foreach ($arrSettings as $setId => $arrSetting) {
        if (!_updateSettingsTable($setId, $arrSetting)) {
            return false;
        }
    }

    $query = "UPDATE `".DBPREFIX."settings` SET `setmodule`=1 WHERE `setmodule`=0";
    if ($objDatabase->Execute($query) === false) {
        return _databaseError($query, $objDatabase->ErrorMsg());
    }


    // write settings
    require_once ASCMS_FRAMEWORK_PATH.'/File.class.php';
    $objFile = new File();

    $strFooter = '';
    $arrModules = '';

    if (!is_writable(ASCMS_DOCUMENT_ROOT.'/config/')) {
        $objFile->setChmod(ASCMS_DOCUMENT_ROOT.'/config', ASCMS_PATH_OFFSET.'/config', '/');
    }

    if (!file_exists(ASCMS_DOCUMENT_ROOT.'/config/settings.php')) {
        if (!touch(ASCMS_DOCUMENT_ROOT.'/config/settings.php')) {
            setUpdateMsg(sprintf($_ARRAYLANG['TXT_UNABLE_CREATE_SETTINGS_FILE'], ASCMS_DOCUMENT_ROOT.'/config/settings.php'));
            setUpdateMsg(sprintf($_ARRAYLANG['TXT_SET_WRITE_PERMISSON_TO_DIR'], ASCMS_DOCUMENT_ROOT.'/config/', $_CORELANG['TXT_UPDATE_TRY_AGAIN']), 'msg');
            return false;
        }
    }

    if (!is_writable(ASCMS_DOCUMENT_ROOT.'/config/settings.php')) {
        $objFile->setChmod(ASCMS_DOCUMENT_ROOT.'/config', ASCMS_PATH_OFFSET.'/config', '/settings.php');
    }

    if (is_writable(ASCMS_DOCUMENT_ROOT.'/config/settings.php')) {
        $handleFile = fopen(ASCMS_DOCUMENT_ROOT.'/config/settings.php','w+');
        if ($handleFile) {
            //Header & Footer
            $strHeader    = "<?php\n";
            $strHeader .= "/**\n";
            $strHeader .= "* This file is generated by the \"settings\"-menu in your CMS.\n";
            $strHeader .= "* Do not try to edit it manually!\n";
            $strHeader .= "*/\n\n";

            $strFooter .= "?>";

            //Get module-names
            $objResult = $objDatabase->Execute('SELECT    id, name FROM '.DBPREFIX.'modules');
            if ($objResult->RecordCount() > 0) {
                while (!$objResult->EOF) {
                    $arrModules[$objResult->fields['id']] = $objResult->fields['name'];
                    $objResult->MoveNext();
                }
            }

            //Get values
            $objResult = $objDatabase->Execute('SELECT        setname,
                                                            setmodule,
                                                            setvalue
                                                FROM        '.DBPREFIX.'settings
                                                ORDER BY    setmodule ASC,
                                                            setname ASC
                                            ');
            $intMaxLen = 0;
            if ($objResult->RecordCount() > 0) {
                while (!$objResult->EOF) {
                    $intMaxLen = (strlen($objResult->fields['setname']) > $intMaxLen) ? strlen($objResult->fields['setname']) : $intMaxLen;
                    $arrValues[$objResult->fields['setmodule']][$objResult->fields['setname']] = $objResult->fields['setvalue'];
                    $objResult->MoveNext();
                }
            }
            $intMaxLen += strlen('$_CONFIG[\'\']') + 1; //needed for formatted output

            //Write values
            flock($handleFile, LOCK_EX); //set semaphore
            @fwrite($handleFile,$strHeader);

            foreach ($arrValues as $intModule => $arrInner) {
                @fwrite($handleFile,"/**\n");
                @fwrite($handleFile,"* -------------------------------------------------------------------------\n");
                @fwrite($handleFile,"* ".ucfirst($arrModules[$intModule])."\n");
                @fwrite($handleFile,"* -------------------------------------------------------------------------\n");
                @fwrite($handleFile,"*/\n");

                foreach($arrInner as $strName => $strValue) {
                    @fwrite($handleFile,sprintf("%-".$intMaxLen."s",'$_CONFIG[\''.$strName.'\']'));
                    @fwrite($handleFile,"= ");
                    @fwrite($handleFile,(is_numeric($strValue) ? $strValue : '"'.$strValue.'"').";\n");
                }
                @fwrite($handleFile,"\n");
            }

            @fwrite($handleFile,$strFooter);
            flock($handleFile, LOCK_UN);

            fclose($handleFile);
        }
    } else {
        setUpdateMsg(sprintf($_ARRAYLANG['TXT_UNABLE_WRITE_SETTINGS_FILE'], ASCMS_DOCUMENT_ROOT.'/config/settings.php'));
        setUpdateMsg(sprintf($_ARRAYLANG['TXT_SET_WRITE_PERMISSON_TO_FILE'], ASCMS_DOCUMENT_ROOT.'/config/settings.php', $_CORELANG['TXT_UPDATE_TRY_AGAIN']), 'msg');
        return false;
    }

    $query = "
    ALTER TABLE ".DBPREFIX."settings
    CHANGE COLUMN setid setid integer(6) UNSIGNED NOT NULL auto_increment;
    ";
    if (!$objDatabase->Execute($query)) {
        return _databaseError($query, $objDatabase->ErrorMsg());
    }

    return true;
}

function _updateSettingsTable($setId, $arrSetting)
{
    global $objDatabase, $arrSettings, $arrSettingsByName, $arrCurrentSettingsTable;

    if (!isset($arrCurrentSettingsTable)) {
        $arrCurrentSettingsTable = array();
    }

    $query = "SELECT setid FROM `".DBPREFIX."settings` WHERE `setname`='".$arrSetting['setname']."'";
    // select stored ID of option
    if (($objSettings = $objDatabase->SelectLimit($query, 1)) !== false) {
        if ($objSettings->RecordCount() == 0) {
            // option isn't yet present => ok, check if the associated ID isn't already used
            $query = "SELECT `setname` FROM `".DBPREFIX."settings` WHERE `setid` = ".$setId;
            if (($objSettings = $objDatabase->SelectLimit($query, 1)) !== false) {
                if ($objSettings->RecordCount() == 0) {
                    // option ID isn't already in use => ok, add it
                    $query = "INSERT INTO `".DBPREFIX."settings` ( `setid` , `setname` , `setvalue` , `setmodule` ) VALUES (".$setId.", '".$arrSetting['setname']."', '".$arrSetting['setvalue']."', '".$arrSetting['setmodule']."')";
                    if ($objDatabase->Execute($query) !== false) {
                        return true;
                    } else {
                        return _databaseError($query, $objDatabase->ErrorMsg());
                    }
                } else {
                    // option ID is already in use => update the option who uses the wrong ID to it's right ID
                    $setname = $objSettings->fields['setname'];
                    if (in_array($setname, $arrCurrentSettingsTable)) {
                        // set a free ID which could be used as a temporary ID
                        $query = "SELECT MAX(`setid`) AS lastInsertId FROM `".DBPREFIX."settings`";
                        if (($objSettings = $objDatabase->SelectLimit($query, 1)) !== false) {
                            $query = "UPDATE `".DBPREFIX."settings` SET `setid` = ".($objSettings->fields['lastInsertId']+1)." WHERE `setid` = ".$setId;
                            // associated a temportary ID to the option who uses the wrong ID
                            if ($objDatabase->Execute($query) !== false) {
                                unset($arrCurrentSettingsTable[$setname]);
                                if (_updateSettingsTable($setId, $arrSetting)) {
                                    return true;
                                } else {
                                    return false;
                                }
                            } else {
                                return _databaseError($query, $objDatabase->ErrorMsg());
                            }
                        } else {
                            return _databaseError($query, $objDatabase->ErrorMsg());
                        }
                    } else {
                        $arrCurrentSettingsTable[] = $setname;
                        if (_updateSettingsTable($arrSettingsByName[$setname], $arrSettings[$arrSettingsByName[$setname]]) && _updateSettingsTable($setId, $arrSetting)) {
                            return true;
                        } else {
                            return false;
                        }
                    }
                }
            } else {
                return _databaseError($query, $objDatabase->ErrorMsg());
            }
        } elseif ($objSettings->fields['setid'] != $setId) {
            $currentSetId = $objSettings->fields['setid'];
            // option is already present but uses a wrong ID => check if the right associated ID of the option is already used by an other option
            $query = "SELECT `setname` FROM `".DBPREFIX."settings` WHERE `setid` = ".$setId;
            if (($objSettings = $objDatabase->SelectLimit($query, 1)) !== false) {
                if ($objSettings->RecordCount() == 0) {
                    // ID isn't already used => ok, set the correct ID of the option
                    $query = "UPDATE `".DBPREFIX."settings` SET `setid` = ".$setId." WHERE `setid` = ".$currentSetId;
                    if ($objDatabase->Execute($query) !== false) {
                        return true;
                    } else {
                        return _databaseError($query, $objDatabase->ErrorMsg());
                    }
                } else {
                    // option ID is already in use => update the option who uses the wrong ID to it's right ID
                    $setname = $objSettings->fields['setname'];
                    if (in_array($setname, $arrCurrentSettingsTable)) {
                        // set a free ID which could be used as a temporary ID
                        $query = "SELECT MAX(`setid`) AS lastInsertId FROM `".DBPREFIX."settings`";
                        if (($objSettings = $objDatabase->SelectLimit($query, 1)) !== false) {
                            $query = "UPDATE `".DBPREFIX."settings` SET `setid` = ".($objSettings->fields['lastInsertId']+1)." WHERE `setid` = ".$setId;
                            // associated a temportary ID to the option who uses the wrong ID
                            if ($objDatabase->Execute($query) !== false) {
                                unset($arrCurrentSettingsTable[$setname]);
                                if (_updateSettingsTable($setId, $arrSetting)) {
                                    return true;
                                } else {
                                    return false;
                                }
                            } else {
                                return _databaseError($query, $objDatabase->ErrorMsg());
                            }
                        } else {
                            return _databaseError($query, $objDatabase->ErrorMsg());
                        }
                    } else {
                        $arrCurrentSettingsTable[] = $setname;
                        if (_updateSettingsTable($arrSettingsByName[$setname], $arrSettings[$arrSettingsByName[$setname]]) && _updateSettingsTable($setId, $arrSetting)) {
                            return true;
                        } else {
                            return false;
                        }
                    }
                }
            } else {
                return _databaseError($query, $objDatabase->ErrorMsg());
            }
        } else {
            return true;
        }
    } else {
        return _databaseError($query, $objDatabase->ErrorMsg());
    }
}
?>
