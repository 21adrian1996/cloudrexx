<?php

/**
 * Framework language
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team <info@comvation.com>
 * @version     2.2.0
 * @package     contrexx
 * @subpackage  lib_framework
 * @todo        Edit PHP DocBlocks!
 */

/**
 * Framework language
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team <info@comvation.com>
 * @version     2.2.0
 * @package     contrexx
 * @subpackage  lib_framework
 */
class FWLanguage
{
    private static $arrLanguages = false;

    /**
     * Loads the language config from database.
     *
     * This used to be in __construct but is also
     * called from core/language.class.php to reload
     * the config, so core/settings.class.php can
     * rewrite .htaccess (virtual lang dirs).
     */
    static function init()
    {
        global $objDatabase;

        $objResult = $objDatabase->Execute("
            SELECT id, lang, name, charset, themesid,
                   frontend, backend, is_default
              FROM ".DBPREFIX."languages
             ORDER BY id ASC
        ");
        if ($objResult) {
            while (!$objResult->EOF) {
                self::$arrLanguages[$objResult->fields['id']] = array(
                    'id'         => $objResult->fields['id'],
                    'lang'       => $objResult->fields['lang'],
                    'name'       => $objResult->fields['name'],
                    'charset'    => $objResult->fields['charset'],
                    'themesid'   => $objResult->fields['themesid'],
                    'frontend'   => $objResult->fields['frontend'],
                    'backend'    => $objResult->fields['backend'],
                    'is_default' => $objResult->fields['is_default'],
                );
                $objResult->MoveNext();
            }
        }
    }


    /**
     * Returns the complete language data
     * @see     FWLanguage()
     * @return  array           The language data
     * @access  public
     */
    static function getLanguageArray()
    {
        if (empty(self::$arrLanguages)) self::init();
        return self::$arrLanguages;
    }


    /**
     * Returns single language related fields
     *
     * Access language data by specifying the language ID and the index
     * as initialized by {@link FWLanguage()}.
     * @return  mixed           Language data field content
     * @access  public
     */
    static function getLanguageParameter($id, $index)
    {
        if (empty(self::$arrLanguages)) self::init();
        return
            (isset(self::$arrLanguages[$id][$index])
                ? self::$arrLanguages[$id][$index] : false
            );
    }


    /**
     * Returns HTML code to display a language selection dropdown menu.
     *
     * Does only contain the <select> tag pair if the optional $menuName
     * is specified and evaluates to a true value.
     * @param   integer $selectedId The optional preselected language ID
     * @param   string  $menuName   The optional menu name
     * @param   string  $onchange   The optional onchange code
     * @return  string              The dropdown menu HTML code
     * @author  Reto Kohli <reto.kohli@comvation.com>
     * @todo    Use Html class instead
     */
    static function getMenu($selectedId=0, $menuName='', $onchange='')
    {
        $menu = self::getMenuoptions($selectedId, true);
        if ($menuName) {
            $menu = "<select id='$menuName' name='$menuName'".
                    ($onchange ? ' onchange="'.$onchange.'"' : '').
                    ">\n$menu</select>\n";
        }
        return $menu;
    }


    /**
     * Returns HTML code to display a language selection dropdown menu
     * for the active frontend languages only.
     *
     * Does only contain the <select> tag pair if the optional $menuName
     * is specified and evaluates to a true value.
     * Frontend use only.
     * @param   integer $selectedId The optional preselected language ID
     * @param   string  $menuName   The optional menu name
     * @param   string  $onchange   The optional onchange code
     * @return  string              The dropdown menu HTML code
     * @author  Reto Kohli <reto.kohli@comvation.com>
     * @todo    Use Html class instead
     */
    static function getMenuActiveOnly($selectedId=0, $menuName='', $onchange='')
    {
        $menu = self::getMenuoptions($selectedId, false);
        if ($menuName) {
            $menu = "<select id='$menuName' name='$menuName'".
                    ($onchange ? ' onchange="'.$onchange.'"' : '').
                    ">\n$menu</select>\n";
        }
//echo("getMenu(select=$selectedId, name=$menuName, onchange=$onchange): made menu: ".htmlentities($menu)."<br />");
        return $menu;
    }


    /**
     * Returns HTML code for the language menu options
     * @param   integer $selectedId   The optional preselected language ID
     * @param   boolean $flagInactive If true, all languages are added,
     *                                only the active ones otherwise
     * @return  string                The menu options HTML code
     * @author  Reto Kohli <reto.kohli@comvation.com>
     * @todo    Use Html class instead
     */
    static function getMenuoptions($selectedId=0, $flagInactive=false)
    {
        if (empty(self::$arrLanguages)) self::init();
        $menuoptions = '';
        foreach (self::$arrLanguages as $id => $arrLanguage) {
            // Skip inactive ones if desired
            if (!$flagInactive && empty($arrLanguage['frontend']))
                continue;
            $menuoptions .=
                "<option value='$id'".
                ($selectedId == $id ? ' selected="selected"' : '').
                ">{$arrLanguage['name']}</option>\n";
        }
        return $menuoptions;
    }


    /**
     * Return the language ID for the ISO 639-1 code specified.
     *
     * If the code cannot be found, returns the default language.
     * If that isn't set either, returns the first language encountered.
     * If none can be found, returns boolean false.
     * Note that you can supply the complete string from the Accept-Language
     * HTTP header.  This method will take care of chopping it into pieces
     * and trying to pick a suitable language.
     * However, it will not pick the most suitable one according to RFC2616,
     * but only returns the first language that fits.
     * @static
     * @param   string    $langCode         The ISO 639-1 language code
     * @return  mixed                       The language ID on success,
     *                                      false otherwise
     * @global  ADONewConnection
     * @author  Reto Kohli <reto.kohli@comvation.com>
     */
    static function getLangIdByIso639_1($langCode)
    {
        global $objDatabase;

        // Something like "fr; q=1.0, en-gb; q=0.5"
        $arrLangCode = preg_split('/,\s*/', $langCode);
        $strLangCode = "'".join("', '", preg_replace('/(?:-\w+)?(?:;\s*q(?:\=\d?\.?\d*)?)?/i', '', $arrLangCode))."'";

        $objResult = $objDatabase->Execute("
            SELECT id
              FROM ".DBPREFIX."languages
             WHERE lang IN ($strLangCode)
               AND frontend=1
        ");
        if ($objResult && $objResult->RecordCount() > 0) {
            return $objResult->fields['id'];
        }
        // The code was not found.  Pick the default.
        $objResult = $objDatabase->Execute("
            SELECT id
              FROM ".DBPREFIX."languages
             WHERE is_default='true'
               AND frontend=1
        ");
        if ($objResult && $objResult->RecordCount() > 0) {
            return $objResult->fields['id'];
        }
        // Still nothing.  Pick the first frontend language available.
        $objResult = $objDatabase->Execute("
            SELECT id
              FROM ".DBPREFIX."languages
             WHERE frontend=1
        ");
        if ($objResult && $objResult->RecordCount() > 0) {
            return $objResult->fields['id'];
        }
        // Pick the first language.
        $objResult = $objDatabase->Execute("
            SELECT id
              FROM ".DBPREFIX."languages
             WHERE frontend=1
        ");
        if ($objResult && $objResult->RecordCount() > 0) {
            return $objResult->fields['id'];
        }
        // Give up.
        return false;
    }


    /**
     * Return the language code from the database for the given ID
     *
     * Returns false on failure, or false if the code could not be found.
     * @global  ADONewConnection
     * @param   integer $langId         The language ID
     * @return  mixed                   The two letter code, or false
     * @static
     */
    static function getLanguageCodeById($langId)
    {
        if (empty(self::$arrLanguages)) self::init();
        return self::getLanguageParameter($langId, 'lang');
    }

}

?>
