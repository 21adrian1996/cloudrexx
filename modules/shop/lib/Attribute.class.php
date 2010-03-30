<?php

/**
 * Shop Product Attribute class
 * @version     2.1.0
 * @package     contrexx
 * @subpackage  module_shop
 * @todo        Test!
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Reto Kohli <reto.kohli@comvation.com>
 */

/**
 * Product Attribute
 *
 * These may be associated with zero or more Products.
 * Each attribute consists of a name part
 * (module_shop_products_attributes_name) and zero or more value parts
 * (module_shop_products_attributes_value).
 * Each of the values can be associated with an arbitrary number of Products
 * by inserting the respective record into the relations table
 * module_shop_products_attributes.
 * The type determines the kind of relation between a Product and the attribute
 * values, that is, whether it is optional or mandatory, and whether single
 * or multiple attributes may be chosen at a time.  See {@link ?} for details.
 * @version     2.1.0
 * @package     contrexx
 * @subpackage  module_shop
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Reto Kohli <reto.kohli@comvation.com>
 * @todo        Test!
 */
class Attribute
{
    /**
     * Text keys
     */
    const TEXT_NAME  = 'shop_product_attribute';
    const TEXT_VALUE = 'shop_product_option';

    /**
     * Attribute type constants
     *
     * Note that you need to update methods like
     * Attributes::getDisplayTypeMenu() when you add another
     * type here.
     */
    const TYPE_MENU_OPTIONAL    = 0;
    const TYPE_RADIOBUTTON      = 1;
    const TYPE_CHECKBOX         = 2;
    const TYPE_MENU_MANDATORY   = 3;
    const TYPE_TEXT_OPTIONAL    = 4;
    const TYPE_TEXT_MANDATORY   = 5;
    const TYPE_UPLOAD_OPTIONAL  = 6;
    const TYPE_UPLOAD_MANDATORY = 7;
    // Keep this up to date!
    const TYPE_COUNT            = 8;

    /**
     * The Attribute ID
     * @var integer
     */
    private $attribute_id = 0;
    /**
     * The associated Product ID, if any, or false
     * @var   mixed
     */
    private $product_id = false;
    /**
     * The Attribute name
     * @var string
     */
    private $name = '';
    /**
     * The Text ID of the name
     * @var integer
     */
    private $text_name_id = 0;
    /**
     * The Attribute type
     * @var integer
     */
    private $type = 0;
    /**
     * The array of Options
     * @var array
     */
    private $arrValues = false;
    /**
     * The array of Product Attribute relations
     * @var array;
     */
    private $arrRelation = false;
    /**
     * Sorting order
     *
     * Only used by our friend, the Product class
     * @var integer
     */
    private $order;


    /**
     * Constructor
     * @param   integer   $type           The type of the Attribute
     * @param   integer   $attribute_id   The optional Attribute ID
     * @param   integer   $product_id     The optional Product ID
     */
    function __construct($name, $type, $attribute_id=0, $product_id=false)
    {
        $this->name = $name;
        $this->setType($type);
        $this->id = $attribute_id;
        $this->product_id = $product_id;
        if ($attribute_id)
            $this->arrValues =
                Attributes::getOptionArrayByAttributeId($attribute_id);
        if ($product_id)
            $this->arrRelation = Attributes::getRelationArray($product_id);
    }


    /**
     * Get the name
     * @return  string                              The name
     * @author      Reto Kohli <reto.kohli@comvation.com>
     */
    function getName()
    {
        return $this->name;
    }
    /**
     * Set the Attribute name
     *
     * Empty name arguments are ignored.
     * @param   string    $name              The Attribute name
     * @author  Reto Kohli <reto.kohli@comvation.com>
     */
    function setName($name)
    {
        if (!$name) return;
        $this->name = trim(strip_tags($name));
    }

