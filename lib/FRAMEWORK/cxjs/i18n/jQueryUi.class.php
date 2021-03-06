<?php

/**
 * Cloudrexx
 *
 * @link      http://www.cloudrexx.com
 * @copyright Cloudrexx AG 2007-2015
 *
 * According to our dual licensing model, this program can be used either
 * under the terms of the GNU Affero General Public License, version 3,
 * or under a proprietary license.
 *
 * The texts of the GNU Affero General Public License with an additional
 * permission and of our proprietary license can be found at and
 * in the LICENSE file you have received along with this program.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * "Cloudrexx" is a registered trademark of Cloudrexx AG.
 * The licensing of the program under the AGPLv3 does not imply a
 * trademark license. Therefore any rights, title and interest in
 * our trademarks remain entirely with us.
 */

/**
 * JQueryUiI18nProvider
 *
 * @copyright   CLOUDREXX CMS - CLOUDREXX AG
 * @author      CLOUDREXX Development Team <info@cloudrexx.com>
 * @package     cloudrexx
 * @subpackage  lib_cxjs
 */

/**
 * JQueryUiI18nProvider
 *
 * @copyright   CLOUDREXX CMS - CLOUDREXX AG
 * @author      CLOUDREXX Development Team <info@cloudrexx.com>
 * @package     cloudrexx
 * @subpackage  lib_cxjs
 */
class JQueryUiI18nProvider implements ContrexxJavascriptI18nProvider {
    public function getVariables($langCode) {
        $vars = array();
        $datePickerFile = '/lib/javascript/jquery/ui/i18n/jquery.ui.datepicker-'.$langCode.'.js';
        $datePickerDefaultFile = '/lib/javascript/jquery/ui/i18n/jquery.ui.datepicker-default.js';
        if (file_exists(ASCMS_DOCUMENT_ROOT.$datePickerFile)) {
            $vars['datePickerI18nFile'] = $datePickerFile;
        } elseif (file_exists(ASCMS_DOCUMENT_ROOT.$datePickerDefaultFile)) {
            $vars['datePickerI18nFile'] = $datePickerDefaultFile;
        }
        return $vars;
    }
}
