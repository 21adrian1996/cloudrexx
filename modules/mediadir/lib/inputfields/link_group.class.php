<?php
/**
 * Media  Directory Inputfield Text Class
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  module_mediadir
 * @todo        Edit PHP DocBlocks!
 */

/**
 * Includes
 */
require_once ASCMS_MODULE_PATH . '/mediadir/lib/inputfields/inputfield.interface.php';

class mediaDirectoryInputfieldLink_group implements inputfield
{
    public $arrPlaceholders = array('TXT_MEDIADIR_INPUTFIELD_NAME','MEDIADIR_INPUTFIELD_VALUE');



    /**
     * Constructor
     */
    function __construct()
    {
    }



    function getInputfield($intView, $arrInputfield, $intEntryId=null)
    {
        global $objDatabase, $_LANGID, $objInit;

        switch ($intView) {
            default:
            case 1:
                //modify (add/edit) View
                $intId = intval($arrInputfield['id']);

                if(isset($intEntryId) && $intEntryId != 0) {
                    $objInputfieldValue = $objDatabase->Execute("
                        SELECT
                            `value`
                        FROM
                            ".DBPREFIX."module_mediadir_rel_entry_inputfields
                        WHERE
                            field_id=".$intId."
                        AND
                            entry_id=".$intEntryId."
                        LIMIT 1
                    ");
                    $strValue = htmlspecialchars($objInputfieldValue->fields['value'], ENT_QUOTES, CONTREXX_CHARSET);
                } else {
                    $strValue = null;
                }

                if(empty($strValue)) {
                    $strValue = empty($arrInputfield['default_value'][$_LANGID]) ? $arrInputfield['default_value'][0] : $arrInputfield['default_value'][$_LANGID];
                }

                if($objInit->mode == 'backend') {
                    $strInputfield = '<textarea name="mediadirInputfield['.$intId.']" id="mediadirInputfield_'.$intId.'" style="width: 300px; height: 60px;" onfocus="this.select();" />'.$strValue.'</textarea>';
                } else {
                     $strInputfield = '<textarea name="mediadirInputfield['.$intId.']" id="mediadirInputfield_'.$intId.'" class="mediadirInputfieldLink_group" onfocus="this.select();" />'.$strValue.'</textarea>';
                }

                return $strInputfield;

                break;
            case 2:
                //search View
                break;
        }
    }



    function saveInputfield($intInputfieldId, $strValue)
    {
        $strValue = contrexx_addslashes(contrexx_strip_tags($strValue));
        return $strValue;
    }


    function deleteContent($intEntryId, $intIputfieldId)
    {
        global $objDatabase;

        $objDeleteInputfield = $objDatabase->Execute("DELETE FROM ".DBPREFIX."module_mediadir_rel_entry_inputfields WHERE `entry_id`='".intval($intEntryId)."' AND  `field_id`='".intval($intIputfieldId)."'");

        if($objDeleteEntry !== false) {
            return true;
        } else {
            return false;
        }
    }



    function getContent($intEntryId, $arrInputfield)
    {
        global $objDatabase;

        $intId = intval($arrInputfield['id']);
        $objInputfieldValue = $objDatabase->Execute("
            SELECT
                `value`
            FROM
                ".DBPREFIX."module_mediadir_rel_entry_inputfields
            WHERE
                field_id=".$intId."
            AND
                entry_id=".$intEntryId."
            LIMIT 1
        ");

        $strValue = strip_tags(htmlspecialchars($objInputfieldValue->fields['value'], ENT_QUOTES, CONTREXX_CHARSET));

        //get seperator
        $strSeperator = $this->getSeperartor($strValue);

        //explode links
        $arrLinkGroup = explode($strSeperator, $strValue);

        //open link <ul> list
        $strValue = '<ul class="mediadirInputfieldLink_group">';

        //make list elements
        foreach ($arrLinkGroup as $intKey => $strLink) {

            //make link name without "http://"
            $strValueName = $strLink;
            if (substr($strValueName, 0,7) == "http://") {
                $strValueName = substr($strValueName,7);
            }

            if (strlen($strValueName) >= 55 ) {
                $strValueName = substr($strValueName, 0, 55)." [...]";
            }

            //make link href with "http://"
            $strValueHref = $strLink;
            if (substr($strValueHref, 0,7) != "http://") {
                $strValueHref = "http://".$strValueHref;
            }

            //make hyperlink with <a> and <li> tag
            $strValue .= '<li><a href="'.$strValueHref.'" target="_blank">'.$strValueName.'</a></li>';
        }

        //close link </ul> list
        $strValue .= '</ul>';

        if(!empty($strValue)) {
            $arrContent['TXT_MEDIADIR_INPUTFIELD_NAME'] = htmlspecialchars($arrInputfield['name'][0], ENT_QUOTES, CONTREXX_CHARSET);
            $arrContent['MEDIADIR_INPUTFIELD_VALUE'] = $strValue;
        } else {
            $arrContent = null;
        }

        return $arrContent;
    }



    function getSeperartor($strValue)
    {
        $arrAllowedSeperators = array("," => 0,";" => 0,"\n" => 0," " => 0);

        foreach (array_keys($arrAllowedSeperators) as $strSeperator) {
            $intMatches = substr_count($strValue, $strSeperator);
            $arrSeperators[$intMatches] = $strSeperator;
        }

        ksort($arrSeperators);

        $strSeperator = array_pop($arrSeperators);

        return $strSeperator;
    }



    function getJavascriptCheck()
    {
        $strJavascriptCheck = <<<EOF

            case 'link_group':
                value = document.getElementById('mediadirInputfield_' + field).value;
                if (value == "" && isRequiredGlobal(inputFields[field][1], value)) {
                	isOk = false;
                	document.getElementById('mediadirInputfield_' + field).style.border = "#ff0000 1px solid";
                } else if (value != "" && !matchType(inputFields[field][2], value)) {
                	isOk = false;
                	document.getElementById('mediadirInputfield_' + field).style.border = "#ff0000 1px solid";
                } else {
                	document.getElementById('mediadirInputfield_' + field).style.borderColor = '';
                }
                break;

EOF;
        return $strJavascriptCheck;
    }
}