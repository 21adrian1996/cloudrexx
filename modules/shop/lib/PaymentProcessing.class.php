<?php

/**
 * Payment Service Provider class
 * @package     contrexx
 * @subpackage  module_shop
 * @copyright   CONTREXX CMS - COMVATION AG
 * @version     3.0.0
 * @author      Reto Kohli <reto.kohli@comvation.com> (parts)
 */

/**
 * Debug mode
 */
define('_PAYMENT_DEBUG', 0);

/**
 * Payment logo folder (e.g. /modules/shop/images/payments/)
 */
define('SHOP_PAYMENT_LOGO_PATH', '/modules/shop/images/payments/');

/**
 * Payment Service Provider manager
 *
 * These are the requirements of the current specification
 * for any external payment service provider class:
 * - Any payment method *MUST* be implemented in its own class, with its
 *   constructor and/or methods being called from PaymentProcessing.class.php
 *   using only the two methods checkIn() and checkOut().
 * - Any data needed by the payment service class *MUST* be provided
 *   as arguments to the constructor and/or methods from within the
 *   PaymentProcessing class.
 * - Any code in checkOut() *MUST* return either a valid payment form *OR*
 *   redirect to a payment page of that provider, supplying all necessary
 *   data for a successful payment.
 * - Any code in checkIn() *MUST* return the original order ID of the order
 *   being processed on success, false otherwise (both in the case of failure
 *   and upon cancelling the payment).
 * - A payment provider class *MUST NOT* access the database itself, in
 *   particular it is strictly forbidden to read or change the order status
 *   of any order.
 * - A payment provider class *MUST NOT* store any data in the global session
 *   array.  Instead, it is to rely on the protocol of the payment service
 *   provider to transmit and retrieve all necessary data.
 * - Any payment method providing different return values for different
 *   outcomes of the payment in the consecutive HTTP requests *SHOULD* use
 *   the follwing arguments and values:
 *      Successful payments:            result=1
 *      Successful payments, silent *:  result=-1
 *      Failed payments:                result=0
 *      Aborted payments:               result=2
 *      Aborted payments, silent *:     result=-2
 *   * Some payment services do not only redirect the customer after a
 *     successful payment has completed, but already after the payment
 *     has been authorized.  Yellowpay, as an example, expects an empty
 *     page as a reply to such a request.
 *     Other PSP send the notification even for failed or cancelled
 *     transactions, e.g. Datatrans.  Consult your local PSP for further
 *     information.
 * @package     contrexx
 * @subpackage  module_shop
 * @author      Reto Kohli <reto.kohli@comvation.com> (parts)
 * @copyright   CONTREXX CMS - COMVATION AG
 * @version     3.0.0
 */
class PaymentProcessing
{
    /**
     * Array of all available payment processors
     * @access  private
     * @static
     * @var     array
     */
    private static $arrPaymentProcessor = false;

    /**
     * The selected processor ID
     * @access  private
     * @static
     * @var     integer
     */
    private static $processorId = false;


    /**
     * Initialize known payment service providers
     */
    static function init()
    {
        global $objDatabase;

        $query = '
            SELECT id, type, name, description,
                   company_url, status, picture
              FROM '.DBPREFIX.'module_shop_payment_processors
          ORDER BY id';
        $objResult = $objDatabase->Execute($query);
        while (!$objResult->EOF) {
            self::$arrPaymentProcessor[$objResult->fields['id']] = array(
                'id'          => $objResult->fields['id'],
                'type'        => $objResult->fields['type'],
                'name'        => $objResult->fields['name'],
                'description' => $objResult->fields['description'],
                'company_url' => $objResult->fields['company_url'],
                'status'      => $objResult->fields['status'],
                'picture'     => $objResult->fields['picture'],
            );
            $objResult->MoveNext();
        }
        // Verify version 3.0 complete data
        if (empty (self::$arrPaymentProcessor[11])) {
            self::errorHandler();
        }
    }


    /**
     * Set the active processor ID
     * @return  void
     * @param   integer $processorId    The PSP ID to use
     * @static
     */
    static function initProcessor($processorId)
    {
        if (!is_array(self::$arrPaymentProcessor)) self::init();
        self::$processorId = $processorId;
    }


