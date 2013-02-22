<?php
/**
 * Library Class CRM
 *
 * CrmLibrary class
 *
 * @copyright	CONTREXX CMS
 * @author		SoftSolutions4U Development Team <info@softsolutions4u.com>
 * @module		CRM
 * @modulegroup	modules
 * @access		public
 * @version		1.0.0
 */

require_once  ASCMS_MODULE_PATH  . '/crm/lib/constants.php';

require_once CRM_MODULE_LIB_PATH . '/events/Event.class.php';
require_once CRM_MODULE_LIB_PATH . '/events/EventDispatcher.class.php';
require_once CRM_MODULE_LIB_PATH . '/events/handlers/InterfaceEventHandler.class.php';
require_once CRM_MODULE_LIB_PATH . '/events/handlers/DefaultEventHandler.class.php';

class CrmLibrary {
    var $_csvSeparator          = ';';
    var $moduleName             = 'crm';
    var $pm_moduleName          = 'pm';
    var $isPmInstalled          = false;
    var $adminAccessId          = 557;
    var $staffAccessId          = 556;
    var $customerAccessId       = 555;
    var $_arrSettings           = array();
    var $countries              = array();
    var $customerTypes          = array();
    var $_arrLanguages          = array();
    var $_memberShips           = array();
    var $emailOptions           = array("TXT_CRM_HOME", "TXT_CRM_WORK", "TXT_CRM_OTHERS");
    var $phoneOptions           = array("TXT_CRM_HOME", "TXT_CRM_WORK", "TXT_CRM_OTHERS", "TXT_CRM_MOBILE", "TXT_CRM_FAX", "TXT_CRM_DIRECT");
    var $websiteOptions         = array("TXT_CRM_HOME", "TXT_CRM_WORK", "TXT_CRM_BUSINESS1", "TXT_CRM_BUSINESS2", "TXT_CRM_BUSINESS3", "TXT_CRM_PRIVATE", "TXT_CRM_OTHERS");
    var $websiteProfileOptions  = array("TXT_CRM_HOME", "TXT_CRM_WORK", "TXT_CRM_OTHERS", "TXT_CRM_BUSINESS1", "TXT_CRM_BUSINESS2", "TXT_CRM_BUSINESS3");
    var $socialProfileOptions   = array("", "TXT_CRM_SKYPE", "TXT_CRM_TWITTER", "TXT_CRM_LINKEDIN", "TXT_CRM_FACEBOOK", "TXT_CRM_LIVEJOURNAL",
            "TXT_CRM_MYSPACE", "TXT_CRM_GMAIL", "TXT_CRM_BLOGGER", "TXT_CRM_YAHOO", "TXT_CRM_MSN", "TXT_CRM_ICQ", "TXT_CRM_JABBER",
            "TXT_CRM_AIM");
    var $addressValues          = array("","address", "city", "state", "zip", "country", "type");
    var $addressTypes           = array("TXT_CRM_HOME", "TXT_CRM_DELIVERY", "TXT_CRM_OFFICE", "TXT_CRM_BILLING", "TXT_CRM_OTHERS", "TXT_CRM_WORK");

    /**
     * Status message
     *
     * @access private
     * @var string
     */
    var $_statusMessage = '';
    var $_strOkMessage  = '';
    var $_strErrMessage  = '';
    var $supportCaseStatus = array(
            0 => 'Open',
            1 => 'Pending',
            2 => 'Closed'
    );

    protected $load;
    protected static $instance;

    public static function init() {
        if(is_null(self::$instance)) {
            $class = __CLASS__;
            self::$instance = new $class;
        }

        return self::$instance;
    }

    function  __construct() {
        $this->load          = new loader();
        $this->_arrLanguages = $this->createLanguageArray();
        $this->isPmInstalled = contrexx_isModuleActive($this->pm_moduleName);        
    }

    function getSettings() {
        global $objDatabase;

        if (!empty($this->_arrSettings)) {
            return $this->_arrSettings;
        }

        $query = "SELECT `setid`, `setname`, `setvalue` FROM ".DBPREFIX."module_{$this->moduleName}_settings";
        $settings = $objDatabase->execute($query);

        if (false !== $settings) {
            while (!$settings->EOF) {
                $this->_arrSettings[$settings->fields['setname']] = $settings->fields['setvalue'];
                $settings->moveNext();
            }
        }

        return $this->_arrSettings;
    }