    /**
     * Get the Attribute type
     * @return  integer                 The Attribute type
     */
    function getType()
    {
        return $this->type;
    }
    /**
     * Set the Attribute type
     * @param   integer                 The Attribute type
     */
    function setType($type)
    {
        if (   $type >= self::TYPE_MENU_OPTIONAL
            && $type <  self::TYPE_COUNT) {
            $this->type = intval($type);
        }
    }

    /**
     * Get the Attribute ID
     * @return  integer                 The Attribute ID
     */
    function getId()
    {
        return $this->id;
    }
    /**
     * Set the Attribute ID -- NOT ALLOWED
     */

    /**
     * Get the Attribute sorting order
     *
     * Note that this is *SHOULD* only be set by our friend,
     * the Product object.
     * So if you have a Attribute not actually associated to
     * a Product, you *SHOULD* always get a return value of boolean false.
     * @return  integer                 The Attribute sorting order,
     *                                  or false if not applicable.
     */
    function getOrder()
    {
        return (isset($this->order) ? $this->order : false);
    }
    /**
     * Set the Attribute sorting order.
     *
     * Note that you can only set this to a valid integer value,
     * not reset to false or even unset state.
     * This *SHOULD* only be set if the Attribute is indeed associated
     * with a Product, as this value will only be stored in the
     * relations table module_shop_products_attributes.
     * @param   integer                 The Attribute sorting order
     */
    function setOrder($order)
    {
        if (is_integer($order)) $this->order = intval($order);
    }

    /**
     * Returns an array of values for this Attribute.
     *
     * If the array has not been initialized, the method tries to
     * do so from the database.
     * The array has the form
     *  array(
     *    option ID => array(
     *      'id' => option ID,
     *      'attribute_id' => Attribute ID,
     *      'value' => value name,
     *      'text_value_id' => Text ID,
     *      'price' => price,
     *    ),
     *    ... more ...
     *  );
     * For relations to the associated Product, if any, see
     * {@link getRelationArray}.
     * @access  public
     * @return  array                       Array of Options
     *                                      upon success, false otherwise.
     * @global  ADONewConnection
     */
    function getOptionArray()
    {
        if (!is_array($this->arrValues))
            $this->arrValues = Attributes::getOptionArrayByAttributeId($this->id);
        return $this->arrValues;
    }
    /**
     * Set the option array -- NOT ALLOWED
     * Use addOption()/deleteValueById() instead.
     */


    /**
     * Add an option
     *
     * The values' ID is set when the record is stored.
     * @param   string  $value      The value description
     * @param   float   $price      The value price
     * @param   integer $order      The value order, only applicable when
     *                              associated with a Product
     * @return  boolean             True on success, false otherwise
     */
    function addOption($value, $price, $order=0)
    {
        if (   $this->type == self::TYPE_UPLOAD_OPTIONAL
            || $this->type == self::TYPE_UPLOAD_MANDATORY
            || $this->type == self::TYPE_TEXT_OPTIONAL
            || $this->type == self::TYPE_TEXT_MANDATORY) {
            // These types can have exactly one value
            $this->arrValues = array(
                array(
                    'value'   => $value,
                    'price'   => $price,
                    'order'   => $order,
                )
            );
            return true;
        }
        // Any other types can have an arbitrary number of values
        $this->arrValues[] = array(
            'value'   => $value,
            'price'   => $price,
            'order'   => $order,
        );
        return true;
    }


    /**
     * Update an option in this object
     *
     * The option is only stored together with the object in {@link store()}
     * @param   integer   $option_id  The option ID
     * @param   string    $value      The descriptive name
     * @param   float     $price      The price
     * @param   integer   $order      The order of the value, only applicable
     *                                when associated with a Product
     * @return  boolean               True on success, false otherwise
     */
    function changeValue($option_id, $value, $price, $order=0)
    {
        $this->arrValues[$option_id]['value'] = $value;
        $this->arrValues[$option_id]['price'] = $price;
        $this->arrValues[$option_id]['order'] = $order;
        // Insert into database, and update ID
        //return $this->updateValue($this->arrValues[$option_id]);
    }


