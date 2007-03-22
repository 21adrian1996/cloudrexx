<?php
/**
 * Class podcast library
 *
 * podcast library class
 *
 * @copyright   CONTREXX CMS - ASTALAVISTA IT AG
 * @author		Astalavista Development Team <thun@astalvista.ch>
 * @version		1.0.0
 * @package     contrexx
 * @subpackage  core_module_cache
 * @todo        Edit PHP DocBlocks!
 * @todo        Descriptions are wrong. What is it really?
 */

/**
 * Class podcast library
 *
 * podcast library class
 *
 * @copyright   CONTREXX CMS - ASTALAVISTA IT AG
 * @author		Astalavista Development Team <thun@astalvista.ch>
 * @access		public
 * @version		1.0.0
 * @package     contrexx
 * @subpackage  core_module_cache
 * @todo        Descriptions are wrong. What is it really?
 */
class cacheLib
{
	var $strCachePath;

	function _deleteAllFiles()
	{
		$handleDir = opendir($this->strCachePath);
		if ($handleDir) {
			while ($strFile = readdir($handleDir)) {
				if ($strFile != '.' && $strFile != '..' && $strFile != $this->strCacheablePagesFile) {
					unlink($this->strCachePath.$strFile);
				}
			}
			closedir($handleDir);
		}
	}
}
?>
