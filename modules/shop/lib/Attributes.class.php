<?php

/**
 * Shop Product Attributes
 *
 * @version     2.2.0
 * @package     contrexx
 * @subpackage  module_shop
 * @todo        Test!
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Reto Kohli <reto.kohli@comvation.com>
 */

require_once ASCMS_MODULE_PATH.'/shop/lib/Currency.class.php';

/**
 * Product Attributes
 *
 * This class provides frontend and backend helper and display functionality
 * related to the Product Attribute class.
 * See {@link Attribute} for details.
 * @version     2.2.0
 * @package     contrexx
 * @subpackage  module_shop
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Reto Kohli <reto.kohli@comvation.com>
 * @todo        Test!
 */
class Attributes
{
    /**
     * The array of Attribute names
     *
     * Includes the fields id, name, and type
     * @var array
     */
    private static $arrAttributes;

    /**
     * The array of options
     *
     * See {@see initOptionArray()} for details
     * @var array
     */
    private static $arrOptions;

    /**
     * The array of Attribute relations
     *
     * See {@see initRelationArray() for details.
     * @var array;
     */
    private static $arrRelation;


    /**
     * Clear all static data
     *
     * You *SHOULD* call this after updating database records.
     * @static
     */
    static function reset()
    {
        // These will be reinitialised the next time they are accessed
        self::$arrAttributes = false;
        self::$arrOptions    = false;
        self::$arrRelation   = false;
    }


    /**
     * Returns an array of Attribute names.
     *
     * If the optional $product_id argument is greater than zero,
     * only names associated with this Product are returned,
     * all names found in the database otherwise.
     * @static
     * @access  public
     * @param   integer     $product_id      The optional Product ID
     * @return  array                       Array of Attribute names
     *                                      upon success, false otherwise.
     */
    static function getArrayByProductId($product_id=0)
    {
        global $objDatabase;

        $query = "
            SELECT DISTINCT `id`, `name`, `type`
              FROM `".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes_name`
            ".($product_id
              ? "INNER JOIN `".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes`
                    ON `attribute_id`=`id`
                 WHERE `product_id`=$product_id
                 ORDER BY `ord` ASC
            " : '');
        $objResult = $objDatabase->Execute($query);
        if (!$objResult) return false;
        self::$arrAttributes = array();
        while (!$objResult->EOF) {
            $id = $objResult->fields['id'];
            self::$arrAttributes[$id] = array(
                'id'   => $id,
                'name' => $objResult->fields['name'],
                'type' => $objResult->fields['type'],
            );
            $objResult->MoveNext();
        }
        return self::$arrAttributes;
    }


    /**
     * Returns an array of Attribute data for the given name ID
     *
     * This array contains no options, just the Attribute name and type.
     * It is a single entry of the result of {@see initAttributeArray(()}.
     * @param   integer   $attribute_id    The name ID
     * @return  array                 The Attribute array
     */
    static function getArrayById($attribute_id)
    {
        if (   !is_array(self::$arrAttributes)
            && !self::initAttributeArray()) return false;
        if (empty(self::$arrAttributes[$attribute_id])) return false;
        return self::$arrAttributes[$attribute_id];
    }


    /**
     * Returns an array of all available Attribute data arrays
     *
     * This array contains no options, just the Attribute name and type.
     * It is the complete array created by {@see initAttributeArray(()}.
     * @return  array                 The Attribute array
     */
    static function getArray()
    {
        if (   !is_array(self::$arrAttributes)
            && !self::initAttributeArray()) return false;
        return self::$arrAttributes;
    }


    /**
     * Initialises the array of Attribute name data
     *
     * This array contains no options, just the Attribute name and type.
     * The array has the form
     *  array(
     *    Attribute ID => array(
     *      'id'   => Attribute ID,
     *      'name' => Attribute name (according to FRONTEND_LANG_ID),
     *      'type' => Attribute type,
     *    ),
     *    ... more ...
     *  )
     * Note that internal calling methods like getArray(() or
     * getArrayById() make no use of the optional parameter, so that
     * the full array is initialised on the first call.
     * @param   integer   $attribute_id   The optional Attribute ID
     * @return  boolean                   True on success, false otherwise
     */
    static function initAttributeArray($attribute_id=0)
    {
        global $objDatabase;

        if (!isset(self::$arrAttributes)) self::$arrAttributes = array();
        $arrSqlName = Text::getSqlSnippets(
            '`name`.`text_name_id`', FRONTEND_LANG_ID,
            MODULE_ID, Attribute::TEXT_NAME
        );
        $query = "
            SELECT `name`.`id`, `name`.`type`".
                   $arrSqlName['field']."
              FROM `".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes_name` AS `name`".
                   $arrSqlName['join'].
            ($attribute_id ? " WHERE `name`.`id`=$attribute_id" : '');
        $objResult = $objDatabase->Execute($query);
        if (!$objResult) return false;
        while (!$objResult->EOF) {
            $id = $objResult->fields['id'];
            $text_name_id = $objResult->fields[$arrSqlName['name']];
            $strName = $objResult->fields[$arrSqlName['text']];
            // Replace Text in a missing language by another, if available
            if ($strName === null) {
                $objText = Text::getById($text_name_id, 0);
                if ($objText)
                    $objText->markDifferentLanguage(FRONTEND_LANG_ID);
                    $strName = $objText->getText();
            }
            self::$arrAttributes[$id] = array(
                'id'   => $id,
                'name' => $strName,
                'type' => $objResult->fields['type'],
            );
            $objResult->MoveNext();
        }
        return true;
    }


