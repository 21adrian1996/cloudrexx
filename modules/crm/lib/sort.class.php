<?php
/**
 * Sorter Class CRM
 *
 * PHP version 5.3 or >
 *
 * @category crmInterface
 * @package  PM_CRM_Tool
 * @author   ss4ugroup <ss4ugroup@softsolutions4u.com>
 * @license  BSD Licence
 * @version  1.0.0
 * @link     http://mycomvation.com/po/cadmin
 */

/**
 * Sorter Class CRM
 *
 * PHP version 5.3 or >
 *
 * @category crmInterface
 * @package  PM_CRM_Tool
 * @author   ss4ugroup <ss4ugroup@softsolutions4u.com>
 * @license  BSD Licence
 * @version  1.0.0
 * @link     http://mycomvation.com/po/cadmin
 */

class Sorter
{
    /**
     * Sort fields
     *
     * @access private
     * @var string
     */
    var $sort_fields;

    /**
     * Backwards
     *
     * @access private
     * @var boolean
     */
    var $backwards = false;

    /**
     * Numeric
     *
     * @access private
     * @var boolean
     */
    var $numeric = false;

    /**
     * sort function
     * 
     * @return array
     */
    function sort()
    {
        $args = func_get_args();
        $array = $args[0];
        if (!$array) return array();
        $this->sort_fields = array_slice($args, 1);
        if (!$this->sort_fields) return $array();

        if ($this->numeric) {
            usort($array, array($this, 'numericCompare'));
        } else {
            usort($array, array($this, 'stringCompare'));
        }
    return $array;
    }

    /**
     * compare the numeric values
     *
     * @param array $a
     * @param array $b
     * 
     * @return Integer
     */
    function numericCompare($a, $b)
    {
        foreach($this->sort_fields as $sort_field) {
            if ($a[$sort_field] == $b[$sort_field]) {
                continue;
            }
            return ($a[$sort_field] < $b[$sort_field]) ? ($this->backwards ? 1 : -1) : ($this->backwards ? -1 : 1);
        }
    return 0;
    }

    /**
     * Compare the String
     *
     * @param array $a
     * @param array $b
     *
     * @return Integer
     */
    function stringCompare($a, $b)
    {
        foreach($this->sort_fields as $sort_field) {
            $cmp_result = strcasecmp($a[$sort_field], $b[$sort_field]);
            if ($cmp_result == 0) continue;

            return ($this->backwards ? -$cmp_result : $cmp_result);
        }
    return 0;
    }
}