    /**
     * Returns an array with all the payment processor names indexed
     * by their ID.
     * @return  array             The payment processor name array
     *                            on success, the empty array on failure.
     * @static
     */
    static function getPaymentProcessorNameArray()
    {
        if (empty(self::$arrPaymentProcessor)) self::init();
        $arrName = array();
        foreach (self::$arrPaymentProcessor as $id => $arrProcessor) {
            $arrName[$id] = $arrProcessor['name'];
        }
        return $arrName;
    }


    /**
     * Returns the name associated with a payment processor ID.
     *
     * If the optional argument is not set and greater than zero, the value
     * processorId stored in this object is used.  If this is invalid as
     * well, returns the empty string.
     * @param   integer     $processorId    The payment processor ID
     * @return  string                      The payment processors' name,
     *                                      or the empty string on failure.
     * @global  ADONewConnection
     * @static
     */
    static function getPaymentProcessorName($processorId=0)
    {
        // Either the argument or the class variable must be initialized
        if (!$processorId) $processorId = self::$processorId;
        if (!$processorId) return '';
        if (empty(self::$arrPaymentProcessor)) self::init();
        return self::$arrPaymentProcessor[$processorId]['name'];
    }


    /**
     * Returns the processor type associated with a payment processor ID.
     *
     * If the optional argument is not set and greater than zero, the value
     * processorId stored in this object is used.  If this is invalid as
     * well, returns the empty string.
     * Note: Currently supported types are 'internal' and 'external'.
     * @author  Reto Kohli <reto.kohli@comvation.com>
     * @param   integer     $processorId    The payment processor ID
     * @return  string                      The payment processor type,
     *                                      or the empty string on failure.
     * @global  ADONewConnection
     * @static
     */
    static function getCurrentPaymentProcessorType($processorId=0)
    {
        // Either the argument or the object may not be initialized
        if (!$processorId) $processorId = self::$processorId;
        if (!$processorId) return '';
        if (empty(self::$arrPaymentProcessor)) self::init();
        return self::$arrPaymentProcessor[$processorId]['type'];
    }


    /**
     * Returns the picture file name associated with a payment processor ID.
     *
     * If the optional argument is not set and greater than zero, the value
     * processorId stored in this object is used.  If this is invalid as
     * well, returns the empty string.
     * @param   integer     $processorId    The payment processor ID
     * @return  string                      The payment processors' picture
     *                                      file name, or the empty string
     *                                      on failure.
     * @global  ADONewConnection
     * @static
     */
    static function getPaymentProcessorPicture($processorId=0)
    {
        // Either the argument or the object may not be initialized
        if (!$processorId) $processorId = self::$processorId;
        if (!$processorId) return '';
        if (empty(self::$arrPaymentProcessor)) self::init();
        return self::$arrPaymentProcessor[$processorId]['picture'];
    }


