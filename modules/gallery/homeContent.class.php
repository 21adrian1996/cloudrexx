<?php
/**
 * Gallery home content
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team
 * @version     1.0.0
 * @package     contrexx
 * @subpackage  module_gallery
 * @todo        Edit PHP DocBlocks!
 */

/**
 * @ignore
 */
require_once ASCMS_MODULE_PATH.'/gallery/Lib.class.php';

/**
 * Gallery home content
 *
 * Show Gallery Block Content (Random, Last)
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Comvation Development Team
 * @access      public
 * @version     1.0.0
 * @package     contrexx
 * @subpackage  module_gallery
 */
class GalleryHomeContent extends GalleryLibrary
{
    var $_strWebPath;

    /**
    * Constructor php5
    */
    function __construct()
    {
        $this->getSettings();
        $this->_strWebPath     = ASCMS_GALLERY_THUMBNAIL_WEB_PATH . '/';
    }


    /**
     * Check if the random-function is activated
     *
     * @return boolean
     */
    function checkRandom() {
        if ($this->arrSettings['show_random'] == 'on') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Check if the latest-function is activated
     *
     * @return boolean
     */
    function checkLatest() {
        if ($this->arrSettings['show_latest'] == 'on') {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Returns an randomized image from database
     *
     * @global     ADONewConnection
     * @return     string     Complete <img>-tag for a randomized image
     */
    function getRandomImage()
    {
        global $objDatabase;

        $objResult = $objDatabase->Execute('SELECT        SUM(1) AS catCount
                                                    FROM         '.DBPREFIX.'module_gallery_categories        AS categories
                                                    INNER JOIN    '.DBPREFIX.'module_gallery_pictures         AS pics ON pics.catid = categories.id
                                                    INNER JOIN    '.DBPREFIX.'module_gallery_language_pics     AS lang    ON lang.picture_id = pics.id
                                                    WHERE    categories.status="1" AND
                                                            pics.validated="1" AND
                                                            pics.status="1" AND
                                                            lang.lang_id = '.FRONTEND_LANG_ID.'
                                                    GROUP BY categories.id
                                                    ORDER BY categories.id');

        if ($objResult === false || $objResult->RecordCount() == 0) {
            return '';
        } else {
            $catNr = mt_rand(0, $objResult->RecordCount()-1);

            $objResult = $objDatabase->SelectLimit('SELECT    categories.id
                                                    FROM         '.DBPREFIX.'module_gallery_categories        AS categories
                                                    INNER JOIN    '.DBPREFIX.'module_gallery_pictures         AS pics ON pics.catid = categories.id
                                                    INNER JOIN    '.DBPREFIX.'module_gallery_language_pics     AS lang    ON lang.picture_id = pics.id
                                                    WHERE    categories.status="1" AND
                                                            pics.validated="1" AND
                                                            pics.status="1" AND
                                                            lang.lang_id = '.FRONTEND_LANG_ID.'
                                                    GROUP BY categories.id
                                                    ORDER BY categories.id', 1, $catNr);

            if ($objResult === false || $objResult->RecordCount() == 0) {
                return '';
            } else {
                $catId = $objResult->fields['id'];


                $objResult = $objDatabase->SelectLimit('SELECT        SUM(1) AS picCount
                                                FROM        '.DBPREFIX.'module_gallery_pictures         AS pics
                                                INNER JOIN    '.DBPREFIX.'module_gallery_language_pics     AS lang    ON pics.id = lang.picture_id
                                                WHERE    pics.validated="1" AND
                                                        pics.status="1" AND
                                                        pics.catid='.$catId.' AND
                                                        lang.lang_id = '.FRONTEND_LANG_ID, 1);

                if ($objResult === false || $objResult->RecordCount() == 0) {
                    return '';
                } else {
                    $picNr = mt_rand(0, $objResult->fields['picCount']-1);

                    $objResult = $objDatabase->SelectLimit("SELECT value FROM ".DBPREFIX."module_gallery_settings WHERE name='paging'", 1);
                    $paging = $objResult->fields['value'];

                    $objResult = $objDatabase->SelectLimit('SELECT    pics.catid    AS CATID,
                                                                    pics.path    AS PATH,
                                                                    lang.name    AS NAME
                                                        FROM        '.DBPREFIX.'module_gallery_pictures         AS pics
                                                        INNER JOIN    '.DBPREFIX.'module_gallery_language_pics     AS lang    ON pics.id = lang.picture_id
                                                        WHERE    pics.validated="1" AND
                                                                pics.status="1" AND
                                                                pics.catid='.$catId.' AND
                                                            lang.lang_id = '.FRONTEND_LANG_ID.'
                                                            ORDER BY pics.sorting', 1, $picNr);

                    if ($objResult !== false) {
                        $strReturn =     '<a href="'.CONTREXX_DIRECTORY_INDEX.'?section=gallery&amp;cid='.$objResult->fields['CATID'].($picNr >= $paging ? '&amp;pos='.(floor($picNr/$paging)*$paging) : '').'" target="_self">';
                        $strReturn .=    '<img border="0" alt="'.htmlentities($objResult->fields['NAME'], ENT_QUOTES, CONTREXX_CHARSET).'" title="'.htmlentities($objResult->fields['NAME'], ENT_QUOTES, CONTREXX_CHARSET).'" src="'.$this->_strWebPath.$objResult->fields['PATH'].'" /></a>';
                        return $strReturn;
                    } else {
                        return '';
                    }
                }
            }
        }
    }


    /**
     * Returns the last inserted image from database
     *
     * @global     ADONewConnection
     * @global     array
     * @global     array
     * @return     string     Complete <img>-tag for a randomized image
     */
    function getLastImage() {
        global $objDatabase, $_CONFIG, $_ARRAYLANG;

        $picNr = 0;

        $objResult = $objDatabase->Execute('SELECT        pics.id,
                                                        pics.catid    AS CATID,
                                                        pics.path    AS PATH,
                                                        lang.name    AS NAME
                                            FROM        '.DBPREFIX.'module_gallery_pictures         AS pics
                                            INNER JOIN    '.DBPREFIX.'module_gallery_language_pics     AS lang         ON pics.id = lang.picture_id
                                            INNER JOIN     '.DBPREFIX.'module_gallery_categories         AS categories     ON pics.catid = categories.id
                                            WHERE        categories.status = "1"        AND
                                                        pics.validated = "1"        AND
                                                        pics.status = "1"            AND
                                                        lang.lang_id = '.FRONTEND_LANG_ID.'
                                            ORDER BY    pics.id DESC
                                            LIMIT        1
                                        ');

        if ($objResult->RecordCount() == 1) {
            $objPaging = $objDatabase->SelectLimit("SELECT value FROM ".DBPREFIX."module_gallery_settings WHERE name='paging'", 1);
            $paging = $objPaging->fields['value'];

            $objPos = $objDatabase->Execute('SELECT        pics.id
                                                FROM        '.DBPREFIX.'module_gallery_pictures         AS pics
                                                INNER JOIN    '.DBPREFIX.'module_gallery_language_pics     AS lang         ON pics.id = lang.picture_id
                                                INNER JOIN     '.DBPREFIX.'module_gallery_categories         AS categories     ON pics.catid = categories.id
                                                WHERE        categories.status = "1"        AND
                                                            pics.validated = "1"        AND
                                                            pics.status = "1"            AND
                                                            lang.lang_id = '.FRONTEND_LANG_ID.'
                                                ORDER BY    pics.sorting');
            if ($objPos !== false) {
                while (!$objPos->EOF) {
                    if ($objPos->fields['id'] == $objResult->fields['id']) {
                        break;
                    } else {
                        $picNr++;
                    }
                    $objPos->MoveNext();
                }
            }

            $strReturn =     '<a href="'.CONTREXX_DIRECTORY_INDEX.'?section=gallery&amp;cid='.$objResult->fields['CATID'].($picNr >= $paging ? '&amp;pos='.floor(($picNr) / $paging) : '').'" target="_self">';
            $strReturn .=    '<img border="0" alt="'.$objResult->fields['NAME'].'" title="'.$objResult->fields['NAME'].'" src="'.$this->_strWebPath.$objResult->fields['PATH'].'" /></a>';
            return $strReturn;
        } else {
            return '';
        }
    }
}
?>
