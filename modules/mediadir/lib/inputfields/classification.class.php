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
require_once ASCMS_MODULE_PATH . '/mediadir/lib/lib.class.php';

class mediaDirectoryInputfieldClassification extends mediaDirectoryLibrary implements inputfield
{
    public $arrPlaceholders = array('TXT_MEDIADIR_INPUTFIELD_NAME','MEDIADIR_INPUTFIELD_VALUE');


    /**
     * Constructor
     */
    function __construct()
    {
        parent::getSettings();
    }



    function getInputfield($intView, $arrInputfield, $intEntryId=null)
    {
        global $objDatabase, $_LANGID, $objInit, $_ARRAYLANG;

        $intId = intval($arrInputfield['id']);

        switch ($intView) {
            default:
            case 1:
                //modify (add/edit) View
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
                    $strInputfield = '<select name="mediadirInputfield['.$intId.']" id="mediadirInputfield_'.$intId.'" class="mediadirInputfieldDropdown" style="width: 302px">';

                    for ($i=1;$i<=$this->arrSettings['settingsClassificationPoints'];$i++){
                        if($strValue == $i) {
                            $strChecked = 'selected="selected"';
                        } else {
                            $strChecked = '';
                        }

                        $strInputfield .= '<option  value="'.$i.'" '.$strChecked.'>'.$i.'</option>';
                    }

                    $strInputfield .= '</select>';
                } else {
                    $strInputfield = '<select name="mediadirInputfield['.$intId.']" id="mediadirInputfield_'.$intId.'" class="mediadirInputfieldDropdown">';

                    for ($i=1;$i<=$this->arrSettings['settingsClassificationPoints'];$i++){
                        if($strValue == $i) {
                            $strChecked = 'selected="selected"';
                        } else {
                            $strChecked = '';
                        }

                        $strInputfield .= '<option  value="'.$i.'" '.$strChecked.'>'.$i.'</option>';
                    }

                    $strInputfield .= '</select>';
                }

                return $strInputfield;

                break;
            case 2:
                //search View
                $strValue = $_GET[$intId];
                $strImagePath = ASCMS_MODULE_IMAGE_WEB_PATH.'/mediadir/';
                $intNumPoints = $this->arrSettings['settingsClassificationPoints'];

                $strInputfield = <<<EOF
<script language="JavaScript" type="text/javascript">
/* <![CDATA[ */
function classification_$intId(num) {
    var intFieldId = $intId;
    var strImagePath = '$strImagePath';
    var intNumPoints = $intNumPoints;
    var elmInput = document.getElementById('mediadirClassification_' + intFieldId);
    var intActualVaule = elmInput.value;

    for (i=1;i<=intNumPoints;i++) {
        if(i <= num && intActualVaule != num) {
            var strImage = strImagePath + 'classification_on.png';
        } else {
            var strImage = strImagePath + 'classification_off.png';
        }

        var elmImage = document.getElementById('mediadirClassificationImage_' + intFieldId + '_' + i);
        elmImage.src = strImage;
    }

    if(intActualVaule != num) {
        elmInput.value = num;
    } else {
        elmInput.value = '';
    }
}
/* ]]> */
</script>
EOF;

                for ($i=1;$i<=$intNumPoints;$i++){
                    if($i <= $strValue) {
                        $strImage = 'classification_on.png';
                    } else {
                        $strImage = 'classification_off.png';
                    }

                    $strInputfield .= '<img id="mediadirClassificationImage_'.$intId.'_'.$i.'" src="'.$strImagePath.$strImage.'" title="'.$arrInputfield['name'][0].' - '.$intValue.'" alt="'.$arrInputfield['name'][0].' - '.$intValue.'" style="cursor: pointer;" onclick="classification_'.$intId.'('.$i.');" />';
                }


                $strInputfield .= '<input id="mediadirClassification_'.$intId.'" type="hidden" name="'.$intId.'" " class="mediadirInputfieldSearch" value="'.$strValue.'" />';

                return $strInputfield;
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

        $intValue = intval($objInputfieldValue->fields['value']);


        for ($i=1;$i<=$this->arrSettings['settingsClassificationPoints'];$i++){
            if($i <= $intValue) {
                $strImage = 'classification_on.png';
            } else {
                $strImage = 'classification_off.png';
            }

            $strValue .= '<img src="'.ASCMS_MODULE_IMAGE_WEB_PATH.'/mediadir/'.$strImage.'" title="'.$arrInputfield['name'][0].' - '.$intValue.'" alt="'.$arrInputfield['name'][0].' - '.$intValue.'" />';
        }

        if(!empty($strValue)) {
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

            case 'classification':
                break;

EOF;
        return $strJavascriptCheck;
    }
}