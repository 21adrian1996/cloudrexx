<?PHP
class UpdateException extends Exception {};

class Update_DatabaseException extends UpdateException {
    public $sql;
    function __construct($message, $sql = null) {
        parent::__construct($message);
        $this->sql = $sql;
    }
}

class UpdateUtil {
    /**
     * Creates or modifies a table to the given specification.
     *
     * @param string name - the name of the table. do not forget DBPREFIX!
     * @param array struc - the structure of the columns. This is an associative
     *     array with the keys being the column names and the values being an array
     *     with the following keys:
     *       array(
     *           'type'            => 'INT', # or VARCHAR(30) or whatever
     *           'notnull'         => true/false, # optional, defaults to true
     *           'auto_increment'  => true/false, # optional, defaults to false
     *           'default'         => 'value',    # optional, defaults to '' (or 0 if type is INT)
     *           'default_expr'    => expression, # use this instead of 'default' to use NOW(), CURRENT_TIMESTAMP etc
     *           'primary'         => true/false, # optional, defaults to false
     *           'renamefrom'      => 'a_name'    # optional. Use this if the column existed previously with another name
     *       )
     * @param array idx - optional. Additional index specification. This is an associative array
     *     where the keys are index names and the values are arrays with the following
     *     keys:
     *        array(
     *            'fields' => array('field1', 'field2', ..), # field names to be indexed
     *            'type'   => 'UNIQUE/FULLTEXT', # optional. If left out, a normal search index is created
     *            'force'  => true/false,  # optional. forces creation of unique indexes, even if there
     *                                     # are duplicates (which will be dropped). use with care.
     *        )
     */
    public static function table($name, array $struc, array $idx = array(), $engine = 'MyISAM') {
        global $_CORELANG, $objDatabase, $_ARRAYLANG;
        $tableinfo = $objDatabase->MetaTables();
        if ($tableinfo === false) {
            throw new UpdateException(sprintf($_ARRAYLANG['TXT_UNABLE_GETTING_DATABASE_TABLE_STRUCTURE'], $name));
        }

        if (in_array($name, $tableinfo)) {
            self::check_columns($name, $struc);
            self::check_indexes($name, $idx, $struc);
        }
        else {
            self::create_table($name, $struc, $idx, $engine);
        }
    }

    public static function drop_table($name) {
        global $objDatabase, $_ARRAYLANG;
        $tableinfo = $objDatabase->MetaTables();
        if ($tableinfo === false) {
            throw new UpdateException(sprintf($_ARRAYLANG['TXT_UNABLE_GETTING_DATABASE_TABLE_STRUCTURE'], $name));
        }

        if (in_array($name, $tableinfo)) {
            $table_stmt = "DROP TABLE `$name`";
            if ($objDatabase->Execute($table_stmt) === false) {
                self::cry($objDatabase->ErrorMsg(), $table_stmt);
            }
        }
    }

    public static function column_exist($name, $col) {
        global $objDatabase;
        $col_info = $objDatabase->MetaColumns($name);
        if ($col_info === false) {
            throw new UpdateException(sprintf($_ARRAYLANG['TXT_UNABLE_GETTING_DATABASE_TABLE_STRUCTURE'], $name));
        }
        return isset($col_info[strtoupper($col)]);
    }

    private static function create_table($name, array $struc, $idx, $engine) {
        global $_CORELANG, $objDatabase, $_ARRAYLANG;

        // create table statement
        $cols = array();
        foreach ($struc as $col => $spec) {
            $cols[] = "`$col` ". self::_colspec($spec);
        }
        $colspec    = join(",\n", $cols);
        $primaries  = join(",\n", self::_getprimaries($struc));

        $table_stmt = "CREATE TABLE `$name`(
            $colspec".(!empty($primaries) ? ",
            PRIMARY KEY ($primaries)" : '')."
        ) ENGINE=$engine";

