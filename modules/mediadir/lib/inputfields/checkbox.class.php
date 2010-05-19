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

class mediaDirectoryInputfieldCheckbox implements inputfield
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
                    if(!empty($objInputfieldValue->fields['value'])) {
                        $arrValue = explode(",",$objInputfieldValue->fields['value']);
                    } else {
                        $arrValue = null;
                    }
                } else {
                    $arrValue = null;
                }

                $strOptions = empty($arrInputfield['default_value'][$_LANGID]) ? $arrInputfield['default_value'][0] : $arrInputfield['default_value'][$_LANGID];
                $arrOptions = explode(",", $strOptions);

                if($objInit->mode == 'backend') {
                    $strInputfield = '<span id="mediadirInputfield_'.$intId.'_list" style="display: block;">';
                    foreach($arrOptions as $intKey => $strDefaultValue) {
                        $intKey++;
                        if(in_array($intKey, $arrValue)) {
                            $strChecked = 'checked="checked"';
                        } else {
                            $strChecked = '';
                        }

                        $strInputfield .= '<input type="checkbox" name="mediadirInputfield['.$intId.'][]" id="mediadirInputfield_'.$intId.'_'.$intKey.'" value="'.$intKey.'" '.$strChecked.' />&nbsp;'.$strDefaultValue.'<br />';
                    }

                    $strInputfield .= '</span>';
                } else {
                    $strInputfield = '<span id="mediadirInputfield_'.$intId.'_list" style="display: block;">';

                    foreach($arrOptions as $intKey => $strDefaultValue) {
                        $intKey++;
                        if(in_array($intKey, $arrValue)) {
                            $strChecked = 'checked="checked"';
                        } else {
                            $strChecked = '';
                        }

                        $strInputfield .= '<input class="mediadirInputfieldRadio" type="checkbox" name="mediadirInputfield['.$intId.'][]" id="mediadirInputfield_'.$intId.'_'.$intKey.'" value="'.$intKey.'" '.$strChecked.' />&nbsp;'.$strDefaultValue.'<br />';
                    }


                    $strInputfield .= '</span>';
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
        $arrValue = $strValue;

        foreach($arrValue as $intKey => $strValue) {
            $arrValue[$intKey] = $strValue = contrexx_addslashes(contrexx_strip_tags($strValue));
        }

        $strValue = join(",",$arrValue);

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


        $arrValues = explode(",", $arrInputfield['default_value'][0]);
        $strValue = strip_tags(htmlspecialchars($objInputfieldValue->fields['value'], ENT_QUOTES, CONTREXX_CHARSET));

        //explode elements
        $arrElements = explode(",", $strValue);

        //open <ul> list
        $strValue = '<ul class="mediadirInputfieldCheckbox">';

        //make element list
        foreach ($arrElements as $intKey => $strElement) {
            $strElement = $strElement-1;
            $strValue .= '<li>'.$arrValues[$strElement].'</li>';
        }

        //close </ul> list
        $strValue .= '</ul>';

        if($arrElements[0] != null) {
            $arrContent['TXT_MEDIADIR_INPUTFIELD_NAME'] = htmlspecialchars($arrInputfield['name'][0], ENT_QUOTES, CONTREXX_CHARSET);
            $arrContent['MEDIADIR_INPUTFIELD_VALUE'] = $strValue;
        } else {
            $arrContent = null;
        }

        return $arrContent;
    }


    function getJavascriptCheck()
    {
        $strJavascriptCheck = <<<EOF

            case 'checkbox':
                if (isRequiredGlobal(inputFields[field][1], value)) {
                    var boxes = document.getElementsByName('mediadirInputfield[' + field + '][]');
                    var checked = false;

                    for (var i = 0; i < boxes.length; i++) {
                        if (boxes[i].checked) {
                            checked = true;
                        }
                    }

                    if (!checked) {
                        document.getElementById('mediadirInputfield_' + field + '_list').style.border = "#ff0000 1px solid";
                        isOk = false;
                    } else {
                        document.getElementById('mediadirInputfield_' + field + '_list').style.border = "#ff0000 0px solid";
                    }
                }
                break;

EOF;
        return $strJavascriptCheck;
    }
}