    /**
     * Remove the option with the given ID from this Attribute
     * @param   integer     $option_id      The option ID
     * @return  boolean                     True on success, false otherwise
     * @author      Reto Kohli <reto.kohli@comvation.com>
     */
    function deleteValueById($option_id)
    {
        global $objDatabase;

        // Anything to be removed?
        if (empty($this->arrValues[$option_id])) return true;

        // Remove relations to Products
        $query = "
            DELETE FROM ".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes
             WHERE option_id=$option_id";
        $objResult = $objDatabase->Execute($query);
        if (!$objResult) return false;
        // Remove Text records
        if (!Text::deleteById($this->arrValues[$option_id]['text_value_id']))
            return false;
        // Remove the value
        $query = "
            DELETE FROM ".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes_value
             WHERE id=$option_id";
        $objResult = $objDatabase->Execute($query);
        if (!$objResult) return false;
        unset($this->arrValues[$option_id]);
        return true;
    }


    /**
     * Deletes the Attribute from the database.
     *
     * Includes both the name and all of the value entries related to it.
     * As a consequence, all relations to Products referring to the deleted
     * entries are deleted, too.  See {@link Product::arrAttribute(sp?)}.
     * Keep in mind that any Products currently held in memory may cause
     * inconsistencies!
     * @return  boolean                     True on success, false otherwise.
     * @global  ADONewConnection  $objDatabase    Database connection object
     */
    function delete()
    {
        global $objDatabase;

        // Delete references to products first
        $query = "
            DELETE FROM ".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes
             WHERE attribute_id=$this->id";
        $objResult = $objDatabase->Execute($query);
        if (!$objResult) return false;

        // Delete values' Text records
        foreach ($this->arrValues as $arrValue)
            if (!Text::deleteById($arrValue['text_value_id'])) return false;
        // Delete values
        $query = "
            DELETE FROM ".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes_value
             WHERE attribute_id=$this->id";
        $objResult = $objDatabase->Execute($query);
        if (!$objResult) return false;

        // Delete names' Text records
        if (!Text::deleteById($this->text_name_id)) return false;
        // Delete name
        $query = "
            DELETE FROM ".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes_name
             WHERE id=$this->id";
        $objResult = $objDatabase->Execute($query);
        if (!$objResult) return false;
        unset($this);
        return true;
    }


    /**
     * Stores the Attribute object in the database.
     *
     * Either updates or inserts the record.
     * Also stores the associated Text records.
     * @return  boolean     True on success, false otherwise
     */
    function store()
    {
        $this->text_name_id = Text::replace(
            $this->text_name_id, FRONTEND_LANG_ID,
            $this->name, MODULE_ID, self::TEXT_NAME);
        if (!$this->text_name_id) return false;
        if ($this->id && $this->recordExists()) {
            if (!$this->update()) return false;
        } else {
            $this->id = 0;
            if (!$this->insert()) return false;
        }
        return $this->storeValues();
    }


    /**
     * Returns true if the record for this objects' ID exists,
     * false otherwise
     * @return  boolean                     True if the record exists,
     *                                      false otherwise
     * @global  ADONewConnection  $objDatabase
     */
    function recordExists()
    {
        global $objDatabase;

        $query = "
            SELECT 1
              FROM ".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes_name
             WHERE id=$this->id";
        $objResult = $objDatabase->Execute($query);
        if (!$objResult || $objResult->EOF) return false;
        return true;
    }


    /**
     * Updates the Attribute object in the database.
     *
     * Note that this neither updates the associated Text nor
     * the values records.  Call {@link store()} for that.
     * @return  boolean                     True on success, false otherwise
     * @global  ADONewConnection  $objDatabase
     */
    function update()
    {
        global $objDatabase;

        $query = "
            UPDATE ".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes_name
               SET text_name_id=$this->text_name_id,
                   type=$this->type
             WHERE id=$this->id";
        $objResult = $objDatabase->Execute($query);
        if (!$objResult) return false;
        return true;
    }