    /**
     * Returns the full array of Options arrays available
     *
     * See {@see initOptionArray()} for details on the array returned.
     * @return  array                     The Options array
     */
    static function getOptionArray()
    {
        if (   !is_array(self::$arrOptions)
            && !self::initOptionArray()) return false;
        return self::$arrOptions;
    }


    /**
     * Returns the array of Options for the given Attribute ID
     *
     * See {@see initOptionArray()} for details on the array returned.
     * @return  array                     The Options array
     */
    static function getOptionArrayByAttributeId($attribute_id)
    {
        if (   !is_array(self::$arrOptions)
            && !self::initOptionArray()) return false;
        if (empty(self::$arrOptions[$attribute_id])) return array();
        return self::$arrOptions[$attribute_id];
    }


    /**
     * Initialises the Options array for the given Attribute ID, or any
     * Attributes if it is missing
     *
     * The array has the form
     *  array(
     *    Attribute ID => array(
     *      Option ID => array(
     *        'id' => The option ID,
     *        'attribute_id' => The Attribute ID,
     *        'value' => The option name (according to FRONTEND_LANG_ID),
     *        'text_value_id' => The option name Text ID,
     *        'price' => The option price (including the sign),
     *      ),
     *      ... more ...
     *    ),
     *    ... more ...
     *  )
     * @param   integer   $attribute_id   The optional Attribute ID
     * @return  boolean                   True on success, false otherwise
     */
    static function initOptionArray($attribute_id=0)
    {
        global $objDatabase;

        if (!isset(self::$arrOptions)) self::$arrOptions = array();
        $arrSqlValue = Text::getSqlSnippets(
            '`value`.`text_value_id`', FRONTEND_LANG_ID,
            MODULE_ID, TEXT_SHOP_PRODUCTS_ATTRIBUTES_VALUE
        );
        $query = "
            SELECT `value`.`id`, `value`.`attribute_id`,
                   `value`.`price`".$arrSqlValue['field']."
              FROM `".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes_value` as `value`".
                   $arrSqlValue['join'].
            ($attribute_id ? " WHERE `value`.`attribute_id`=$attribute_id" : '');
        $objResult = $objDatabase->Execute($query);
        if (!$objResult) return false;
        while (!$objResult->EOF) {
            $option_id = $objResult->fields['id'];
            $attribute_id = $objResult->fields['attribute_id'];
            $text_value_id = $objResult->fields[$arrSqlValue['name']];
            $strValue = $objResult->fields[$arrSqlValue['text']];
            // Replace Text in a missing language by another, if available
            if ($strValue === null) {
                $objText = Text::getById($text_value_id, 0);
                if ($objText)
                    $objText->markDifferentLanguage(FRONTEND_LANG_ID);
                    $strValue = $objText->getText();
            }
            if (!isset(self::$arrOptions[$attribute_id]))
                self::$arrOptions[$attribute_id] = array();
            self::$arrOptions[$attribute_id][$option_id] = array(
                'id'            => $option_id,
                'attribute_id'  => $attribute_id,
                'value'         => $strValue,
                'text_value_id' => $text_value_id,
                'price'         => $objResult->fields['price'],
            );
            $objResult->MoveNext();
        }
        return true;
    }