    /**
     * Check out the payment processor associated with the payment processor
     * selected by {@link initProcessor()}.
     *
     * If the page is redirected, or has already been handled, returns the empty
     * string.
     * In the other cases, returns HTML code for the payment form and to insert
     * a picture representing the payment method.
     * @return  string      Empty string, or HTML code
     * @static
     */
    static function checkOut()
    {
        global $_ARRAYLANG;

        if (!is_array(self::$arrPaymentProcessor)) self::init();
        $return = '';
        switch (self::getPaymentProcessorName()) {
            case 'Internal':
                /* Redirect browser */
                CSRF::header('location: index.php?section=shop'.MODULE_INDEX.'&cmd=success&result=1&handler=Internal');
                exit;
            case 'Internal_LSV':
                /* Redirect browser */
                CSRF::header('location: index.php?section=shop'.MODULE_INDEX.'&cmd=success&result=1&handler=Internal');
                exit;
            case 'Internal_CreditCard':
                // Not implemented
                //$return = self::_Internal_CreditCardProcessor();
                CSRF::header('location: index.php?section=shop'.MODULE_INDEX.'&cmd=success&result=1&handler=Internal');
                break;
            case 'Internal_Debit':
                // Not implemented
                //$return = self::_Internal_DebitProcessor();
                CSRF::header('location: index.php?section=shop'.MODULE_INDEX.'&cmd=success&result=1&handler=Internal');
                break;
            case 'Saferpay':
            case 'Saferpay_All_Cards':
            case 'Saferpay_Mastercard_Multipay_CAR': // Obsolete
            case 'Saferpay_Visa_Multipay_CAR':  // Obsolete
                $return = self::_SaferpayProcessor();
                break;
            case 'yellowpay': // was: 'PostFinance_DebitDirect'
                $return = self::_YellowpayProcessor();
                break;
            // Added 20100222 -- Reto Kohli
            case 'mobilesolutions':
                $return = PostfinanceMobile::getForm(
                    intval(100 * $_SESSION['shop']['grand_total_price']),
                    $_SESSION['shop']['order_id']);
                if ($return) {
//DBG::log("Postfinance Mobile getForm() returned:");
//DBG::log($return);
                } else {
DBG::log("PaymentProcessing::checkOut(): ERROR: Postfinance Mobile getForm() failed");
DBG::log("Postfinance Mobile error messages:");
foreach (PostfinanceMobile::getErrors() as $error) {
DBG::log($error);
}
                }
                break;
            // Added 20081117 -- Reto Kohli
            case 'Datatrans':
                $return = self::getDatatransForm(Currency::getActiveCurrencyCode());
                break;
            case 'Paypal':
                $order_id = $_SESSION['shop']['order_id'];
                $account_email = SettingDb::getValue('paypal_account_email');
                $item_name = $_ARRAYLANG['TXT_SHOP_PAYPAL_ITEM_NAME'];
                $currency_code = Currency::getCodeById(
                    $_SESSION['shop']['currencyId']);
                $amount = $_SESSION['shop']['grand_total_price'];
                $return = PayPal::getForm($account_email, $order_id,
                    $currency_code, $amount, $item_name);
                break;
            case 'Dummy':
                $return = Dummy::getForm();
                break;
        }
        // shows the payment picture
        $return .= self::_getPictureCode();
        return $return;
    }


    /**
     * Returns HTML code for showing the logo associated with this
     * payment processor, if any, or an empty string otherwise.
     * @return  string      HTML code, or the empty string
     * @static
     */
    static function _getPictureCode()
    {
        if (!is_array(self::$arrPaymentProcessor)) self::init();
        $imageName = self::getPaymentProcessorPicture();
        if (empty($imageName)) return '';
        $imageName_lang = $imageName;
        $match = array();
        if (preg_match('/(\.\w+)$/', $imageName, $match))
            $imageName_lang = preg_replace(
                '/\.\w+$/', '_'.FRONTEND_LANG_ID.$match[1], $imageName);
//DBG::log("PaymentProcessing::_getPictureCode(): Testing path ".ASCMS_DOCUMENT_ROOT.SHOP_PAYMENT_LOGO_PATH.$imageName_lang);
        return
            '<br /><br /><img src="'.
            // Is there a language dependent version?
            (File::exists(SHOP_PAYMENT_LOGO_PATH.$imageName_lang)
              ? ASCMS_PATH_OFFSET.SHOP_PAYMENT_LOGO_PATH.$imageName_lang
              : ASCMS_PATH_OFFSET.SHOP_PAYMENT_LOGO_PATH.$imageName).
            '" alt="" title="" /><br /><br />';
    }


