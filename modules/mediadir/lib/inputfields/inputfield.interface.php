<?php
interface inputfield  {
    function getInputfield($intView, $arrInputfield, $intEntryId=null);
    function saveInputfield($intInputfieldId, $strValue);
    function deleteContent($intEntryId, $intIputfieldId);
    function getContent($intEntryId, $arrInputfield);
    function getJavascriptCheck();
}
?>