    /**
     * Return the name of the option selected by its ID
     * from the database.
     *
     * Returns false on error, or the empty string if the value cannot be
     * found.
     * @param   integer   $option_id    The option ID
     * @return  mixed                   The option name on success,
     *                                  or false otherwise.
     * @static
     * @global  mixed     $objDatabase  Database object
     */
    static function getOptionNameById($option_id)
    {
        global $objDatabase;

        $arrSqlValue = Text::getSqlSnippets(
            'text_value_id', FRONTEND_LANG_ID,
            MODULE_ID, self::TEXT_VALUE);
        $query = "
            SELECT ".$arrSqlValue['field']."
              FROM ".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes_value".
                   $arrSqlValue['join']."
             WHERE id=$option_id";
        $objResult = $objDatabase->Execute($query);
        if (!$objResult || $objResult->EOF) return false;
        return $objResult->fields[$arrSqlValue['text']];
    }


    /**
     * Return the price of the option selected by its ID
     * from the database.
     *
     * Returns false on error or if the value cannot be found.
     * @param   integer   $option_id    The option ID
     * @return  double                  The option price on success,
     *                                  or false on failure.
     * @static
     * @global  mixed     $objDatabase  Database object
     */
    static function getOptionPriceById($option_id)
    {
        global $objDatabase;

        $query = "
            SELECT price
              FROM ".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes_value
             WHERE id=$option_id";
        $objResult = $objDatabase->Execute($query);
        if (!$objResult || $objResult->EOF) return false;
        return $objResult->fields['price'];
    }


    /**
     * Returns an array of Product-Option relations for the given Product ID
     *
     * See {@see initRelationArray()} for details on the array.
     * @param   integer   $product_id     The Product ID
     * @return  array                     The relation array on success,
     *                                    false otherwise
     */
    static function getRelationArray($product_id)
    {
        if (empty($product_id)) return false;
        if (   !isset(self::$arrRelation)
            || !isset(self::$arrRelation[$product_id])) {
            if (!self::initRelationArray($product_id)) return false;
        }
        // No options for this Product ID:  Return the empty array
        if (empty(self::$arrRelation[$product_id])) return array();
        // Otherwise, there are some options.  Return that array element
        return self::$arrRelation[$product_id];
    }


    /**
     * Initialises the Product-Option relation array
     *
     * If the optional Product ID is missing, all Products are included.
     * The resulting array has the form
     *  array(
     *    Product ID => array(
     *      Option ID => The ordinal value (for sorting),
     *      ... more ...
     *    ),
     *    ... more ...
     *  )
     * The option IDs for any Product are sorted by their ascending ordinal
     * value.
     * @param   integer   $product_id     The optional Product ID
     * @return  boolean                   True on success, false otherwise
     */
    static function initRelationArray($product_id=0)
    {
        global $objDatabase;

        $query = "
            SELECT `product_id`, `option_id`, `ord`
              FROM `".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes`".
            ($product_id ? " WHERE `product_id`=$product_id" : '')."
             ORDER BY `ord` ASC";
        $objResult = $objDatabase->Execute($query);
        if (!$objResult) return false;
        if (!isset(self::$arrRelation)) self::$arrRelation = array();
        while (!$objResult->EOF) {
            $product_id = $objResult->fields['product_id'];
            $option_id = $objResult->fields['option_id'];
            if (!isset(self::$arrRelation[$product_id]))
                self::$arrRelation[$product_id] = array();
            self::$arrRelation[$product_id][$option_id] = $objResult->fields['ord'];
            $objResult->MoveNext();
        }
        return true;
    }


    /**
     * Creates a relation between the given option and Product IDs.
     *
     * The optional $order argument determines the ordinal value.
     * @static
     * @param   integer     $option_id      The option ID
     * @param   integer     $product_id     The Product ID
     * @param   integer     $order          The optional ordinal value,
     *                                      defaults to 0 (zero)
     * @return  boolean                     True on success, false otherwise
     * @global  ADONewConnection  $objDatabase    Database connection object
     * @author  Reto Kohli <reto.kohli@comvation.com>
     */
    static function addOptionToProduct($option_id, $product_id, $order=0)
    {
        global $objDatabase;

        $attribute_id = Attribute::getIdByOptionId($option_id);
        if ($attribute_id <= 0) return false;
        $query = "
            INSERT INTO `".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes` (
                `product_id`,
                `attribute_id`,
                `option_id`,
                `ord`
            ) VALUES (
                $product_id,
                $attribute_id,
                $option_id,
                $order
            )";
        $objResult = $objDatabase->Execute($query);
        if ($objResult) return true;
        return false;
    }