    /**
     * Returns the HTML code for the Saferpay payment form.
     * @param   array   $arrCards     The optional accepted card types
     * @return  string                The HTML code
     * @static
     */
    static function _SaferpayProcessor($arrCards=null)
    {
        global $_ARRAYLANG;

        $serverBase = $_SERVER['SERVER_NAME'].ASCMS_PATH_OFFSET.'/';
        $arrShopOrder = array(
            'AMOUNT'      => str_replace('.', '', $_SESSION['shop']['grand_total_price']),
            'CURRENCY'    => Currency::getActiveCurrencyCode(),
            'ORDERID'     => $_SESSION['shop']['order_id'],
            'ACCOUNTID'   => SettingDb::getValue('saferpay_id'),
            'SUCCESSLINK' =>
                'http://'.$serverBase.'index.php?section=shop'.MODULE_INDEX.
                '&cmd=success&result=1&handler=saferpay',
            'FAILLINK'    =>
                'http://'.$serverBase.'index.php?section=shop'.MODULE_INDEX.
                '&cmd=success&result=0&handler=saferpay',
            'BACKLINK'    =>
                'http://'.$serverBase.'index.php?section=shop'.MODULE_INDEX.
                '&cmd=success&result=2&handler=saferpay',
            'DESCRIPTION' =>
                '"'.$_ARRAYLANG['TXT_ORDER_NR'].
                ' '.$_SESSION['shop']['order_id'].'"',
            'LANGID'      => FWLanguage::getLanguageCodeById(FRONTEND_LANG_ID),
            'NOTIFYURL'   =>
                'http://'.$serverBase.'index.php?section=shop'.MODULE_INDEX.
                '&cmd=success&result=-1&handler=saferpay',
            'ALLOWCOLLECT' => 'no',
            'DELIVERY'     => 'no',
        );
// Obsolete
//        if ($arrCards) {
//            $arrShopOrder['PROVIDERSET'] = $arrCards;
//        }
        $payInitUrl = Saferpay::payInit($arrShopOrder,
            SettingDb::getValue('saferpay_use_test_account'));
//DBG::log("PaymentProcessing::_SaferpayProcessor(): payInit URL: $payInitUrl");
        // Fixed: Added check for empty return string,
        // i.e. on connection problems
        if (!$payInitUrl) {
            return
                "<font color='red'><b>".
                $_ARRAYLANG['TXT_SHOP_PSP_FAILED_TO_INITIALISE_SAFERPAY'].
                "<br />$payInitUrl</b></font>".
                "<br />".Saferpay::getErrors();
        }
        $return = "<script src='http://www.saferpay.com/OpenSaferpayScript.js'></script>\n";
        switch (SettingDb::getValue('saferpay_window_option')) {
            case 0: // iframe
                return
                    $return.
                    $_ARRAYLANG['TXT_ORDER_PREPARED']."<br/><br/>\n".
                    "<iframe src='$payInitUrl' width='580' height='400' scrolling='no' marginheight='0' marginwidth='0' frameborder='0' name='saferpay'></iframe>\n";
            case 1: // popup
                return
                    $return.
                    $_ARRAYLANG['TXT_ORDER_LINK_PREPARED']."<br/><br/>\n".
                    "<script type='text/javascript'>
                     function openSaferpay() {
                       strUrl = '$payInitUrl';
                       if (strUrl.indexOf(\"WINDOWMODE=Standalone\") == -1) {
                         strUrl += \"&WINDOWMODE=Standalone\";
                       }
                       oWin = window.open(strUrl, 'SaferpayTerminal',
                           'scrollbars=1,resizable=0,toolbar=0,location=0,directories=0,status=1,menubar=0,width=580,height=400'
                       );
                       if (oWin == null || typeof(oWin) == \"undefined\") {
                         alert(\"The payment couldn't be initialized.  It seems like you are using a popup blocker!\");
                       }
                     }
                     </script>\n".
                    "<input type='button' name='order_now' value='".
                    $_ARRAYLANG['TXT_ORDER_NOW'].
                    "' onclick='openSaferpay()' />\n";
            default: //case 2: // new window
        }
        return
            $return.
            $_ARRAYLANG['TXT_ORDER_LINK_PREPARED']."<br/><br/>\n".
            "<form method='post' action='$payInitUrl'>\n<input type='submit' value='".
            $_ARRAYLANG['TXT_ORDER_NOW'].
            "' />\n</form>\n";
    }


    /**
     * Returns the HTML code for the Yellowpay payment method.
     * @return  string  HTML code
     */
    static function _YellowpayProcessor()
    {
        global $_ARRAYLANG;

        $language = FWLanguage::getLanguageCodeById(FRONTEND_LANG_ID);
        $language = strtolower($language).'_'.strtoupper($language);
        $arrShopOrder = array(
// 20111227 - Note that all parameter names should now be uppercase only
            'PSPID'    => SettingDb::getValue('postfinance_shop_id'),
            'ORDERID'   => $_SESSION['shop']['order_id'],
            'AMOUNT'    => intval($_SESSION['shop']['grand_total_price']*100),
            'LANGUAGE'  => $language,
            'CURRENCY'  => Currency::getActiveCurrencyCode(),
            'OPERATION' => SettingDb::getValue('postfinance_authorization_type'),
        );
        $return = Yellowpay::getForm(
            $arrShopOrder, $_ARRAYLANG['TXT_ORDER_NOW']
        );
        if (_PAYMENT_DEBUG && Yellowpay::$arrError) {
            $strError =
                '<font color="red"><b>'.
                $_ARRAYLANG['TXT_SHOP_PSP_FAILED_TO_INITIALISE_YELLOWPAY'].
                '<br /></b>';
            if (_PAYMENT_DEBUG) {
                $strError .= join('<br />', Yellowpay::$arrError); //.'<br />';
            }
            return $strError.'</font>';
        }
if (empty ($return)) {
    foreach (Yellowpay::$arrError as $error) {
DBG::log("Yellowpay Error: $error");
    }
}
        return $return;
    }


    /**
     * Returns the complete HTML code for the Datatrans payment form
     *
     * Includes form, input and submit button tags
     * @return  string                        The HTML form code
     * @author  Reto Kohli <reto.kohli@comvation.com>
     * @version 0.9
     * @since   2.1.0
     */
    static function getDatatransForm()
    {
        global $_ARRAYLANG;

        Datatrans::initialize(
            SettingDb::getValue('datatrans_merchant_id'),
            $_SESSION['shop']['order_id'],
            $_SESSION['shop']['grand_total_price'],
            Currency::getActiveCurrencyCode()
        );
        return
            $_ARRAYLANG['TXT_ORDER_LINK_PREPARED'].'<br/><br/>'."\n".
            '<form name="datatrans" method="post" '.
            'action="'.Datatrans::getGatewayUri().'">'."\n".
            Datatrans::getHtml()."\n".
            '<input type="submit" name="go" value="'.
            $_ARRAYLANG['TXT_ORDER_NOW'].'" />'."\n".
            '</form>'."\n";
    }


    /**
     * Check in the payment processor after the payment is complete.
     * @return  mixed   For external payment methods:
     *                  The integer order ID, if known, upon success
     *                  For internal payment methods:
     *                  Boolean true, in order to make these skip the order
     *                  status update, as this has already been done.
     *                  If the order ID is unknown or upon failure:
     *                  Boolean false
     */
    static function checkIn()
    {
        $result = NULL;
        if (isset($_GET['result'])) {
            $result = abs(intval($_GET['result']));
            if ($result == 0 || $result == 2) return false;
        }
        if (empty($_GET['handler'])) return false;
        switch ($_GET['handler']) {
            case 'saferpay':
//DBG::log("PaymentProcessing::checkIn():");
//DBG::log("POST: ".var_export($_POST, true));
//DBG::log("GET: ".var_export($_GET, true));
                $arrShopOrder = array(
                    'ACCOUNTID' => SettingDb::getValue('saferpay_id'));
                $id = Saferpay::payConfirm();
                if (SettingDb::getValue('saferpay_finalize_payment')) {
                    $arrShopOrder['ID'] = $id;
                    $id = Saferpay::payComplete($arrShopOrder);
                }
//DBG::log("Transaction: ".var_export($transaction, true));
                return (boolean)$id;
            case 'paypal':
                if ($result < 0) {
                    $paypalAccountEmail = SettingDb::getValue('paypal_account_email');
                    $order_id = (isset($_SESSION['shop']['order_id'])
                        ? $_SESSION['shop']['order_id']
                        : (isset ($_SESSION['shop']['order_id_checkin'])
                            ? $_SESSION['shop']['order_id_checkin']
                            : NULL));
                    $order = Order::getById($order_id);
                    $amount = $currency_id = $customer_email = NULL;
                    if ($order) {
                        $amount = $order->sum();
                        $currency_id = $order->currency_id();
                        $customer_id = $order->customer_id();
                        $customer = Customer::getById($customer_id);
                        if ($customer) {
                            $customer_email = $customer->email();
                        }
                    }
                    $currency_code = Currency::getCodeById($currency_id);
                    $newOrderStatus = SHOP_ORDER_STATUS_CANCELLED;
                    return PayPal::ipnCheck($amount, $currency_code,
                        $order_id, $email, $customer_email);
                }
            case 'yellowpay':
                $passphrase = SettingDb::getValue('postfinance_hash_signature_out');
                return Yellowpay::checkIn($passphrase);
//                    if (Yellowpay::$arrError || Yellowpay::$arrWarning) {
//                        global $_ARRAYLANG;
//                        echo('<font color="red"><b>'.
//                        $_ARRAYLANG['TXT_SHOP_PSP_FAILED_TO_INITIALISE_YELLOWPAY'].
//                        '</b><br />'.
//                        'Errors:<br />'.
//                        join('<br />', Yellowpay::$arrError).
//                        'Warnings:<br />'.
//                        join('<br />', Yellowpay::$arrWarning).
//                        '</font>');
//                    }
            // Added 20100222 -- Reto Kohli
            case 'mobilesolutions':
                // A return value of null means:  Do not change the order status
                if (   empty($_POST['state'])
//                    || (   $_POST['state'] == 'success'
//                        && (   empty($_POST['mosoauth'])
//                            || empty($_POST['postref'])))
                ) return null;
                $result = PostfinanceMobile::validateSign();
if ($result) {
//DBG::log("PaymentProcessing::checkIn(): mobilesolutions: Payment verification successful!");
} else {
DBG::log("PaymentProcessing::checkIn(): WARNING: mobilesolutions: Payment verification failed; errors: ".var_export(PostfinanceMobile::getErrors(), true));
}
                return $result;
            // Added 20081117 -- Reto Kohli
            case 'datatrans':
                return Datatrans::validateReturn()
                    && Datatrans::getPaymentResult() == 1;

            // For the remaining types, there's no need to check in, so we
            // return true and jump over the validation of the order ID
            // directly to success!
            // Note: A backup of the order ID is kept in the session
            // for payment methods that do not return it. This is used
            // to cancel orders in all cases where false is returned.
            case 'Internal':
            case 'Internal_CreditCard':
            case 'Internal_Debit':
            case 'Internal_LSV':
                return true;
            // Dummy payment.
            case 'dummy':
                $result = '';
                if (isset($_REQUEST['result']))
                    $result = $_REQUEST['result'];
                // Returns the order ID on success, false otherwise
                return Dummy::commit($result);
            default:
                break;
        }
        // Anything else is wrong.
        return false;
    }


    static function getOrderId()
    {
        if (empty($_GET['handler'])) {
//DBG::log("PaymentProcessing::getOrderId(): No handler, fail");
            return false;
        }
        switch ($_GET['handler']) {
            case 'saferpay':
                return Saferpay::getOrderId();
            case 'paypal':
                return PayPal::getOrderId();
            case 'yellowpay':
                return Yellowpay::getOrderId();
            // Added 20100222 -- Reto Kohli
            case 'mobilesolutions':
//DBG::log("getOrderId(): mobilesolutions");
                $order_id = PostfinanceMobile::getOrderId();
//DBG::log("getOrderId(): mobilesolutions, Order ID $order_id");
                return $order_id;
            // Added 20081117 -- Reto Kohli
            case 'datatrans':
                return Datatrans::getOrderId();
            // For the remaining types, there's no need to check in, so we
            // return true and jump over the validation of the order ID
            // directly to success!
            // Note: A backup of the order ID is kept in the session
            // for payment methods that do not return it. This is used
            // to cancel orders in all cases where false is returned.
            case 'Internal':
            case 'Internal_CreditCard':
            case 'Internal_Debit':
            case 'Internal_LSV':
            case 'dummy':
                return (isset($_SESSION['shop']['order_id_checkin'])
                    ? $_SESSION['shop']['order_id_checkin']
                    : false);
        }
        // Anything else is wrong.
        return false;
    }


    static function getMenuoptions($selected_id=0)
    {
        global $_ARRAYLANG;

        $arrName = self::getPaymentProcessorNameArray();
        if (empty($selected_id))
            $arrName = array(
                0 => $_ARRAYLANG['TXT_SHOP_PLEASE_SELECT'],
            ) + $arrName;
        return Html::getOptions($arrName, $selected_id);
    }


    /**
     * Handles all kinds of database errors
     *
     * Creates the processors' table, and creates default entries if necessary
     * @return  boolean                         False. Always.
     * @global  ADOConnection   $objDatabase
     */
    static function errorHandler()
    {
        global $objDatabase;
        
        if (!UpdateUtil::table_exist($table_name_new)) {
            $table_structure = array(
                'id' => array('type' => 'INT(10)', 'unsigned' => true, 'auto_increment' => true, 'primary' => true),
                'type' => array('type' => 'ENUM("internal", "external")', 'default' => 'internal'),
                'name' => array('type' => 'VARCHAR(255)', 'default' => ''),
                'description' => array('type' => 'TEXT', 'default' => ''),
                'company_url' => array('type' => 'VARCHAR(255)', 'default' => ''),
                'status' => array('type' => 'TINYINT(10)', 'unsigned' => true, 'default' => 1),
                'picture' => array('type' => 'VARCHAR(255)', 'default' => ''),
            );
        }
        $arrPsp = array(
            array(01, 'external', 'Saferpay',
                'Saferpay is a comprehensive Internet payment platform, specially developed for commercial applications. It provides a guarantee of secure payment processes over the Internet for merchants as well as for cardholders. Merchants benefit from the easy integration of the payment method into their e-commerce platform, and from the modularity with which they can take account of current and future requirements. Cardholders benefit from the security of buying from any shop that uses Saferpay.',
                'http://www.saferpay.com/', 1, 'logo_saferpay.gif'),
            array(02, 'external', 'Paypal',
                'With more than 40 million member accounts in over 45 countries worldwide, PayPal is the world\'s largest online payment service. PayPal makes sending money as easy as sending email! Any PayPal member can instantly and securely send money to anyone in the U.S. with an email address. PayPal can also be used on a web-enabled cell phone. In the future, PayPal will be available to use on web-enabled pagers and other handheld devices.',
                'http://www.paypal.com/', 1, 'logo_paypal.gif'),
            array(03, 'external', 'yellowpay',
                'PostFinance vereinfacht das Inkasso im Online-Shop.',
                'http://www.postfinance.ch/', 1, 'logo_postfinance.gif'),
            array(04, 'internal', 'Internal',
                'Internal no forms',
                '', 1, ''),
// Obsolete
//            array(05, 'internal', 'Internal_CreditCard',
//                'Internal with a Credit Card form',
//                '', 1, ''),
//            array(06, 'internal', 'Internal_Debit',
//                'Internal with a Bank Debit Form',
//                '', 1, ''),
//            array(07, 'external', 'Saferpay_Mastercard_Multipay_CAR',
//                'Saferpay is a comprehensive Internet payment platform, specially developed for commercial applications. It provides a guarantee of secure payment processes over the Internet for merchants as well as for cardholders. Merchants benefit from the easy integration of the payment method into their e-commerce platform, and from the modularity with which they can take account of current and future requirements. Cardholders benefit from the security of buying from any shop that uses Saferpay.',
//                'http://www.saferpay.com/', 1, 'logo_saferpay.gif'),
//            array(08, 'external', 'Saferpay_Visa_Multipay_CAR',
//                'Saferpay is a comprehensive Internet payment platform, specially developed for commercial applications. It provides a guarantee of secure payment processes over the Internet for merchants as well as for cardholders. Merchants benefit from the easy integration of the payment method into their e-commerce platform, and from the modularity with which they can take account of current and future requirements. Cardholders benefit from the security of buying from any shop that uses Saferpay.',
//                'http://www.saferpay.com/', 1, 'logo_saferpay.gif'),
            array(09, 'internal', 'Internal_LSV',
                'LSV with internal form',
                '', 1, ''),
            array(10, 'external', 'Datatrans',
                'Die professionelle und komplette Payment-Lösung - all inclusive. Ein einziges Interface für sämtliche Zahlungsmethoden (Kreditkarten, Postcard, Kundenkarten). Mit variablem Angebot für unterschiedliche Kundenbedürfnisse.',
                'http://datatrans.biz/', 1, 'logo_datatrans.gif'),
            array(11, 'external', 'mobilesolutions',
                'PostFinance Mobile',
                'https://postfinance.mobilesolutions.ch/', 1, 'logo_postfinance_mobile.gif'),
        );
        $query_template = "
            REPLACE INTO `".DBPREFIX."module_shop_payment_processors` (
                `id`, `type`, `name`,
                `description`,
                `company_url`, `status`, `picture`
            ) VALUES (
                ?, ?, ?, ?, ?, ?, ?
            )";
        foreach ($arrPsp as $psp) {
            UpdateUtil::sql($query_template, $psp);
        }

        // Always
        return false;
    }

}