    /**
     * Creates an array containing all frontend-languages. Example: $arrValue[$langId]['short'] or $arrValue[$langId]['long']
     *
     * @global  ADONewConnection
     * @return  array       $arrReturn
     */
    function createLanguageArray() {
        global $objDatabase;

        $arrReturn = array();

        $objResult = $objDatabase->Execute('SELECT      id,
                                                        lang,
                                                        name
                                            FROM        '.DBPREFIX.'languages
                                            WHERE       frontend=1
                                            ORDER BY    id
                                        ');
        while (!$objResult->EOF) {
            $arrReturn[$objResult->fields['id']] = array(   'short' =>  stripslashes($objResult->fields['lang']),
                    'long'  =>  htmlentities(stripslashes($objResult->fields['name']),ENT_QUOTES, CONTREXX_CHARSET)
            );
            $objResult->MoveNext();
        }

        return $arrReturn;
    }

    function _usortByMultipleKeys($key, $direction=SORT_ASC) {

        if ($direction == 0) {
            $direction = SORT_ASC;
        } else if ($direction == 1) {
            $direction = SORT_DESC;
        }

        $sortFlags = array(SORT_ASC, SORT_DESC);
        if (!in_array($direction, $sortFlags)) {
            throw new InvalidArgumentException('Sort flag only accepts SORT_ASC or SORT_DESC');
        }
        return function($a, $b) use ($key, $direction, $sortFlags) {
                    if (!is_array($key)) { //just one key and sort direction
                        if (!isset($a[$key]) || !isset($b[$key])) {
//                    throw new Exception('Attempting to sort on non-existent keys');
                        }
                        if ($a[$key] == $b[$key]) {
                            return 0;
                        }
                        return ($direction==SORT_ASC xor strtolower($a[$key]) < strtolower($b[$key])) ? 1 : -1;
                    } else { //using multiple keys for sort and sub-sort
                        foreach ($key as $subKey => $subAsc) {
                            //array can come as 'sort_key'=>SORT_ASC|SORT_DESC or just 'sort_key', so need to detect which
                            if (!in_array($subAsc, $sortFlags)) {
                                $subKey = $subAsc;
                                $subAsc = $direction;
                            }
                            //just like above, except 'continue' in place of return 0
                            if (!isset($a->$subKey) || !isset($b->$subKey)) {
                                throw new Exception('Attempting to sort on non-existent keys');
                            }
                            if ($a->$subKey == $b->$subKey) {
                                continue;
                            }
                            return ($subAsc==SORT_ASC xor $a->$subKey < $b->$subKey) ? 1 : -1;
                        }
                        return 0;
                    }
                };
    }

    function saveTaskTypes($id = 0) {
        global $objDatabase;

        $name       = isset($_POST['name']) ? contrexx_input2db($_POST['name']) : '';
        $active     = isset($_POST['active']) ? 1 : 0;
        $sortOrder  = isset($_POST['sort']) ? (int) $_POST['sort'] : 0;
        $description= isset($_POST['description']) ? contrexx_input2db($_POST['description']) : '';

        $where = '';
        if ($id)
            $where = "WHERE `id` = $id";

        $Update = ($id) ? "UPDATE" : "INSERT INTO";
        $query = "$Update `".DBPREFIX."module_{$this->moduleName}_task_types`
                        SET `name` = '$name',
                            `status` = $active,
                            `sorting` = $sortOrder,
                            `description` = '$description'
                $where";
        $objDatabase->Execute($query);
    }
    
    function showTaskTypes() {
        global $_ARRAYLANG, $objDatabase;

        $objTpl = $this->_objTpl;
        $objTpl->addBlockfile('CRM_TASK_TYPES_TABLE_FILE', 'settings_tasktype', 'module_'.$this->moduleName.'_settings_task_type_table.html');

        $objResult = $objDatabase->Execute("SELECT * FROM `".DBPREFIX."module_{$this->moduleName}_task_types` ORDER BY `sorting`");

        if(isset($_GET['sortf']) && isset($_GET['sorto'])) {
            $sortf = ($_GET['sortf'] == 1)? 'name':'sorting';
            $sorto = ($_GET['sorto'] == 'ASC')? 'DESC' : 'ASC';
            $query = "SELECT * FROM `".DBPREFIX."module_{$this->moduleName}_task_types` ORDER BY $sortf $sorto";
            $objResult      = $objDatabase->Execute($query);
        }

        if ($objResult->RecordCount()) {
            $objTpl->hideBlock("noTasktypes");
        } else {
            $objTpl->touchBlock("noTasktypes");
            $objTpl->hideBlock("taskTypes");
        }

        if ($objResult) {
            $row = "row2";
            while(!$objResult->EOF) {
                $status = ($objResult->fields['status']) ? "led_green.gif" : "led_red.gif";
                $objTpl->setVariable(array(
                        'CRM_TASK_TYPE_ID'          => (int) $objResult->fields['id'],
                        'CRM_TASK_TYPE_NAME'        => contrexx_raw2xhtml($objResult->fields['name']),
                        'CRM_TASK_TYPE_SORTING'     => (int) $objResult->fields['sorting'],
                        //        'CRM_TASK_TYPE_DESCRIPTION' => contrexx_raw2xhtml($objResult->fields['description']),
                        'CRM_TASK_TYPE_ACTIVE'      => $status,
                        'ROW_CLASS'                 => $row = ($row == "row2") ? "row1" : "row2",
                        'TXT_ORDER'         => $sorto
                ));
                $objTpl->parse("taskTypes");
                $objResult->MoveNext();
            }
        }
    }

    /* @var $objResult <object> */
    function getModifyTaskTypes($id = 0) {
        global $objDatabase, $_ARRAYLANG;

        $objTpl = $this->_objTpl;
        $objTpl->addBlockfile('CRM_TASK_TYPES_MODIFY_FILE', 'settings_modify_taskType', 'module_'.$this->moduleName.'_settings_task_type_modify.html');

        $name       = isset($_POST['name']) && $id ? $_POST['name'] : '';
        $active     = isset($_POST['active']) || !$id ? 1 : 0;
        $sortOrder  = isset($_POST['sort']) && $id ? (int) $_POST['sort'] : '';
        $description= isset($_POST['description']) && $id ? $_POST['description'] : '';

        if ($id) {
            $objResult = $objDatabase->SelectLimit("SELECT * FROM `".DBPREFIX."module_{$this->moduleName}_task_types` WHERE id = $id" ,1);

            $name       = $objResult->fields['name'];
            $active     = ($objResult->fields['status']) ? 1 : 0;
            $sortOrder  = $objResult->fields['sorting'];
            $description= $objResult->fields['description'];
        } else {
            $objTpl->hideBlock("taskBackButton");
        }

        $objTpl->setVariable(array(
                'CRM_TASK_TYPE_ID'          => $id,
                'CRM_TASK_TYPE_NAME'        => contrexx_raw2xhtml($name),
                'CRM_TASK_TYPE_SORTING'     => $sortOrder,
                'CRM_TASK_TYPE_DESCRIPTION' => contrexx_raw2xhtml($description),
                'CRM_TASK_TYPE_ADD_ACTIVE'  => (empty($_POST) && empty($id)) || ($active) ? "checked" : '',

                'TXT_CRM_TASK_TYPE_NAME'        => $_ARRAYLANG['TXT_CRM_TASK_TYPE_NAME'],
                'TXT_CRM_TASK_TYPE_SORTING'     => $_ARRAYLANG['TXT_CRM_TASK_TYPE_SORTING'],
                'TXT_CRM_TASK_TYPE_DESCRIPTION' => $_ARRAYLANG['TXT_CRM_TASK_TYPE_DESCRIPTION'],
                'TXT_CRM_TASK_TYPE_SORTING1'     => $_ARRAYLANG['TXT_CRM_TASK_TYPE_SORTING1'],
                'TXT_CRM_TASK_TYPE_ACTIVE'      => $_ARRAYLANG['TXT_CRM_TASK_TYPE_ACTIVE'],
                'TXT_SAVE'                      => $_ARRAYLANG['TXT_SAVE'],
        ));
    }

    function taskTypeDropDown($objTpl, $selectedType = 0) {
        global $objDatabase, $_ARRAYLANG;

        $objResult = $objDatabase->Execute("SELECT id,name FROM ".DBPREFIX."module_{$this->moduleName}_task_types WHERE status=1 ORDER BY sorting");
        while(!$objResult->EOF) {
            $selected = $selectedType == $objResult->fields['id'] ? "selected" : '';

            $objTpl->setVariable(array(
                    'TXT_TASKTYPE_ID'       => (int) $objResult->fields['id'],
                    'TXT_TASKTYPE_NAME'     => contrexx_input2xhtml($objResult->fields['name']),
                    'TXT_TASKTYPE_SELECTED' => $selected,
            ));
            $objTpl->parse('Tasktype');
            $objResult->MoveNext();
        }
    }

    function getCustomerTypes() {
        global $objDatabase;

        if (!empty($this->customerTypes)) return $this->customerTypes;

        $objResult = $objDatabase->Execute('SELECT id,label FROM  '.DBPREFIX.'module_'.$this->moduleName.'_customer_types WHERE  active!="0" ORDER BY pos,label');

        while(!$objResult->EOF) {
            $this->customerTypes[$objResult->fields['id']] = array(
                    'id'    => $objResult->fields['id'],
                    'label' => $objResult->fields['label']
            );
            $objResult->MoveNext();
        }

    }

    function getCustomerTypeDropDown($objTpl, $selectedId = 0, $block = "customerTypes") {
        global $_ARRAYLANG;

        $this->getCustomerTypes();

        foreach ($this->customerTypes as $key => $value) {

            $selected = ($value['id'] == $selectedId ) ? 'selected ="selected"' : '';
            $objTpl->setVariable(array(
                    'CRM_CUSTOMER_TYPE_SELECTED' => $selected,
                    'CRM_CUSTOMER_TYPE'          => contrexx_raw2xhtml($value['label']),
                    'CRM_CUSTOMER_TYPE_ID'       => (int) $value['id']));
            $objTpl->parse($block);
        }
    }

    function getCustomerCurrencyDropDown($objTpl, $selectedId = 0, $block = "currency") {
        global $_ARRAYLANG, $objDatabase;

        $objResultCurrency = $objDatabase->Execute('SELECT   id,name,pos,active
                                                FROM     '.DBPREFIX.'module_'.$this->moduleName.'_currency
                                                WHERE    active!="0"
                                                ORDER BY pos,name');
        while(!$objResultCurrency->EOF) {
            //$selected = ($selectedId$contactObj->getCustomerCurrency() == $objResultCurrency->fields['id']) ? "selected" : '';
            $selected = ($selectedId == $objResultCurrency->fields['id']) ? "selected" : '';

            $objTpl->setVariable(array(
                    'CRM_CURRENCYNAME'      =>    contrexx_raw2xhtml($objResultCurrency->fields['name']),
                    'CRM_CURRENCYID'        =>    (int) $objResultCurrency->fields['id'],
                    'CRM_CURRENCY_SELECTED' =>    $selected,
            ));
            $objTpl->parse($block);
            $objResultCurrency->MoveNext();
        }
    }

    function getIndustryTypeDropDown($objTpl, $selectedId = 0, $block = "industryType") {
        global $_ARRAYLANG, $objDatabase, $_LANGID;

        $objResultIndustryType = $objDatabase->Execute("SELECT Intype.id,
                                                              Inloc.value
                                                         FROM `".DBPREFIX."module_{$this->moduleName}_industry_types` AS Intype
                                                         LEFT JOIN `".DBPREFIX."module_{$this->moduleName}_industry_type_local` AS Inloc
                                                            ON Intype.id = Inloc.entry_id
                                                         WHERE Inloc.lang_id = ".$_LANGID." AND Intype.status = 1 ORDER BY sorting ASC ");
        while(!$objResultIndustryType->EOF) {
            $selected = ($selectedId == $objResultIndustryType->fields['id']) ? "selected" : '';

            $objTpl->setVariable(array(
                    'CRM_INDUSTRY_TYPE_NAME'      =>    contrexx_raw2xhtml($objResultIndustryType->fields['value']),
                    'CRM_INDUSTRY_TYPE_ID'        =>    (int) $objResultIndustryType->fields['id'],
                    'CRM_INDUSTRY_TYPE_SELECTED'  =>    $selected,
            ));
            $objTpl->parse($block);
            $objResultIndustryType->MoveNext();
        }
    }

    function parseContacts($input) {

        foreach ($input as $key => $value) {
            $splitKeys = explode("_", $key);
            switch ($splitKeys[0]) {
                case 'contactemail':
                case 'contactphone':
                    $result[$splitKeys[0]][] = array('type' => $splitKeys[2], 'primary' => $splitKeys[3], 'value' => $value);
                    break;
                case 'contactwebsite':
                case 'contactsocial':
                    $result[$splitKeys[0]][$splitKeys[1]] = array('profile' => $splitKeys[2], 'primary' => $splitKeys[3], 'value' => $value);
                    break;
                case 'website':
                    $result['contactwebsite'][$splitKeys[1]]['id'] = $value;
                    break;
                case 'contactAddress':
                    if ($this->addressValues[$splitKeys[2]] == "address") $result[$splitKeys[0]][$splitKeys[1]]["primary"] = $splitKeys[3];
                    $result[$splitKeys[0]][$splitKeys[1]][$this->addressValues[$splitKeys[2]]] = $value;
                    break;
                default:
                    $result[$key]            = $value;
                    break;
            }
        }

        return $result;
    }

    function getContactAddressCountry($objTpl, $selectedCountry, $block = "crmCountry") {
        $countryArr = $this->getCountry();

        foreach ($countryArr as $value) {
            $selected = ($selectedCountry == contrexx_raw2xhtml($value['name'])) ? "selected" : "";
            $objTpl->setVariable(array(
                    'CRM_COUNTRY_SELECTED' => $selected,
                    'CRM_COUNTRY'          => contrexx_raw2xhtml($value['name']),
            ));
            $objTpl->parse($block);
        }
    }

    function getCountry() {
        global $objDatabase;

        if (!empty($this->countries)) return $this->countries;

        // Selecting the Country Name from the Database
        $objResult =   $objDatabase->Execute('SELECT  iso_code_2,id,name FROM '.DBPREFIX.'lib_country ORDER BY id' );

        while(!$objResult->EOF) {
            $this->countries[$objResult->fields['id']] = array("id" => $objResult->fields['id'], "name" => $objResult->fields['name'], "iso_code_2" => $objResult->fields['iso_code_2']);

            $objResult->MoveNext();
        }
        return $this->countries;
    }

    function getContactAddrTypeCountry($objTpl, $selectedType, $block = "addressType") {
        global $_ARRAYLANG;

        foreach ($this->addressTypes as $key => $value) {
            $selected = ($key == $selectedType) ? "selected" : '';
            $objTpl->setVariable(array(
                    'CRM_ADDRESS_TYPE'          => (int) $key,
                    'CRM_ADDRESS_TYPE_NAME'     => contrexx_raw2xhtml($_ARRAYLANG[$value]),
                    'CRM_ADDRESS_TYPE_SELECTED' => $selected
            ));
            $objTpl->parse($block);
        }

    }

    function updateCustomerContacts($contacts, $customerId) {
        global $objDatabase;

        // Reset the contacts
        $objDatabase->Execute("UPDATE ".DBPREFIX."module_".$this->moduleName."_contacts SET contact_customer = 0
                                                                       WHERE  customer_id = '".intval($customerId)."'");

        foreach ($contacts as $value) {
            $objDatabase->Execute("UPDATE ".DBPREFIX."module_".$this->moduleName."_contacts SET contact_customer = $customerId
                                                                       WHERE  id = '".intval($value)."'");
        }
    }    
   
    function updateCustomerMemberships($memberShips, $customerId) {
        global $objDatabase;

        $objDatabase->Execute("DELETE FROM `".DBPREFIX."module_{$this->moduleName}_customer_membership` WHERE contact_id = $customerId");
        foreach ($memberShips as $value) {
            $objDatabase->Execute("INSERT INTO `".DBPREFIX."module_{$this->moduleName}_customer_membership` SET
                                    membership_id = '$value',
                                    contact_id    = '$customerId'
                    ");
        }
    }

    function unlinkContact($contactId) {
        global $objDatabase;
        $objDatabase->Execute("UPDATE `".DBPREFIX."module_".$this->moduleName."_contacts` SET `contact_customer` = 0
                                                                       WHERE  id = '".intval($contactId)."'");
    }

    function validateCustomer($customerName = '', $customerId ='', $id = 0) {
        global $objDatabase;

        $customerName = contrexx_input2db(trim($customerName));
        $customerId   = contrexx_input2db(trim($customerId));
        $id           = (int) $id;

        $objResult = $objDatabase->Execute("SELECT 1 FROM `".DBPREFIX."module_{$this->moduleName}_customers`
                                                  WHERE (`customer_id`  = '$customerId' OR
                                                        `company_name` = '$customerName') AND
                                                         id != $id");
        if ($objResult) {
            if ($objResult->RecordCount() > 0)
                return false;
        }

        return TRUE;

    }

    function formattedWebsite($url = '', $urlProfile = 0) {
        switch ($urlProfile) {
            // linkedIn
            case 3:
                $formattedValue = "<a href='http://".preg_replace("`^http://`is", "", urldecode($url))."'>".urldecode($url)."</a>";
                break;
            // skype
            case 1:
                $formattedValue = "<a href='skype:".contrexx_raw2xhtml($url)."?chat'>".contrexx_raw2xhtml($url)."</a>";
                break;
            // livejournal, myspace, bologger, jabber,aim
            case 5:case 6:case 8: case 12: case 13:
                $formattedValue = contrexx_raw2xhtml($url);
                break;
            // gmail, yahoo, msn (mail)
            case 7:case 9:case 10:
                $formattedValue = "<a href='mailto:".contrexx_raw2xhtml($url)."'>".contrexx_raw2xhtml($url)."</a>";
                break;
            // twitter
            case 2:
                $formattedValue = "<a href='http://twitter.com/".contrexx_raw2xhtml($url)."'>".contrexx_raw2xhtml($url)."</a>";
                break;
            // facebook
            case 4:
                $formattedValue = "<a href='http://facebook.com/".contrexx_raw2xhtml($url)."'>".contrexx_raw2xhtml($url)."</a>";
                break;
            // icq
            case 11:
                $formattedValue = "<a href='http://icq.com/people/".contrexx_raw2xhtml($url)."'>".contrexx_raw2xhtml($url)."</a>";
                break;
            default:
                $formattedValue = "<a href='http://".preg_replace("`^http://`is", "", urldecode($url))."'>".urldecode($url)."</a>";
                break;
        }
        return $formattedValue;
    }

    function getSuccessRate($selectedRate = 0, $block = "sRate") {
        global  $objDatabase;
        $objRates = $objDatabase->Execute("SELECT id, label,rate
                                                FROM `".DBPREFIX."module_{$this->moduleName}_success_rate` ORDER BY sorting ASC");
        if ($objRates) {
            while (!$objRates->EOF) {
                $selected = ($objRates->fields['id'] == $selectedRate) ? "selected" : '';
                $this->_objTpl->setVariable(array(
                        'SRATE_VALUE'     => (int) $objRates->fields['id'],
                        'SRATE_NAME'      => "[".contrexx_raw2xhtml($objRates->fields['rate'])."&nbsp;&#37;]&nbsp;".contrexx_raw2xhtml($objRates->fields['label']),
                        'SRATE_SELECTED'  => $selected,
                ));
                $this->_objTpl->parse($block);
                $objRates->MoveNext();
            }

        }

    }

    function getDealsStages($selectedStage = 0, $block = "dealsStages") {
        global  $objDatabase;

        $objRates = $objDatabase->Execute("SELECT id, label,stage
                                                FROM `".DBPREFIX."module_{$this->moduleName}_stages` ORDER BY sorting ASC");
        if ($objRates) {
            while (!$objRates->EOF) {
                $selected = ($objRates->fields['id'] == $selectedStage) ? "selected" : '';
                $this->_objTpl->setVariable(array(
                        'STAGE_VALUE'     => (int) $objRates->fields['id'],
                        'STAGE_NAME'      => "[".contrexx_raw2xhtml($objRates->fields['stage'])."&nbsp;&#37;]&nbsp;".contrexx_raw2xhtml($objRates->fields['label']),
                        'STAGE_SELECTED'  => $selected,
                ));
                $this->_objTpl->parse($block);
                $objRates->MoveNext();
            }

        }

    }

        public function _getDomainNameId($websiteId, $cusId, $domainName) {
        global $objDatabase;

        if (empty($domainName)) {
            return 0;
        }

        $websiteId  = (int) $websiteId;
        $cusId      = (int) $cusId;
        $domainName = contrexx_input2db($domainName);
        $query = "SELECT
                        `id`
                    FROM `".DBPREFIX."module_{$this->moduleName}_customer_contact_websites`
                    WHERE (`url` = '$domainName')
                        AND `contact_id` = $cusId";
        $objResult = $objDatabase->Execute($query);

        if ($objResult->RecordCount() > 0) {
            return $objResult->fields['id'];
        } else {
            $insertWebsite = $objDatabase->Execute("INSERT INTO
                                                    `".DBPREFIX."module_{$this->moduleName}_customer_contact_websites`
                                                    SET `contact_id` = $cusId,
                                                        `url_type`   = 3,
                                                        `url_profile`= 1,
                                                        `is_primary` = '0',
                                                        `url`        = '".  contrexx_raw2encodedUrl($domainName)."'");
            return $objDatabase->Insert_Id();
        }
    }

    function activateSuccessRate($successEntrys, $deactivate = false) {
        global $objDatabase,$_ARRAYLANG;

        if (!empty($successEntrys) && is_array($successEntrys)) {

            $ids = implode(',',$successEntrys);
            $setValue = $deactivate ? 0 : 1;

            $query = "UPDATE `".DBPREFIX."module_".$this->moduleName."_success_rate` SET `status` = CASE id ";
            foreach ($successEntrys as $count => $idValue) {
                $query .= sprintf("WHEN %d THEN $setValue ", $idValue);
            }
            $query .= "END WHERE id IN ($ids)";
            $objResult = $objDatabase->Execute($query);

            if ($_GET['ajax']) {
                echo $_ARRAYLANG['TXT_CATALOGS_UPDATED_SUCCESSFULLY'];
                exit();
            } else {
                $this->strOkMessage = sprintf($_ARRAYLANG['TXT_CATALOGS_UPDATED_SUCCESSFULLY'], ($deactivate) ? $_ARRAYLANG['TXT_DEACTIVATED'] : $_ARRAYLANG['TXT_ACTIVATED']);
            }
        }
    }

    function saveSortingSuccessRate($successEntrySorting) {
        global $objDatabase,$_ARRAYLANG;

        if (!empty($successEntrySorting) && is_array($successEntrySorting)) {

            $ids = implode(',',array_keys($successEntrySorting));

            $query = "UPDATE `".DBPREFIX."module_".$this->moduleName."_success_rate` SET `sort` = CASE id ";
            foreach ($successEntrySorting as $idValue => $value ) {
                $query .= sprintf("WHEN %d THEN %d ", $idValue, $value);
            }
            $query .= "END WHERE id IN ($ids)";
            $objResult = $objDatabase->Execute($query);

        }
    }

    function deleteSuccessRates($successEntries) {
        global $objDatabase;

        if (!empty($successEntries) && is_array($successEntries)) {

            $ids = implode(',',$successEntries);

            $query = "DELETE FROM `".DBPREFIX."module_".$this->moduleName."_success_rate` WHERE id IN ($ids)";
            $objResult = $objDatabase->Execute($query);

        }
    }

    function deleteSuccessRate() {
        global $objDatabase;

        $id     = (int) $_GET['id'];
        $query = "DELETE FROM `".DBPREFIX."module_{$this->moduleName}_success_rate`
                        WHERE id = $id";
        $db = $objDatabase->Execute($query);

    }

    function activateIndustryType($industryEntrys, $deactivate = false) {
        global $objDatabase,$_ARRAYLANG;

        if (!empty($industryEntrys) && is_array($industryEntrys)) {

            $ids = implode(',',$industryEntrys);
            $setValue = $deactivate ? 0 : 1;

            $query = "UPDATE `".DBPREFIX."module_".$this->moduleName."_industry_types` SET `status` = CASE id ";
            foreach ($industryEntrys as $count => $idValue) {
                $query .= sprintf("WHEN %d THEN $setValue ", $idValue);
            }
            $query .= "END WHERE id IN ($ids)";
            $objResult = $objDatabase->Execute($query);

            if ($_GET['ajax']) {
                exit();
            } else {                
                $this->_strOkMessage = sprintf($_ARRAYLANG['TXT_INDUSTRY_UPDATED_SUCCESSFULLY'], ($deactivate) ? $_ARRAYLANG['TXT_DEACTIVATED'] : $_ARRAYLANG['TXT_ACTIVATED']);
            }
        } else {
            $objDatabase->Execute("UPDATE `".DBPREFIX."module_".$this->moduleName."_industry_types` SET `status` = IF(status = 1, 0, 1) WHERE id = $industryEntrys");
        }
    }

    function saveSortingIndustryType($industryEntrySorting) {
        global $objDatabase,$_ARRAYLANG;

        if (!empty($industryEntrySorting) && is_array($industryEntrySorting)) {

            $ids = implode(',',array_keys($industryEntrySorting));

            $query = "UPDATE `".DBPREFIX."module_".$this->moduleName."_industry_types` SET `sorting` = CASE id ";
            foreach ($industryEntrySorting as $idValue => $value ) {
                $query .= sprintf("WHEN %d THEN %d ", $idValue, $value);
            }
            $query .= "END WHERE id IN ($ids)";
            $objResult = $objDatabase->Execute($query);

        }
    }

    function deleteIndustryTypes($indusEntries) {
        global $objDatabase;

        if (!empty($indusEntries) && is_array($indusEntries)) {

            $ids = implode(',',$indusEntries);

            $query = "DELETE FROM `".DBPREFIX."module_".$this->moduleName."_industry_types` WHERE id IN ($ids)";
            $objResult = $objDatabase->Execute($query);

        }
    }

    function deleteIndustryType() {
        global $objDatabase;

        $id     = (int) $_GET['id'];
        $query = "DELETE FROM `".DBPREFIX."module_{$this->moduleName}_industry_types`
                        WHERE id = $id";
        $db = $objDatabase->Execute($query);

    }

    function activateMembership($entries, $deactivate = false) {
        global $objDatabase,$_ARRAYLANG;

        if (!empty($entries) && is_array($entries)) {

            $ids = implode(',',$entries);
            $setValue = $deactivate ? 0 : 1;

            $query = "UPDATE `".DBPREFIX."module_".$this->moduleName."_memberships` SET `status` = CASE id ";
            foreach ($entries as $count => $idValue) {
                $query .= sprintf("WHEN %d THEN $setValue ", $idValue);
            }
            $query .= "END WHERE id IN ($ids)";
            $objResult = $objDatabase->Execute($query);

            if ($_GET['ajax']) {
                exit();
            } else {                
                $_SESSION['strOkMessage'] = sprintf($_ARRAYLANG['TXT_MEMBERSHIP_UPDATED_SUCCESSFULLY'], ($deactivate) ? $_ARRAYLANG['TXT_DEACTIVATED'] : $_ARRAYLANG['TXT_ACTIVATED']);
            }
        } else {
            $objDatabase->Execute("UPDATE `".DBPREFIX."module_".$this->moduleName."_memberships` SET `status` = IF(status = 1, 0, 1) WHERE id = $entries");
        }
    }

    function saveSortingMembership($entriesSorting) {
        global $objDatabase,$_ARRAYLANG;

        if (!empty($entriesSorting) && is_array($entriesSorting)) {

            $ids = implode(',',array_keys($entriesSorting));

            $query = "UPDATE `".DBPREFIX."module_".$this->moduleName."_memberships` SET `sorting` = CASE id ";
            foreach ($entriesSorting as $idValue => $value ) {
                $query .= sprintf("WHEN %d THEN %d ", $idValue, $value);
            }
            $query .= "END WHERE id IN ($ids)";
            $objResult = $objDatabase->Execute($query);

        }
        if (isset($_POST['save_entries'])) {
            $_SESSION['strOkMessage'] = $_ARRAYLANG['TXT_SORTING_COMPLETE'];
        }
    }

    function deleteMemberships($entries) {
        global $objDatabase, $_ARRAYLANG;

        if (!empty($entries) && is_array($entries)) {

            $ids = implode(',',$entries);

            $query = "DELETE m.*, ml.* FROM `".DBPREFIX."module_{$this->moduleName}_memberships` AS m
                                   LEFT JOIN `".DBPREFIX."module_{$this->moduleName}_membership_local` AS ml
                                   ON m.id = ml.entry_id
                        WHERE id IN ($ids)";
            $objResult = $objDatabase->Execute($query);

        }
        if ($_GET['ajax']) {
            exit();
        } else {
            $_SESSION['strOkMessage'] = $_ARRAYLANG['TXT_MEMBERSHIP_DELETED_SUCCESSFULLY'];
        }
        
    }

    function deleteMembership() {
        global $objDatabase;

        $id     = (int) $_GET['id'];
        $query  = "DELETE m.*, ml.* FROM `".DBPREFIX."module_{$this->moduleName}_memberships` AS m
                                   LEFT JOIN `".DBPREFIX."module_{$this->moduleName}_membership_local` AS ml
                                   ON m.id = ml.entry_id
                        WHERE id = $id";
        $db = $objDatabase->Execute($query);

    }

    function activateStage($successEntrys, $deactivate = false) {
        global $objDatabase,$_ARRAYLANG;

        if (!empty($successEntrys) && is_array($successEntrys)) {

            $ids = implode(',',$successEntrys);
            $setValue = $deactivate ? 0 : 1;

            $query = "UPDATE `".DBPREFIX."module_".$this->moduleName."_stages` SET `status` = CASE id ";
            foreach ($successEntrys as $count => $idValue) {
                $query .= sprintf("WHEN %d THEN $setValue ", $idValue);
            }
            $query .= "END WHERE id IN ($ids)";
            $objResult = $objDatabase->Execute($query);

            if ($_GET['ajax']) {
                echo $_ARRAYLANG['TXT_CATALOGS_UPDATED_SUCCESSFULLY'];
                exit();
            } else {
                $this->strOkMessage = sprintf($_ARRAYLANG['TXT_CATALOGS_UPDATED_SUCCESSFULLY'], ($deactivate) ? $_ARRAYLANG['TXT_DEACTIVATED'] : $_ARRAYLANG['TXT_ACTIVATED']);
            }
        }
    }

    function saveStageSorting($successEntrySorting) {
        global $objDatabase,$_ARRAYLANG;

        if (!empty($successEntrySorting) && is_array($successEntrySorting)) {

            $ids = implode(',',array_keys($successEntrySorting));

            $query = "UPDATE `".DBPREFIX."module_".$this->moduleName."_stages` SET `sorting` = CASE id ";
            foreach ($successEntrySorting as $idValue => $value ) {
                $query .= sprintf("WHEN %d THEN %d ", $idValue, $value);
            }
            $query .= "END WHERE id IN ($ids)";
            $objResult = $objDatabase->Execute($query);

        }
    }

    function deleteStages($successEntries) {
        global $objDatabase;

        if (!empty($successEntries) && is_array($successEntries)) {

            $ids = implode(',',$successEntries);

            $query = "DELETE FROM `".DBPREFIX."module_".$this->moduleName."_stages` WHERE id IN ($ids)";
            $objResult = $objDatabase->Execute($query);

        }
    }

    function deleteStage() {
        global $objDatabase;

        $id     = (int) $_GET['id'];
        $query = "DELETE FROM `".DBPREFIX."module_{$this->moduleName}_stages`
                        WHERE id = $id";
        $db = $objDatabase->Execute($query);

    }

    function deleteDeals($dealsEntries, $deleteProjects = false) {
        global $objDatabase;

        if (!empty($dealsEntries) && is_array($dealsEntries)) {

            $ids = implode(',',$dealsEntries);

            // cahnge project to deleted status if pm module integrated
            if($deleteProjects) {
                $deletedStatusId = $objDatabase->getOne("SELECT projectstatus_id FROM ".DBPREFIX."module_".$this->pm_moduleName."_project_status WHERE deleted = 1");
                $objProjects     = $objDatabase->Execute("SELECT project_id FROM `".DBPREFIX."module_".$this->moduleName."_deals` WHERE id IN ($ids)");

                $projectToBeDeleted = array();
                if ($objProjects) {
                    while(!$objProjects->EOF) {
                        $projectToBeDeleted[] = (int) $objProjects->fields['project_id'];
                        $objProjects->MoveNext();
                    }
                    $projectIds = implode(',', $projectToBeDeleted);
                    $updateProjectStatus = $objDatabase->Execute("UPDATE `".DBPREFIX."module_{$this->pm_moduleName}_projects`
                                                                    SET `status`    = '$deletedStatusId'
                                                                    WHERE id IN  ($projectIds)");
                }
            }
            $query = "DELETE FROM `".DBPREFIX."module_".$this->moduleName."_deals` WHERE id IN ($ids)";
            $objResult = $objDatabase->Execute($query);

        }
        $message = base64_encode("dealsdeleted");
        csrf::header("location:".ASCMS_ADMIN_WEB_PATH."/index.php?cmd=".$this->moduleName."&act=deals&mes=$message");
    }

    function deleteDeal($deleteProjects = false) {
        global $objDatabase;

        $id     = (int) $_GET['id'];

        // cahnge project to deleted status if pm module integrated
        if($deleteProjects) {
            $deletedStatusId = $objDatabase->getOne("SELECT projectstatus_id FROM ".DBPREFIX."module_".$this->pm_moduleName."_project_status WHERE deleted = 1");
            $objProjects     = $objDatabase->Execute("SELECT project_id FROM `".DBPREFIX."module_".$this->moduleName."_deals` WHERE id = $id");

            if ($objProjects) {
                $projectId = (int) $objProjects->fields['project_id'];
                $updateProjectStatus = $objDatabase->Execute("UPDATE `".DBPREFIX."module_{$this->pm_moduleName}_projects`
                                                                SET `status`    = '$deletedStatusId'
                                                                WHERE id = $projectId");
            }
        }
        $query = "DELETE FROM `".DBPREFIX."module_{$this->moduleName}_deals`
                        WHERE id = $id";
        $db = $objDatabase->Execute($query);

    }

    /**
     * Populates the Contrexx user Filter Drop Down
     * @param $block String The name of the template block to parse
     * @param $selectedId Integer The ID of the selected user
     */
    function _getResourceDropDown($block= 'members', $selectedId=0, $groupId = 0) {
        $resources = $this->getResources($groupId);
        foreach ($resources as $resource) {
            $selected = $selectedId ==  $resource['id'] ? 'selected="selected"' : '';

            $this->_objTpl->setVariable(array(
                    'TXT_USER_MEMBERID'   => $resource['id'],
                    'TXT_USER_MEMBERNAME' => $resource['username'],
                    'TXT_SELECTED'        => $selected));
            $this->_objTpl->parse($block);
        }
    }

    function getResources($groupId) {
        global $objDatabase;
        static $resources = array();

        if (!empty($resources)) {
            return $resources;
        }
        if (empty ($groupId)) {
            return false;
        }

        $query = "SELECT u.id,
                         u.username,
                         u.email
                  FROM ".DBPREFIX."access_rel_user_group As g
                    LEFT JOIN ".DBPREFIX."access_users u ON u.id = g.user_id
                  WHERE u.active = 1 AND g.group_id = '".$groupId."'
                  ORDER BY u.username";

        $result = $objDatabase->execute($query);

        if (false !== $result) {
            while (!$result->EOF) {
                $resources[] = array(
                        'id'       => $result->fields['id'],
                        'username' => $result->fields['username'],
                        'email'    => $result->fields['email'],
                );
                $result->moveNext();
            }
            return $resources;
        }
        return false;
    }

    function getDatasourceDropDown($objTpl, $block= 'datasource', $selectedId = 0) {
        $datasources = $this->getCrmDatasource();

        foreach ($datasources as $id => $datasource) {
            $selected = $id == $selectedId ? 'selected' : '';

            $objTpl->setvariable(array(
                'CRM_DATASOURCE_ID'       => (int) $id,
                'CRM_DATASOURCE_VALUE'    => contrexx_raw2xhtml($datasource['datasource']),
                'CRM_DATASOURCE_SELECTED' => $selected
            ));
            $objTpl->parse($block);
        }        
    }
    
    function getCrmDatasource() {
        global $objDatabase;

        static $datasource = array();

        if (!empty($datasource)) {
            return $datasource;
        }
        
        $objResult = $objDatabase->Execute("SELECT `id`, `datasource`, `status`  FROM `". DBPREFIX ."module_{$this->moduleName}_datasources`");

        if ($objResult) {
            while (!$objResult->EOF) {
                $datasource[$objResult->fields['id']] = $objResult->fields;
                $objResult->MoveNext();
            }
        }

        return $datasource;
    }
    
    function  getMemberships($active = true) {
        global $objDatabase, $_LANGID;

        $status = ($active) ? ' AND status = 1' : '';
        $memberships = array();
        $objResult = $objDatabase->Execute("SELECT membership.*,
                                                   memberLoc.value
                                             FROM `".DBPREFIX."module_{$this->moduleName}_memberships` AS membership
                                             LEFT JOIN `".DBPREFIX."module_{$this->moduleName}_membership_local` AS memberLoc
                                                ON membership.id = memberLoc.entry_id
                                             WHERE memberLoc.lang_id = ".$_LANGID." $status ORDER BY sorting ASC");
        if ($objResult) {
            while (!$objResult->EOF) {
                $memberships[$objResult->fields['id']] = $objResult->fields['value'];
                $objResult->MoveNext();
            }
        }

        $this->_memberShips = $memberships;
        return $memberships;
    }

    function listIndustryTypes($objTpl, $intView, $intIndustryId=null, $arrParentIds=null) {
        global $_ARRAYLANG, $objDatabase;

        if (!isset($this->model_industry_types))
            $this->model_industry_types = $this->load->model('IndustryType', __CLASS__);
        if (!isset($this->model_industry_types->arrIndustryTypes))
            $this->model_industry_types->arrIndustryTypes = $this->model_industry_types->getIndustryTypes(null, null, true);

        if(!isset($arrParentIds)) {
            $arrIndustries = $this->model_industry_types->arrIndustryTypes;
        } else {
            $arrChildren = $this->model_industry_types->arrIndustryTypes;

            foreach ($arrParentIds as $key => $intParentId) {
                $arrChildren = $arrChildren[$intParentId]['children'];
            }
            $arrIndustries = $arrChildren;
        }

        switch ($intView) {
            case 1:
                //backend overview page
                foreach ($arrIndustries as $key => $arrIndustry) {
                    //generate space
                    $spacer = null;
                    $intSpacerSize = null;
                    $intSpacerSize = (count($arrParentIds)*21);
                    $spacer .= '<img src="images/icons/pixel.gif" border="0" width="'.$intSpacerSize.'" height="11" alt="" />';

                    //parse variables
                    $activeImage = ($arrIndustry['status']) ? 'images/icons/led_green.gif' : 'images/icons/led_red.gif';
                    $objTpl->setVariable(array(
                            'ENTRY_ID'           => $arrIndustry['id'],
                            'CRM_SORTING'        => (int) $arrIndustry['sorting'],
                            'CRM_SUCCESS_STATUS' => $activeImage,
                            'CRM_INDUSTRY_ICON'  => $spacer,
                            'CRM_INDUSTRY_NAME'  => contrexx_raw2xhtml($arrIndustry['name'])
                    ));
                    $objTpl->parse('industryEntries');

                    $arrParentIds[] = $arrIndustry['id'];
                    
                    //get children
                    if(!empty($arrIndustry['children'])){                        
                        $this->listIndustryTypes($objTpl, 1, $intIndustryId, $arrParentIds);
                    }

                    @array_pop($arrParentIds);
                }
                break;
            case 2: // Industry Drop down menu

                $strDropdownOptions = '';
                foreach ($arrIndustries as $key => $arrIndustry) {
                    $spacer = null;
                    $intSpacerSize = null;

                    if($arrIndustry['id'] == $intIndustryId) {
                        $strSelected = 'selected="selected"';
                    } else {
                        $strSelected = '';
                    }

                    //generate space
                    $intSpacerSize = (count($arrParentIds));
                    for($i = 0; $i < $intSpacerSize; $i++) {
                        $spacer .= "----";
                    }

                    if($spacer != null) {
                    	$spacer .= "&nbsp;";
                    }

                    $strDropdownOptions .= '<option value="'.$arrIndustry['id'].'" '.(($arrIndustry['status']) ? "" : "style='color:#FF7B7B'").' '.$strSelected.' >'.$spacer.contrexx_raw2xhtml($arrIndustry['name']).'</option>';

                    if(!empty($arrIndustry['children'])) {
                        $arrParentIds[] = $arrIndustry['id'];
                        $strDropdownOptions .= $this->listIndustryTypes($objTpl, 2, $intIndustryId, $arrParentIds);
                        @array_pop($arrParentIds);
                    }
                }

                return $strDropdownOptions;
            break;
        }
    }

    function getMembershipDropdown($objTpl, $memberShips, $block = "assignedGroup", $selected = array()) {

        if (!is_array($selected)) {
            return ;
        }

        foreach ($memberShips as $id) {
            $selectedVal = in_array($id, $selected) ? 'selected' : '';
            
            $objTpl->setVariable(array(
                    "CRM_MEMBERSHIP_ID"         => (int) $id,
                    "CRM_MEMBERSHIP_VALUE"      => contrexx_raw2xhtml($this->_memberShips[$id]),
                    "CRM_MEMBERSHIP_SELECTED"   => $selectedVal
            ));
            $objTpl->parse($block);
        }
    }

    function getOverviewMembershipDropdown($objTpl, $modelMembership, $selected = 0, $block = "memberships") {
        $data = array(
                'status = 1'
        );
        $result = $modelMembership->findAllByLang($data);
        while (!$result->EOF) {
            $objTpl->setVariable(array(
                    "CRM_MEMBERSHIP_ID"         => (int) $result->fields['id'],
                    "CRM_MEMBERSHIP_VALUE"      => contrexx_raw2xhtml($result->fields['value']),
                    "CRM_MEMBERSHIP_SELECTED"   => ($result->fields['id'] == $selected) ? "selected='selected'" : '',
            ));
            $objTpl->parse($block);
            $result->MoveNext();
        }
    }

    function addUser($email, $password, $sendLoginDetails = false) {
        global $objDatabase, $_CORELANG;
        
        $settings = $this->getSettings();

        if (!isset($this->contact))
            $this->contact = $this->load->model('crmContact', __CLASS__);

        $objFWUser = FWUser::getFWUserObject();

        $modify = isset($this->contact->id) && !empty($this->contact->id);
        $accountId = 0;
        $objResult = $objDatabase->Execute("
                                        SELECT `id`
                                          FROM ".DBPREFIX."access_users
                                         WHERE email='".addslashes($email)."'");
        if ($objResult && $objResult->RecordCount()) {
            $accountId = $objResult->fields['id'];
        }

        if ($modify) {
            $this->contact->account_id = $objDatabase->getOne("SELECT user_account FROM `".DBPREFIX."module_{$this->moduleName}_contacts` WHERE id = {$this->contact->id}");
            if (empty ($this->contact->account_id) && !empty($accountId)) {
                $objUser = new User($accountId);
            } elseif ((!empty($this->contact->account_id) && $objUser = $objFWUser->objUser->getUser($this->contact->account_id)) === false) {
                $objUser = new User();
            }
        } else {
            if (empty($accountId)){
                $objUser = new User();                                
            } else {
                $userExists = $objDatabase->SelectLimit("SELECT 1 FROM `".DBPREFIX."module_{$this->moduleName}_contacts` WHERE user_account = {$accountId}", 1);
                if ($userExists && $userExists->RecordCount() == 0) {
                    $objUser    = $objFWUser->objUser->getUser($accountId);
                } else {
                    $this->_strErrMessage = $_CORELANG['TXT_ACCESS_EMAIL_ALREADY_USED'];
                    return  false;
                }                
            }
        }

        $objUser->setUsername($email);
        $objUser->setPassword($password);
        $objUser->setEmail($email);
        $objUser->setGroups((array) $settings['default_user_group']);
        $objUser->setFrontendLanguage($settings['customer_default_language_frontend']);
        $objUser->setBackendLanguage($settings['customer_default_language_backend']);
        $objUser->setActiveStatus(true);
        $objUser->setProfile(array(
            'firstname'    => array(0 => $this->contact->customerName),
            'lastname'     => array(0 => $this->contact->family_name),
        ));
        
        if ($objUser->store()) {            
            if (empty($this->contact->account_id) && $sendLoginDetails) {
                $info['substitution'] = array(
                        'CRM_CUSTOMER_COMPANY'           => $this->contact->customerName." ".$this->contact->family_name,
                        'CRM_CUSTOMER_CONTACT_EMAIL'     => $defaultEmail,
                        'CRM_CUSTOMER_CONTACT_USER_NAME' => $this->contact->user_name,
                        'CRM_CUSTOMER_CONTACT_PASSWORD'  => $passWord,
                );
                $dispatcher = EventDispatcher::getInstance();
                $dispatcher->triggerEvent(CRM_EVENT_ON_USER_ACCOUNT_CREATED, null, $info);
            }
            $this->contact->account_id = $objUser->getId();
            
            return true;
        } else {            
            $objUser->reset();
            $this->_strErrMessage = implode("<br />", $objUser->error_msg);
            return  false;
        }
        
        $this->_strErrMessage = 'Some thing went wrong';
        return false;
    }

    function addCrmContact($arrFormData = array())
    {
        global $objDatabase;

        $this->contact = $this->load->model('crmContact', __CLASS__);

        $fieldValues = array();
        foreach ($arrFormData['fields'] as $key => $value) {
            $fieldName = $arrFormData['fields'][$key]['special_type'];
            $fieldValue = $arrFormData['data'][$key];
            $fieldValues[$fieldName] = $fieldValue;
        }

        if (!empty ($fieldValues['access_email'])) {
            $objEmail = $objDatabase->Execute("
                                        SELECT `id`
                                          FROM ".DBPREFIX."access_users
                                         WHERE email='".addslashes($fieldValues['access_email'])."'");

            $this->contact->customerName   = !empty ($fieldValues['access_firstname']) ? contrexx_input2raw($fieldValues['access_firstname']) : '';
            $this->contact->family_name    = !empty ($fieldValues['access_lastname']) ? contrexx_input2raw($fieldValues['access_lastname']) : '';
            $this->contact->contact_gender = (!empty ($fieldValues['access_gender']) && $fieldValues['access_gender'] == 'female') ? 1 : (!empty ($fieldValues['access_gender']) && $fieldValues['access_gender'] == 'male') ? 2 : '';
            
            $this->contact->contactType    = 2;
            $this->contact->datasource     = 2;

            if ($objEmail && $objEmail->RecordCount()) {
                $accountId = $objEmail->fields['id'];
                $userExists = $objDatabase->SelectLimit("SELECT 1 FROM `".DBPREFIX."module_{$this->moduleName}_contacts` WHERE user_account = {$accountId}", 1);
                
                if ($userExists && $userExists->RecordCount() == 0) {
                    $this->contact->account_id     = $accountId;            
                }
            }
            
            if ($this->contact->save()) {
                
                //insert email
                $query = "INSERT INTO `".DBPREFIX."module_{$this->moduleName}_customer_contact_emails` SET
                                email      = '". contrexx_input2db($fieldValues['access_email']) ."',
                                email_type = '0',
                                is_primary = '1',
                                contact_id = '{$this->contact->id}'";
                $objDatabase->Execute($query);
                // insert website
                if (!empty ($fieldValues['access_website'])) {
                    $fields = array(                        
                        'url'           => $fieldValues['access_website'],
                        'url_profile'   => 1,
                        'is_primary'    => 1,
                        'contact_id'    => $this->contact->id
                    );
                    $query  = SQL::insert("module_{$this->moduleName}_customer_contact_websites", $fields);
                    $db = $objDatabase->Execute($query);
                }

                //insert address
                if (!empty ($fieldValues['access_address']) || !empty ($fieldValues['access_city']) || !empty ($fieldValues['access_zip']) || !empty ($fieldValues['access_country'])) {
                    
                    $query = "INSERT INTO `".DBPREFIX."module_{$this->moduleName}_customer_contact_address` SET
                                    address      = '". contrexx_input2db($fieldValues['access_address']) ."',
                                    city         = '". contrexx_input2db($fieldValues['access_city']) ."',
                                    state        = '". contrexx_input2db($fieldValues['access_state']) ."',
                                    zip          = '". contrexx_input2db($fieldValues['access_zip']) ."',
                                    country      = '". contrexx_input2db($fieldValues['access_country']) ."',,
                                    Address_Type = '2',
                                    is_primary   = '1',
                                    contact_id   = '{$this->contact->id}'";

                    $objDatabase->Execute($query);
                }

                // insert Phone
                $contactPhone = array();
                if (!empty($fieldValues['access_phone_office'])) {
                    $contactPhone[] = array(
                        'value'   => $fieldValues['access_phone_office'],
                        'type'    => 1,
                        'primary' => 1
                    );
                }
                if (!empty($fieldValues['access_phone_private'])) {
                    $contactPhone[] = array(
                        'value'   => $fieldValues['access_phone_private'],
                        'type'    => 4,
                        'primary' => 1
                    );
                }
                if (!empty($fieldValues['access_phone_mobile'])) {
                    $contactPhone[] = array(
                        'value'   => $fieldValues['access_phone_mobile'],
                        'type'    => 2,
                        'primary' => 1
                    );
                }
                if (!empty($fieldValues['access_phone_fax'])) {
                    $contactPhone[] = array(
                        'value'   => $fieldValues['access_phone_fax'],
                        'type'    => 3,
                        'primary' => 1
                    );
                }
                if (!empty($contactPhone)) {
                    $query = "INSERT INTO `".DBPREFIX."module_{$this->moduleName}_customer_contact_phone` (phone, phone_type, is_primary, contact_id) VALUES ";

                    foreach ($contactPhone as $value) {
                        $values[] = "('".contrexx_input2db($value['value'])."', '".(int) $value['type']."', '".(int) $value['primary']."', '".$this->contact->id."')";
                    }

                    $query .= implode(",", $values);
                    $objDatabase->Execute($query);
                }
                // notify the staff's
                $this->notifyStaffOnContactAccModification($this->contact->id, $this->contact->customerName.' '.$this->contact->family_name);
            }            
        }
    }

    /**
     * notify the staffs regarding the account modification of a contact
     *
     * @access public
     * @global object $objTemplate
     * @global array $_ARRAYLANG
     */
    public function notifyStaffOnContactAccModification($customerId = 0, $customer_name = '')
    {
        global $objDatabase, $_ARRAYLANG;

        if (empty($customerId)) return false;

        $resources = $this->getResources($this->_arrSettings['emp_default_user_group']);
        $emails    = array();
        foreach ($resources as $key => $value) {
            $emails[]    = $value['email'];
        }

        if (!empty ($emails)) {
            $info['substitution'] = array(
                    'CRM_ASSIGNED_USER_EMAIL'           => implode(',', $emails),
                    'CRM_CONTACT_DETAILS_LINK'          => "<a href='". ASCMS_PROTOCOL."://{$_SERVER['HTTP_HOST']}". ASCMS_ADMIN_WEB_PATH ."/index.php?cmd={$this->moduleName}&act=showcustdetail&id=$customerId'>".$customer_name."</a>"
            );

            $dispatcher = EventDispatcher::getInstance();
            $dispatcher->triggerEvent(CRM_EVENT_ON_ACCOUNT_UPDATED, null, $info);
        }        
    }

     /**
     * Escape a value that it could be inserted into a csv file.
     *
     * @param string $value
     * @return string
     */
    function _escapeCsvValue($value) {

        $csvSeparator = $this->_csvSeparator;
        $value = in_array(strtolower(CONTREXX_CHARSET), array('utf8', 'utf-8')) ? utf8_decode($value) : $value;
        $value = preg_replace('/\r\n/', "\n", $value);
        $valueModified = str_replace('"', '""', $value);

        if ($valueModified != $value || preg_match('/['.$csvSeparator.'\n]+/', $value)) {
            $value = '"'.$valueModified.'"';
        }
        return $value;
    }
    
    /**
     * Returns true if the given $username is valid
     * @param   string    $username
     * @return  boolean
     * @static
     */
    protected function isValidUsername($username)
    {
        if (preg_match('/^[a-zA-Z0-9-_]+$/', $username)) {
            return true;
        }

        if (FWValidator::isEmail($username)) {
            return true;
        }
        return false;
    }

    /**
     * Returns true if $username is a unique user name
     *
     * Returns false if the test for uniqueness fails, or if the $username
     * exists already.
     * If non-empty, the given User ID is excluded from the search, so the
     * User does not match herself.
     * @param   string    $username   The username to test
     * @param   integer   $id         The optional current User ID
     * @return  boolean               True if the username is available,
     *                                false otherwise
     */
    protected function isUniqueUsername($email, $id=0)
    {
        global $objDatabase;
        
        $objResult = $objDatabase->SelectLimit("
                                            SELECT id
                                              FROM ".DBPREFIX."access_users
                                             WHERE email='".addslashes($email)."'
                                               AND id != $id", 1);
        return intval($objResult->fields['id']);
    }

    /**
     * Registers all css and js to be loaded for crm module
     *              
     */
    public function _initCrmModule()
    {
        JS::registerJS("lib/javascript/crm/main.js");        
        JS::registerCSS("lib/javascript/crm/css/main.css");
    }

}


// Loader will access class singleton and set object
class loader {
    function model($model_name, $class) {
        require(CRM_MODULE_LIB_PATH.'models/'.$model_name.'.php');
        $class::init()->$model_name = new $model_name;
        return $class::init()->$model_name;
    }

    function controller($controller_name, $class, $objTpl) {
        require(CRM_MODULE_LIB_PATH.'controllers/'.$controller_name.'.class.php');
        $class::init()->$controller_name = new $controller_name($objTpl);
        return $class::init()->$controller_name;
    }
}