    /**
     * Remove all Product-option relations for the given Product ID.
     * @static
     * @param   integer     $product_id     The Product ID
     * @return  boolean                     True on success, false otherwise.
     * @global  ADONewConnection  $objDatabase    Database connection object
     */
    function removeFromProduct($product_id)
    {
        global $objDatabase;

        $query = "
            DELETE FROM `".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes`
             WHERE `product_id`=$product_id";
        $objResult = $objDatabase->Execute($query);
        return $objResult;
    }


    /**
     * Delete all Attributes from the database
     *
     * Clears all Attributes, options, and relations.  Use with due care!
     * @static
     * @return  boolean                     True on success, false otherwise.
     * @global  ADONewConnection  $objDatabase    Database connection object
     */
    static function deleteAll()
    {
        global $objDatabase;

        $arrAttributes = self::getArray();
        foreach (array_keys($arrAttributes) as $attribute_id) {
            $objAttribute = Attribute::getById($attribute_id);
            if (!$objAttribute->delete()) return false;
        }
        return true;
    }


    static function getDisplayTypeMenu($attribute_id, $displayTypeId='0', $onchange='')
    {
        global $_ARRAYLANG;

        return
            "<select name='attributeDisplayType[$attribute_id]' ".
                "size='1' style='width:170px;'".
                (empty($onchange) ? '' : ' onchange="'.$onchange.'"').
                ">\n".
            "<option value='".Attribute::TYPE_MENU_OPTIONAL."'".
                ($displayTypeId == Attribute::TYPE_MENU_OPTIONAL
                    ? ' selected="selected"' : ''
                ).">".
                $_ARRAYLANG['TXT_MENU_OPTION']."</option>\n".
            "<option value='".Attribute::TYPE_MENU_MANDATORY."'".
                ($displayTypeId == Attribute::TYPE_MENU_MANDATORY
                    ? ' selected="selected"' : ''
                ).">".
                $_ARRAYLANG['TXT_SHOP_MENU_OPTION_DUTY']."</option>\n".
            "<option value='".Attribute::TYPE_RADIOBUTTON."'".
                ($displayTypeId == Attribute::TYPE_RADIOBUTTON
                    ? ' selected="selected"' : ''
                ).">".
                $_ARRAYLANG['TXT_RADIOBUTTON_OPTION']."</option>\n".
            "<option value='".Attribute::TYPE_CHECKBOX."'".
                ($displayTypeId == Attribute::TYPE_CHECKBOX
                    ? ' selected="selected"' : ''
                ).">".
                $_ARRAYLANG['TXT_CHECKBOXES_OPTION']."</option>\n".
            "<option value='".Attribute::TYPE_TEXT_OPTIONAL."'".
                ($displayTypeId == Attribute::TYPE_TEXT_OPTIONAL
                    ? ' selected="selected"' : ''
                ).">".
                $_ARRAYLANG['TXT_SHOP_PRODUCT_ATTRIBUTE_TYPE_TEXT_OPTIONAL']."</option>\n".
            "<option value='".Attribute::TYPE_TEXT_MANDATORY."'".
                ($displayTypeId == Attribute::TYPE_TEXT_MANDATORY
                    ? ' selected="selected"' : ''
                ).">".
                $_ARRAYLANG['TXT_SHOP_PRODUCT_ATTRIBUTE_TYPE_TEXT_MANDATORY']."</option>\n".
            "<option value='".Attribute::TYPE_UPLOAD_OPTIONAL."'".
                ($displayTypeId == Attribute::TYPE_UPLOAD_OPTIONAL
                    ? ' selected="selected"' : ''
                ).">".
                $_ARRAYLANG['TXT_SHOP_PRODUCT_ATTRIBUTE_TYPE_UPLOAD_OPTIONAL']."</option>\n".
            "<option value='".Attribute::TYPE_UPLOAD_MANDATORY."'".
                ($displayTypeId == Attribute::TYPE_UPLOAD_MANDATORY
                    ? ' selected="selected"' : ''
                ).">".
                $_ARRAYLANG['TXT_SHOP_PRODUCT_ATTRIBUTE_TYPE_UPLOAD_MANDATORY']."</option>\n".
            "</select>\n";
    }


