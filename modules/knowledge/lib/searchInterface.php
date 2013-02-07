<?php

/**
 * searchInterface
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      COMVATION Development Team <info@comvation.com>
 * @package     contrexx
 * @subpackage  module_knowledge
 */

if (!class_exists("SearchInterface")) {

    /**
     * SearchInferface
     *
     * @copyright   CONTREXX CMS - COMVATION AG
     * @author      COMVATION Development Team <info@comvation.com>
     * @package     contrexx
     * @subpackage  module_knowledge
     */
    abstract class SearchInterface {
        abstract public function search($term);
    }

}
