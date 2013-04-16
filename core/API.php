<?php

/**
 * Global file including
 *
 * Global file to include the required files
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  core
 * @version     1.0.0
 * @uses /core/validator.inc.php
 * @uses /lib/PEAR/HTML/Template/Sigma/Sigma.php
 * @uses /core/database.php
 * @uses /lib/PEAR/HTML/Table.php
 * @uses /core/adminNavigation.class.php
 * @uses /core/Navigation.class.php
 * @uses /core_modules/stats/lib/statsLib.class.php
 * @uses /core/Security.class.php
 * @uses /lib/wrapper/json.php
 * @todo Add comment for all require_once()s
 * @todo THIS FILE SHOULD BE MANDATORY!
 */

if (stristr(__FILE__, $_SERVER['PHP_SELF'])) {
    Header("Location: index.php");
    die();
}

/**
 * @ignore
 */
\Env::get('ClassLoader')->loadFile(ASCMS_CORE_PATH.'/validator.inc.php');
/**
 * @ignore
 */
\Env::get('ClassLoader')->loadFile(ASCMS_LIBRARY_PATH.'/PEAR/HTML/Template/Sigma/Sigma.php');

/**
 * @ignore
 * @todo    Is this still required?
 */
\Env::get('ClassLoader')->loadFile(ASCMS_CORE_PATH.'/database.php');

/**
 * @ignore
 */
#ASCMS_CORE_PATH.'/Modulechecker.class.php';

global $adminPage;
if (isset($adminPage) && $adminPage ) {
    /**
    * @ignore
    */
    \Env::get('ClassLoader')->loadFile(ASCMS_LIBRARY_PATH.'/PEAR/HTML/Table.php');
    /**
     * @ignore
     */
    \Env::get('ClassLoader')->loadFile(ASCMS_CORE_PATH.'/adminNavigation.class.php');
} else {
    /**
     * @ignore
     */
    \Env::get('ClassLoader')->loadFile(ASCMS_CORE_PATH.'/Navigation.class.php');
    /**
     * @ignore
     */
    \Env::get('ClassLoader')->loadFile(ASCMS_CORE_MODULE_PATH.'/stats/lib/statsLib.class.php');
    /**
     * @ignore
     */
    \Env::get('ClassLoader')->loadFile(ASCMS_CORE_PATH.'/Security.class.php');
}

//wrappers providing php functions via PEAR and other third party libraries if they're not found.
\Env::get('ClassLoader')->loadFile(ASCMS_LIBRARY_PATH.'/wrapper/json.php');

/**
 * Checks if a certain module, specified by param $moduleName, is a core module.
 *
 * @param   string  $moduleName
 * @return  boolean
 */
function contrexx_isCoreModule($moduleName)
{
    static $objModuleChecker = NULL;

    if (!isset($objModuleChecker)) {
        $objModuleChecker = new \Cx\Core\ModuleChecker(\Env::get('em'), \Env::get('db'), \Env::get('ClassLoader'));
    }

    return $objModuleChecker->isCoreModule($moduleName);
}

/**
 * Checks if a certain module, specified by param $moduleName, is active.
 *
 * @param   string  $moduleName
 * @return  boolean
 */
function contrexx_isModuleActive($moduleName)
{
    static $objModuleChecker = NULL;

    if (!isset($objModuleChecker)) {
        $objModuleChecker = new \Cx\Core\ModuleChecker(\Env::get('em'), \Env::get('db'), \Env::get('ClassLoader'));
    }

    return $objModuleChecker->isModuleActive($moduleName);
}

/**
 * Checks if a certain module, specified by param $moduleName, is installed.
 *
 * @param   string  $moduleName
 * @return  boolean
 */
function contrexx_isModuleInstalled($moduleName)
{
    static $objModuleChecker = NULL;

    if (!isset($objModuleChecker)) {
        $objModuleChecker = new \Cx\Core\ModuleChecker(\Env::get('em'), \Env::get('db'), \Env::get('ClassLoader'));
    }

    return $objModuleChecker->isModuleInstalled($moduleName);
}


/**
 * OBSOLETE
 * Use the {@see Paging::get()} method instead.
 *
 * Returs a string representing the complete paging HTML code for the
 * current page.
 * Note that the old $pos parameter is obsolete as well,
 * see {@see getPosition()}.
 * @copyright CONTREXX CMS - COMVATION AG
 * @author    Comvation Development Team <info@comvation.com>
 * @access    public
 * @version   1.0.0
 * @global    array       $_CONFIG        Configuration
 * @global    array       $_CORELANG      Core language
 * @param     int         $numof_rows     The number of rows available
 * @param     int         $pos            The offset from the first row
 * @param     string      $uri_parameter
 * @param     string      $paging_text
 * @param     boolean     $showeverytime
 * @param     int         $results_per_page
 * @return    string      Result
 * @todo      Change the system to use the new, static class method,
 *            then remove this one.
 */
function getPaging($numof_rows, $pos, $uri_parameter, $paging_text,
    $showeverytime=false, $results_per_page=null
) {
    return Paging::get($uri_parameter, $paging_text, $numof_rows,
        $results_per_page, $showeverytime, $pos, 'pos');
}

/**
 * Builds a (partially localized) date string from the optional timestamp.
 *
 * If no timestamp is supplied, the current date is used.
 * The returned date has the form "Weekday, Day. Month Year".
 * @param   int     $unixtimestamp  Unix timestamp
 * @return  string                  Formatted date
 * @todo    The function is inappropriately named "showFormattedDate"
 *          as the date is returned, and not "shown" in any way.
 * @todo    The formatting is not localized.
 *          Use a date format constant and/or language variable template.
 */
