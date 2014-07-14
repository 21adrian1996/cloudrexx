<?php
/**
 * Sorting controller
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      COMVATION Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  coremodule_listing
 */

namespace Cx\Core_Modules\Listing\Controller;

/**
 * Sorting controller
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      COMVATION Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  coremodule_listing
 */
class SortingController {
	
	public function handle($params, $config) {
	    if (!isset($config['order'])) {
	        return $params;
	    }
	    $order = explode('/', $config['order']);
	    $sortField = current($order);
	    $sortOrder = SORT_ASC;
	    if (count($order) > 1) {
	        if ($order[1] == 'DESC') {
	            $sortOrder = SORT_DESC;
	        }
	    }
	    $params['order'] = array($sortField => $sortOrder);
	    return $params;
	}
}
