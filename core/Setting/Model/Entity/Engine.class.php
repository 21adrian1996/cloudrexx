<?php
/**
 * Specific Setting for this Component. Use this abstract class extends with the Db.class.php or FileSystem.class.php
 *
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Reto Kohli <reto.kohli@comvation.com> (parts)
 * @author      Manish Thakur <manishthakur@cdnsol.com>
 * @version     3.0.0
 * @package     contrexx
 * @subpackage  core_setting
 * @todo        Edit PHP DocBlocks!
 */
 
namespace Cx\Core\Setting\Model\Entity;

/**
 * Manages settings stored in the database or file system
 *
 * Before trying to access a modules' settings, *DON'T* forget to call
 * {@see Setting::init()} before calling getValue() for the first time!
 * @copyright   CONTREXX CMS - COMVATION AG
 * @author      Reto Kohli <reto.kohli@comvation.com> (parts)
 * @author      Manish Thakur <manishthakur@cdnsol.com>
 * @version     3.0.0
 * @package     contrexx
 * @subpackage  core_setting
 * @todo        Edit PHP DocBlocks!
 */
abstract class Engine {
    
    /**
     * The array of currently loaded settings, like
     *  array(
     *    'name' => array(
     *      'section' => section,
     *      'group' => group,
     *      'value' => current value,
     *      'type' => element type (text, dropdown, ... [more to come]),
     *      'values' => predefined values (for dropdown),
     *      'ord' => ordinal number (for sorting),
     *    ),
     *    ... more ...
     *  );
     * @var     array
     * @static
     * @access  protected
     */
    protected static $arrSettings = null;
    /**
     * The group last used to {@see init()} the settings.
     * Defaults to null (ignored).
     * @var     string
     * @static
     * @access  protected
     */
    protected static $group = null;
    /**
     * The section last used to {@see init()} the settings.
     * Defaults to null (which will cause an error in most methods).
     * @var     string
     * @static
     * @access  protected
     */
    protected static $section = null;
    /**
     * Changed flag
     *
     * This flag is set to true as soon as any change to the settings is detected.
     * It is cleared whenever {@see updateAll()} is called.
     * @var     boolean
     * @static
     * @access  protected
     */
    protected static $changed = false;
    /**
     * Returns the current value of the changed flag.
     *
     * If it returns true, you probably want to call {@see updateAll()}.
     * @return  boolean           True if values have been changed in memory,
     *                            false otherwise
     */
    static function changed()
    {
        return self::$changed;
    }
    /**
     * Tab counter for the {@see show()} and {@see show_external()}
     * @var     integer
     * @access  public
     */
    public static $tab_index = 1;
    /**
     * Optionally sets and returns the value of the tab index
     * @param   integer $tab_index  The optional new tab index
     * @return  integer             The current tab index
     */
    static function tab_index($tab_index=null)
    {
        if (isset($tab_index)) {
            self::$tab_index = intval($tab_index);
        }
        return self::$tab_index;
    }
    /**
     * Flush the stored settings
     *
     * Resets the class to its initial state.
     * Does *NOT* clear the section, however.
     * @return  void
     */
    static function flush()
    {
        self::$arrSettings = null;
        self::$section = null;
        self::$group = null;
        self::$changed = null;
    }
    /** 
     * Returns the settings array for the given section and group
     *
     * See {@see init()} on how the arguments are used.
     * If the method is called successively using the same $group argument,
     * the current settings are returned without calling {@see init()}.
     * Thus, changes made by calling {@see set()} will be preserved.
     * @param   string    $section    The section
     * @param   string    $group        The optional group
     * @return  array                 The settings array on success,
     *                                false otherwise
     */
    static function getArray($section, $group=null)
    {
        if (self::$section !== $section
         || self::$group !== $group) {
            if (!parent::init($section, $group)) return false;
        }
        return self::$arrSettings;
    }
    /**
     * Returns the settings array for the given section and group
     * @return  array
     */
    public abstract static function getArraySetting();
    /**
     * Returns the settings value stored in the object for the name given.
     *
     * If the settings have not been initialized (see {@see init()}), or
     * if no setting of that name is present in the current set, null
     * is returned.
     * @param   string    $name       The settings name
     * @return  mixed                 The settings value, if present,
     *                                null otherwise
     */
    static function getValue($name)
    {
        if (is_null(self::$arrSettings)) {
            \DBG::log("\Cx\Core\Setting\Model\Entity\Engine::getValue($name): ERROR: no settings loaded");
            return null;
        }
        if (isset(self::$arrSettings[$name]['value'])) {
            return self::$arrSettings[$name]['value'];
        };
        return null;
    }
    /**
     * Updates a setting
     *
     * If the setting name exists and the new value is not equal to
     * the old one, it is updated, and $changed set to true.
     * Otherwise, nothing happens, and false is returned
     * @see init(), updateAll()
     * @param   string    $name       The settings name
     * @param   string    $value      The settings value
     * @return  boolean               True if the value has been changed,
     *                                false otherwise, null on noop
     */
    static function set($name, $value)
    {
        if (!isset(self::$arrSettings[$name])) {
        // \DBG::log("\Cx\Core\Setting\Model\Entity\Engine::set($name, $value): Unknown, changed: ".self::$changed);
            return false;
        }
        if (self::$arrSettings[$name]['value'] == $value) {
        // \DBG::log("\Cx\Core\Setting\Model\Entity\Engine::set($name, $value): Identical, changed: ".self::$changed);
            return null;
        }
        self::$changed = true;
        self::$arrSettings[$name]['value'] = $value;
        // \DBG::log("\Cx\Core\Setting\Model\Entity\Engine::set($name, $value): Added/updated, changed: ".self::$changed);
        return true;
    }
    /**
     * Stores all settings entries present in the $arrSettings object
     * array variable
     *
     * Returns boolean true if all records were stored successfully,
     * null if nothing changed (noop), false otherwise.
     * Upon success, also resets the $changed class variable to false.
     * The class *MUST* have been initialized before calling this
     * method using {@see init()}, and the new values been {@see set()}.
     * Note that this method does not work for adding new settings.
     * See {@see add()} on how to do this.
     * @return  boolean                   True on success, null on noop,
     *                                    false otherwise
     */
    public abstract static function updateAll();
    /**
     * Updates the value for the given name in the settings table
     *
     * The class *MUST* have been initialized before calling this
     * method using {@see init()}, and the new value been {@see set()}.
     * Sets $changed to true and returns true if the value has been
     * updated successfully.
     * Note that this method does not work for adding new settings.
     * See {@see add()} on how to do this.
     * Also note that the loaded setting is not updated, only the database!
     * @param   string    $name   The settings name
     * @return  boolean           True on successful update or if
     *                            unchanged, false on failure
     * @static
     * @global  mixed     $objDatabase    Database connection object
     */
    public abstract static function update($name);
    /**
     * Add a new record to the settings
     *
     * The class *MUST* have been initialized by calling {@see init()}
     * or {@see getArray()} before this method is called.
     * The present $group stored in the class is used as a default.
     * If the current class $group is empty, it *MUST* be specified in the call.
     * @param   string    $name     The setting name
     * @param   string    $value    The value
     * @param   integer   $ord      The ordinal value for sorting,
     *                              defaults to 0
     * @param   string    $type     The element type for displaying,
     *                              defaults to 'text'
     * @param   string    $values   The values for type 'dropdown',
     *                              defaults to the empty string
     * @param   string    $group    The optional group
     * @return  boolean             True on success, false otherwise
     */ 
    public abstract static function add($name, $value, $ord=false, $type='text', $values='', $group=null);
    /**
     * Delete one or more records from the database table
     *
     * For maintenance/update purposes only.
     * At least one of the parameter values must be non-empty.
     * It will fail if both are empty.  Mind that in this case,
     * no records will be deleted.
     * Does {@see flush()} the currently loaded settings on success.
     * @param   string    $name     The optional setting name.
     *                              Defaults to null
     * @param   string    $group      The optional group.
     *                              Defaults to null
     * @return  boolean             True on success, false otherwise
     */
    public abstract static function delete($name=null, $group=null);
    /**
     * Deletes all entries for the current section
     *
     * This is for testing purposes only.  Use with care!
     * The static $section determines the module affected.
     * @return    boolean               True on success, false otherwise
     */
    public abstract static function deleteModule();
    /**
     * Should be called whenever there's a problem with the settings
     *
     * Tries to fix or recreate the settings.
     * @return  boolean             False, always.
     * @static
     */
    public abstract static function errorHandler();
}