function showFormattedDate($unixtimestamp='')
{
    global $_CORELANG;
    $months = explode(",",$_CORELANG['TXT_MONTH_ARRAY']);
    $weekday = explode(",",$_CORELANG['TXT_DAY_ARRAY']);

    if (empty($unixtimestamp)) {
        $date = date("w j n Y");
    } else {
        $date = date("w j n Y", $unixtimestamp);
    }
    list ($wday, $mday, $month, $year) = explode(' ', $date);
    $month -= 1;
    $formattedDate = "$weekday[$wday], $mday. $months[$month] $year";
    return $formattedDate;
}


/**
 * Cleans strings from illegal characters.
 *
 * Searches the string for known special (non-ASCII) characters and
 * replaces them with ASCII representations.  Removes any other
 * non-ASCII characters left.
 * @author  Ivan Schmid <ivan.schmid@comvation.com>
 * @param   string  $string     Raw string
 * @return  string              Cleaned string
 * @todo    The function is inappropriately named "strcheck",
 *          although the string isn't just "checked", but also "cleaned" or
 *          "fixed".
 * @todo    Replace the non-ASCII characters by their octal (\xxx)
 *          representation!
 */
function strcheck(&$string)
{
// TODO: Like this, but make it consider CONTREXX_CHARSET:
/*
    static $arrReplace = array(
        ' ' => '_',
        '$' => 'S',
        "\303\240" => 'a',
        "\303\241" => 'a',
        "\303\242" => 'a',
        "\303\243" => 'a',
        "\303\245" => 'a',
        "\303\247" => 'c',
        "\303\250" => 'e',
        "\303\251" => 'e',
        "\303\252" => 'e',
        "\303\253" => 'e',
        "\303\254" => 'i',
        "\303\255" => 'i',
        "\303\256" => 'i',
        "\303\257" => 'i',
        "\303\261" => 'n',
        "\303\262" => 'o',
        "\303\263" => 'o',
        "\303\264" => 'o',
        "\303\265" => 'o',
        "\303\270" => 'o',
        "\305\241" => 's',
        "\303\271" => 'u',
        "\303\272" => 'u',
        "\303\273" => 'u',
        "\302\265" => 'u',
        "\303\275" => 'y',
        "\303\277" => 'y',
        "\302\245" => 'Y',
        "\305\276" => 'z',
        "\303\244" => 'ae',
        "\303\206" => 'ae',
        "\303\246" => 'ae',
        "\303\266" => 'oe',
        "\305\222" => 'oe',
        "\305\223" => 'oe',
        "\303\274" => 'ue',
        "\303\220" => 'dh',
        "\303\260" => 'dh',
        "\303\236" => 'th',
        "\303\276" => 'th',
        "\303\237" => 'ss',
    );

    $clean_string = strtolower($string);
    $clean_string = rawurldecode($clean_string);
    $clean_string = html_entity_decode($clean_string);
    $clean_string = str_replace(
        array_keys($arrReplace), $arrReplace,
        $clean_string);
    $clean_string = preg_replace('/[^a-z0-9_\.]/i', '', $clean_string);
*/
    $clean_string = strtolower($string);
    $clean_string = rawurldecode($clean_string);
    $clean_string = html_entity_decode($clean_string);

    $from = 'àáâãäåçèéêëìíîïñòóôõöøùúûüµýÿ¥ ';
    $to   = 'aaaaaaceeeeiiiinoooooosuuuuuyyyz_';
    $clean_string = strtr($clean_string, $from, $to);

    $replace = array('Þ' => 'th', 'þ' => 'th', 'Ð' => 'dh', 'ð' => 'dh',
                    'ß' => 'ss', '' => 'oe', '' => 'oe', 'Æ' => 'ae',
                    'æ' => 'ae', '$' => 's',  '¥' => 'y');
    $clean_string = strtr($clean_string, $replace);

    $clean_string = ereg_replace("[^a-z0-9._]", "", $clean_string);
    return $clean_string;
}


/**
 * Inserts javascript code at the current position.
 *
 * Assumes that the string is valid JavaScript code and adds
 * a pair of <script> / </script> tags before printing.
 * @author  Ivan Schmid <ivan.schmid@comvation.com>
 * @param   string  $string     javascript code
 * @todo    It can't be wrong to add a HTML comment around the script as well:
 *          - <script type="text/javascript"><!--
 *          - $string
 *          - //--></script>
 *          (Mind the newlines!)
 * @todo    This function should have a more elaborate name as well.
 */
function evalJS($string)
{
    echo '<script type="text/javascript">'.$string.'</script>';
}


/**
 * Inserts javascript alert code at the current position.
 *
 * Takes the string and creates a JavaScript alert() call.
 * This is directly passed to {@link evalJS}.
 * @author  Ivan Schmid <ivan.schmid@comvation.com>
 * @param   string  $string    alert message
 * @todo    This function should have a more elaborate name as well.
 */
function alertJS($string)
{
    evalJS('alert("'.$string.'")');
}


/**
 * Sets 'module2id' and 'id2module' via Env::set().
 * Called in both of the index.php files.
 */
function createModuleConversionTables()
{
    $db = Env::get('db');
    //an array mapping module names to their id
    $module2id = array();
    //above arrays' counterpart
    $id2module = array();
    $rs = $db->Query('SELECT id, name FROM '.DBPREFIX.'modules');
    if ($rs) {
        while(!$rs->EOF) {
            $module2id[$rs->fields['name']] = $rs->fields['id'];
            $id2module[$rs->fields['id']] = $rs->fields['name'];
            $rs->MoveNext();
        }
    }
    Env::set('module2id', $module2id);
    Env::set('id2module', $id2module);
}
