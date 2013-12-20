<?php

/**
 * JSON Adapter for Survey module
 * @copyright   Comvation AG
 * @author      ss4u <ss4ugroup@gmail.com>
 * @package     contrexx
 * @subpackage  core_json
 */

namespace Cx\Modules\Crm\Lib\Controllers;
use \Cx\Core\Json\JsonAdapter;

/**
 * JSON Adapter for Survey module
 * @copyright   Comvation AG
 * @author      ss4u <ss4ugroup@gmail.com>
 * @package     contrexx
 * @subpackage  core_json
 */
class JsonCrm implements JsonAdapter {
    /**
     * List of messages
     * @var Array 
     */
    private $messages = array();
    
    /**
     * Returns the internal name used as identifier for this adapter
     * @return String Name of this adapter
     */
    public function getName() {
        return 'crm';
    }
    
    /**
     * Returns an array of method names accessable from a JSON request
     * @return array List of method names
     */
    public function getAccessableMethods() {
        return array('searchContacts');
    }

    /**
     * Returns all messages as string
     * @return String HTML encoded error messages
     */
    public function getMessagesAsString() {
        return implode('<br />', $this->messages);
    }

    /**
     * get customer search result
     *
     * @global array $_ARRAYLANG
     * @global object $objDatabase
     * @return json result
     */
    public function searchContacts()
    { 
        global $objDatabase;

        $searchFields = array(
            'companyname_filter'  => isset($_REQUEST['companyname_filter']) ? contrexx_input2raw($_REQUEST['companyname_filter']) : '',
            'contactSearch'       => isset($_GET['contactSearch']) ? (array) $_GET['contactSearch'] : array(1,2),
            'advanced-search'     => $_REQUEST['advanced-search'],
            's_name'              => $_REQUEST['s_name'],
            's_email'             => $_REQUEST['s_email'],
            's_address'           => $_REQUEST['s_address'],
            's_city'              => $_REQUEST['s_city'],
            's_postal_code'       => $_REQUEST['s_postal_code'],
            's_notes'             => $_REQUEST['s_notes'],
            'customer_type'       => $_REQUEST['customer_type'],
            'filter_membership'   => $_REQUEST['filter_membership'],
            'term'                => isset($_REQUEST['term']) ? contrexx_input2raw($_REQUEST['term']) : '',
            'sorto'               => $_REQUEST['sorto'],
            'sortf'               => $_REQUEST['sortf'],
        );
        
        $objCrmLibrary = new \CrmLibrary();
        $query         = $objCrmLibrary->getContactsQuery($searchFields);
        
        $objResult     = $objDatabase->Execute($query);

        $result = array();
        if ($objResult) {
            while (!$objResult->EOF) {
                if ($objResult->fields['contact_type'] == 1) {
                    $contactName = $objResult->fields['customer_name'];
                } else {
                    $contactName = $objResult->fields['customer_name']." ".$objResult->fields['contact_familyname'];
                }
                $result[] = array(
                    'id'    => (int) $objResult->fields['id'],
                    'label' => html_entity_decode(stripslashes($contactName), ENT_QUOTES, CONTREXX_CHARSET),
                    'value' => html_entity_decode(stripslashes($contactName), ENT_QUOTES, CONTREXX_CHARSET),
                );
                $objResult->MoveNext();
            }
        }
        
        return $result;
    }
}    
?>