        self::sql($table_stmt);
        // index statements. as we just created the table
        // we can now just do check_indexes() to take care
        // of the "problem".
        self::check_indexes($name, $idx);
    }
    private static function cry($msg, $sql) {
        throw new Update_DatabaseException($msg, $sql);
    }
    private static function sql($statement) {
        global $objDatabase;
        if ($objDatabase->Execute($statement) === false) {
            self::cry($objDatabase->ErrorMsg(), $statement);
        }
    }

    private function check_columns($name, $struc) {
        global $objDatabase;
        $col_info = $objDatabase->MetaColumns($name);
        if ($col_info === false) {
            self::cry(sprintf($_ARRAYLANG['TXT_UNABLE_GETTING_DATABASE_TABLE_STRUCTURE'], $name));
        }

        // Create columns that don't exist yet
        foreach ($struc as $col => $spec) {
            if (self::_check_column($name, $col_info, $col, $spec)) {
                // col_info NEEDS to be reloaded, as _check_column() has changed the table
                $col_info = $objDatabase->MetaColumns($name);
                if ($col_info === false) {
                    self::cry(sprintf($_ARRAYLANG['TXT_UNABLE_GETTING_DATABASE_TABLE_STRUCTURE'], $name));
                }
            }
        }

        // Drop columns that are not specified
        self::_drop_unspecified_columns($name, $struc, $col_info);
    }

    private function _drop_unspecified_columns($name, $struc, $col_info) {
        global $objDatabase;

        foreach (array_keys($col_info) as $col) {
            // we have to do a stupid loop here as we don't know
            // the exact case of the name in $spec ;(
            $exists = false;
            foreach (array_keys($struc) as $col_exists) {
                if (strtolower($col) == strtolower($col_exists)) {
                    $exists = true;
                    break;
                }
            }
            if (!$exists) {
                $col_name = $col_info[$col]->name;
                $query = "ALTER TABLE `$name` DROP COLUMN `$col_name`";
                self::sql($query);
            }
        }
    }

    /**
     * Checks the given column and ALTERS what's needed. Returns true
     * if a change has been done.
     */
    private function _check_column($name, $col_info, $col, $spec) {
        global $objDatabase;

        if (!isset($col_info[strtoupper($col)])) {
            $colspec = self::_colspec($spec);
            // check if we need to rename the column
            if (isset($spec['renamefrom']) and isset($col_info[strtoupper($spec['renamefrom'])])) {
                // rename requested and possible.
                $from = $spec['renamefrom'];
                $query = "ALTER TABLE `$name` CHANGE `$from` `$col` $colspec";
            }
            else {
                // rename not possible or not requested. create the new column!
                // TODO: maybe we should somehow notify the caller if
                //       rename was requested but not possible?
                $query = "ALTER TABLE `$name` ADD `$col` $colspec";
            }

            self::sql($query);
            return true;
		}
        else {
            $col_spec = $col_info[strtoupper($col)];
            $type = $col_spec->type . (preg_match('@[a-z]+\([0-9]+\)@i', $spec['type']) && $col_spec->max_length > 0 ? "($col_spec->max_length)" : ($col_spec->type == 'enum' ? "(".implode(",", $col_spec->enums).")" : ''));
            $default = isset($spec['default']) ? $spec['default'] : (isset($spec['default_expr']) ? $spec['default_expr'] : '');
            if ($type <> strtolower($spec['type'])
                || $col_spec->unsigned <> (isset($spec['unsigned']) && $spec['unsigned'])
                || $col_spec->not_null <> (!isset($spec['notnull']) || $spec['notnull'])
                || $col_spec->has_default <> (isset($spec['default']) || isset($spec['default_expr']))
                || $col_spec->has_default && ($col_spec->default_value <> $default)
                || $col_spec->auto_increment <> (isset($spec['auto_increment']) && $spec['auto_increment'])
            ) {
                $colspec = self::_colspec($spec);
                $query = "ALTER TABLE `$name` CHANGE `$col` `$col` $colspec";
                self::sql($query);
                return true;
            }

        }
        return false;

        // TODO: maybe we should check for the type of the
        // existing column here and adjust it too?
    }

    private function check_indexes($name, $idx, $struc = null) {
        global $objDatabase;
        # mysql> show index from contrexx_access_user_mail;
        $key_qry = "SHOW INDEX FROM `$name`";
        $keyinfo = $objDatabase->Execute($key_qry);
        if ($keyinfo === false) {
            self::cry($objDatabase->ErrorMsg(), $key_qry);
        }

        // Find already existing keys, drop unused keys
        $arr_keys_to_drop = array();
        $arr_primaries = array();
        while (!$keyinfo->EOF) {
            if (isset($idx[ $keyinfo->fields['Key_name'] ])) {
                $idx[$keyinfo->fields['Key_name']]['exists'] = true;
                $keyinfo->MoveNext();
                continue;
            }
            if ($keyinfo->fields['Key_name'] == 'PRIMARY') {
                $arr_primaries[] = $keyinfo->fields['Column_name'];
                // primary keys should NOT be dropped :P
                $keyinfo->MoveNext();
                continue;
            }
            $arr_keys_to_drop[] = $keyinfo->fields['Key_name'];
            $keyinfo->MoveNext();
        }

        if ($struc) {
            $new_primaries = self::_getprimaries($struc);
            // recreate the primary key in case it changed
            if (count(array_diff($new_primaries, $arr_primaries)) || count(array_diff($arr_primaries, $new_primaries))) {
                // delete current primary key, in case there is one
                if (count($arr_primaries)) {
                    $drop_st = "ALTER TABLE `$name` DROP PRIMARY KEY";
                    self::sql($drop_st);
                }

                // add new primary key, in case one is defined
                if (count($new_primaries)) {
                    $new_st = "ALTER TABLE `$name` ADD PRIMARY KEY (`".join("`, `", $new_primaries)."`)";
                    self::sql($new_st);
                }
            }
        }

        // drop obsolete keys
        if (count($arr_keys_to_drop)) {
            foreach ($arr_keys_to_drop as $key) {
                $drop_st = self::_dropkey($name, $key);
                self::sql($drop_st);
            }
        }

        // create new keys
        foreach ($idx as $keyname => $spec) {
            if (!array_key_exists('exists', $spec)) {
                $new_st = self::_keyspec($name, $keyname, $spec);
                self::sql($new_st);
            }
        }
        // okay, that's it, have a nice day!
    }

    private function _dropkey($table, $name) {
        return "DROP INDEX `$name` ON `$table`";
    }

    private function _keyspec($table, $name, $spec) {
        foreach ($spec['fields'] as $fieldInfo1 => $fieldInfo2) {
            if (intval($fieldInfo1) !== $fieldInfo1) {
                $arrFields[] = '`'.$fieldInfo1.'`('.$fieldInfo2.')';
            } else {
                $arrFields[] = '`'.$fieldInfo2.'`';
            }
        }
        $fields = join(',', $arrFields);
        $type   = array_key_exists('type', $spec) ? $spec['type'] : '';

        if (isset($spec['force']) && $spec['force']) {
            $descr = "ALTER IGNORE TABLE `$table` ADD $type INDEX `$name` ($fields)";
        } else {
            $descr  = "CREATE $type INDEX `$name` ON $table ($fields)";
        }

        return $descr;
    }
    private function _colspec($spec) {
        $unsigned     = (array_key_exists('unsigned',       $spec)) ? $spec['unsigned']       : false;
        $notnull      = (array_key_exists('notnull',        $spec)) ? $spec['notnull']        : true;
        $autoinc      = (array_key_exists('auto_increment', $spec)) ? $spec['auto_increment'] : false;
        $default_expr = (array_key_exists('default_expr',   $spec)) ? $spec['default_expr']   : '';
        $default      = (array_key_exists('default',        $spec)) ? $spec['default']        : null;
        $binary       = (array_key_exists('binary',         $spec)) ? $spec['binary']         : null;
        $after        = (array_key_exists('after',          $spec)) ? $spec['after']          : false;

        $default_st = '';
        if (strtoupper($spec['type']) != 'BLOB' and strtoupper($spec['type']) != 'TEXT') {
            // BLOB/TEXT can't have a default value... sez MySQL
            if (!is_null($default)) {
                $default_st = " DEFAULT '".addslashes($default)."'";
            }
            elseif($default_expr != '') {
                $default_st = " DEFAULT $default_expr";
            }
        }

        $descr  = $spec['type'];
        $descr .= $binary ? " BINARY" : '';
        $descr .= $unsigned ? " unsigned"      : '';
        $descr .= $notnull ? " NOT NULL"       : '';
        $descr .= $autoinc ? " auto_increment" : '';
        $descr .= $default_st;
        $descr .= $after ? " AFTER `".$after."`" : '';
        return $descr;
    }
    private function _getprimaries($struc) {
        $primaries = array();
        foreach ($struc as $name => $spec) {
            $is_primary = (array_key_exists('primary', $spec)) ? $spec['primary'] : false;
            if ($is_primary) {
                $primaries[] = $name;
            }
        }
        return $primaries;
    }

    public static function DefaultActionHandler($e) {
        if ($e instanceof Update_DatabaseException) {
            return _databaseError($e->sql, $e->getMessage());
        }
		setUpdateMsg($e->getMessage());
		return false;
    }
}