    /**
     * Returns a string containing HTML code for a list of input boxes
     * with the option values (names) or prices of an Attribute
     *
     * Only the first of the input elements has its display style set to
     * 'inline', the others are invisible ('none').
     * See {@see _showAttributeOptions()} for an example on how it's used.
     * @access   private
     * @param    integer     $attribute_id  The Attribute ID
     * @param    string      $name          The name and ID attribute for the
     *                                      input element
     * @param    string      $content       The field content
     *                                      ('value' or 'price')
     * @param    integer     $maxlength     The maximum length of the input box
     * @param    string      $style         The optional CSS style for the
     *                                      input element
     * @return   string                     The string with HTML code
     */
    static function getInputs(
        $attribute_id, $name, $content, $maxlength='', $style=''
    ) {
        $inputBoxes = '';
        $select = true;
        $arrAttributeName = self::getArrayById($attribute_id);
        $type = $arrAttributeName['type'];
        foreach (self::getOptionArrayByAttributeId($attribute_id)
                 as $option_id => $arrOption) {
            $inputBoxes .=
                '<input type="text" name="'.$name.'['.$option_id.']" '.
                'id="'.$name.'-'.$option_id.'" '.
                'value="'.$arrOption[$content].'"'.
                ($maxlength ? ' maxlength="'.$maxlength.'"' : '').
                ' style="display: '.($select ? 'inline' : 'none').';'.
                    ($style ? " $style" : '').
                '" onchange="updateOptionList('.
                    $attribute_id.','.$option_id.')"'.
                // For text and file upload options, disable the value field.
                // This does not apply to the price field, however.
                (   $content == 'value'
                 && $type >= Attribute::TYPE_TEXT_OPTIONAL
                    ? ' disabled="disabled"' : ''
                ).' />';
            $select = false;
        }
        return $inputBoxes;
    }


    /**
     * Returns HTML code for the option menu for an Attribute
     *
     * Used in the Backend for selecting and editing.
     * @global  array       $_ARRAYLANG     Language array
     * @param   integer     $attribute_id   The Attribute ID
     * @param   string      $name           The name and ID attribute for the
     *                                      menu
     * @param   integer     $selected_id    The ID of the selected option
     * @param   string      $onchange       The optional Javascript onchange
     *                                      event
     * @param   string      $style          The optional CSS style for the menu
     * @return  string      $menu           The Option menu HTML code
     * @static
     */
    static function getOptionMenu(
        $attribute_id, $name, $selected_id=0, $onchange='', $style=''
    ) {
        global $_ARRAYLANG;

        $arrOptions = self::getOptionArrayByAttributeId($attribute_id);
        // No options, or an error occurred
        if (!$arrOptions) return '';
        $menu =
            '<select name="'.$name.'['.$attribute_id.'][]" '.
            'id="'.$name.'-'.$attribute_id.'" size="1"'.
            ($onchange ? ' onchange="'.$onchange.'"' : '').
            ($style ? ' style="'.$style.'"' : '').'>'."\n";
        foreach ($arrOptions as $option_id => $arrValue) {
            $menu .=
                '<option value="'.$option_id.'"'.
                ($selected_id == $option_id ? ' selected="selected"' : '').'>'.
                $arrValue['value'].' ('.$arrValue['price'].' '.
                Currency::getDefaultCurrencySymbol().')</option>'."\n";
        }
        $menu .=
            '</select><br /><a href="javascript:{}" '.
            'id="optionMenuLink-'.$attribute_id.'" '.
            'style="display: none;" '.
            'onclick="removeSelectedValues('.$attribute_id.')" '.
            'title="'.$_ARRAYLANG['TXT_SHOP_REMOVE_SELECTED_VALUE'].'" '.
            'alt="'.$_ARRAYLANG['TXT_SHOP_REMOVE_SELECTED_VALUE'].'">'.
            $_ARRAYLANG['TXT_SHOP_REMOVE_SELECTED_VALUE'].'</a>'."\n";
        return $menu;
    }


    /**
     * Returns a string containing an Javascript array variable definition
     * with the first option ID for each Attribute
     *
     * The array has the form
     *  optionId[Attribute ID] = first option ID;
     * Additionally, the variable "index" is set to the highest option ID
     * encountered.  This is incremented for each new option added
     * on the page.
     * @static
     * @access    private
     * @return    string    $jsVars    Javascript variables list
     */
    static function getAttributeJSVars()
    {
        $jsVars = '';
        $highestIndex = 0;
        foreach (Attributes::getOptionArray() as $attribute_id => $arrOption) {
            $first = true;
            foreach (array_keys($arrOption) as $option_id) {
                if ($first)
                    $jsVars .= "optionId[$attribute_id] = $option_id;\n";
                $first = false;
                if ($option_id > $highestIndex) $highestIndex = $option_id;
            }
        }
        $jsVars .= "\nindex = ".$highestIndex.";\n";
        return $jsVars;
    }

}

?>