    /**
     * Inserts the Attribute object into the database.
     *
     * Note that this neither updates the associated Text nor
     * the values records.  Call {@link store()} for that.
     * @return  boolean                     True on success, false otherwise
     * @global  ADONewConnection
     */
    function insert()
    {
        global $objDatabase;

        $query = "
            INSERT INTO ".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes_name (
                text_name_id, type
            ) VALUES (
                $this->text_name_id, $this->type
            )";
        $objResult = $objDatabase->Execute($query);
        if (!$objResult) return false;
        $this->id = $objDatabase->Insert_ID();
        return true;
    }


    /**
     * Store the Attibute value records in the database
     * @return  boolean                     True on success, false otherwise
     * @global  ADONewConnection
     */
    function storeValues()
    {
        // Mind: value entries in the array may be new and have to
        // be inserted, even though the object itself has got a valid ID!
        foreach ($this->arrValues as $arrValue) {
            // The Text ID is not set for values that have been added
            $text_value_id =
                (empty($arrValue['text_value_id'])
                    ? 0 : $arrValue['text_value_id']);
            // Store Text
            $arrValue['text_value_id'] = Text::replace(
                $text_value_id, FRONTEND_LANG_ID, $arrValue['value'],
                MODULE_ID, self::TEXT_VALUE);
            if (!$arrValue['text_value_id']) return false;
            // Note that the array index and the option ID stored
            // in $arrValue['id'] are only identical for value
            // records already present in the database.
            // If the value was just added to the array, the array index
            // is just that -- an array index, and its $arrValue['id'] is empty.
            $option_id = (empty($arrValue['id']) ? 0 : $arrValue['id']);
            if ($option_id && $this->recordExistsValue($option_id)) {
                if (!$this->updateValue($arrValue)) return false;
            } else {
                if (!$this->insertValue($arrValue)) return false;
            }
        }
        return true;
    }


    /**
     * Update the Attibute value record in the database
     *
     * Note that associated Text records are not changed here,
     * call {@see storeValues()} with the value array for this.
     * @param   array       $arrValue       The value array
     * @return  boolean                     True on success, false otherwise
     * @global  ADONewConnection  $objDatabase    Database connection object
     */
    function updateValue($arrValue)
    {
        global $objDatabase;

        $query = "
            UPDATE ".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes_value
               SET attribute_id=$this->id,
                   text_value_id=".$arrValue['text_value_id'].",
                   price=".floatval($arrValue['price'])."
             WHERE id=".$arrValue['id'];
        $objResult = $objDatabase->Execute($query);
        if (!$objResult) return false;
        return true;
    }


    /**
     * Insert a new option into the database.
     *
     * Updates the values' ID upon success.
     * Note that associated Text records are not changed here,
     * call {@see storeValues()} with the value array for this.
     * @access  private
     * @param   array       $arrValue       The value array, by reference
     * @return  boolean                     True on success, false otherwise
     * @global  ADONewConnection  $objDatabase    Database connection object
     */
    function insertValue(&$arrValue)
    {
        global $objDatabase;

        $query = "
            INSERT INTO ".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes_value (
                attribute_id, text_value_id, price
            ) VALUES (
                $this->id,
                ".$arrValue['text_value_id'].",
                ".floatval($arrValue['price'])."
            )";
        $objResult = $objDatabase->Execute($query);
        if (!$objResult) return false;
        $arrValue['id'] = $objDatabase->Insert_ID();
        return true;
    }


    /**
     * Returns boolean true if the option record with the
     * given ID exists in the database table, false otherwise
     * @param   integer     $option_id      The option ID
     * @return  boolean                     True if the record exists,
     *                                      false otherwise
     * @static
     */
    static function recordExistsValue($option_id)
    {
        global $objDatabase;

        $query = "
            SELECT 1
              FROM ".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes_value
             WHERE id=$option_id";
        $objResult = $objDatabase->Execute($query);
        if ($objResult && $objResult->RecordCount()) return true;
        return false;
    }


    /**
     * Returns a new Attribute queried by its Attribute ID from
     * the database.
     * @param   integer     $attribute_id        The Attribute ID
     * @return  Attribute            The Attribute object
     * @global  ADONewConnection
     * @static
     */
    static function getById($attribute_id)
    {
        $arrName = Attributes::getNameArrayByNameId($attribute_id);
        if ($arrName === false) return false;
        $objAttribute = new Attribute(
            $arrName['name'], $arrName['type'], $attribute_id
        );
        return $objAttribute;
    }


    /**
     * Returns a new Attribute queried by one of its option IDs from
     * the database.
     * @param   integer     $option_id    The option ID
     * @static
     */
    static function getByOptionId($option_id)
    {
        // Get the associated Attribute ID
        $attribute_id = Attribute::getIdByOptionId($option_id);
        return Attribute::getById($attribute_id);
    }


    /**
     * Return the name of the Attribute selected by its ID
     * from the database.
     * @param   integer     $nameId         The Attribute ID
     * @return  mixed                       The Attribute name on
     *                                      success, false otherwise
     * @global  ADONewConnection  $objDatabase    Database connection object
     * @static
     */
    static function getNameById($nameId)
    {
        global $objDatabase;

        $arrSqlName = Text::getSqlSnippets(
            'text_name_id', FRONTEND_LANG_ID,
            MODULE_ID, self::TEXT_NAME);
        $query = "
            SELECT 1".$arrSqlName['field']."
              FROM ".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes_name".
                   $arrSqlName['join']."
             WHERE id=$nameId";
        $objResult = $objDatabase->Execute($query);
        if (!$objResult || $objResult->EOF) return false;
        return $objResult->fields[$arrSqlName['text']];
    }


    /**
     * Returns the Attribute ID associated with the given option ID in the
     * value table.
     * @static
     * @param   integer     $option_id      The option ID
     * @return  integer                     The associated Attribute ID
     * @global  ADONewConnection
     */
    static function getIdByOptionId($option_id)
    {
        global $objDatabase;

        $query = "
            SELECT attribute_id
              FROM ".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes_value
             WHERE id=$option_id";
        $objResult = $objDatabase->Execute($query);
        if (!$objResult || $objResult->RecordCount() != 1)
            return false;
        return $objResult->fields['attribute_id'];
    }


    /**
     * Return the option ID corresponding to the given value name,
     * if found, false otherwise.
     *
     * If there is more than one value of the same name, only the
     * first ID found is returned, with no guarantee that it will
     * always return the same.
     * This method is awkwardly named because of the equally awkward
     * names given to the database fields.
     * @param   string      $value          The option name
     * @return  integer                     The first matching option ID found,
     *                                      or false.
     * @global  ADONewConnection  $objDatabase    Database connection object
     * @static
     */
    static function getValueIdByName($value)
    {
        global $objDatabase;

        $arrSqlValue = Text::getSqlSnippets(
            'text_value_id', FRONTEND_LANG_ID,
            MODULE_ID, self::TEXT_VALUE);
        $query = "
            SELECT id
              FROM ".DBPREFIX."module_shop".MODULE_INDEX."_products_attributes_value".
                   $arrSqlValue['join']."
             WHERE ".$arrSqlValue['name']."='".addslashes($value)."'";
        $objResult = $objDatabase->Execute($query);
        if (!$objResult || $objResult->RecordCount() == 0) return false;
        return $objResult->fields['id'];
    }


    /**
     * TODO
     * Returns a string representation of the Attribute
     * @return  string
     */

    function toString()
    {
        $string = "ID: $this->id, name: $this->name, type: $this->type<br />  values:<br />";
        foreach ($this->arrValues as $value) {
            $string .=
                "    id: ".  $value['id'].
                ", value: ". $value['value'].
                ", price: ". $value['price'].
                ", prefix: ".$value['prefix'].
                "<br />";
        }
        return $string;
    }

}

?>
