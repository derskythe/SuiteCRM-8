<?php
/**
 *
 * SugarCRM Community Edition is a customer relationship management program developed by
 * SugarCRM, Inc. Copyright (C) 2004-2013 SugarCRM Inc.
 *
 * SuiteCRM is an extension to SugarCRM Community Edition developed by SalesAgility Ltd.
 * Copyright (C) 2011 - 2018 SalesAgility Ltd.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU Affero General Public License version 3 as published by the
 * Free Software Foundation with the addition of the following permission added
 * to Section 15 as permitted in Section 7(a): FOR ANY PART OF THE COVERED WORK
 * IN WHICH THE COPYRIGHT IS OWNED BY SUGARCRM, SUGARCRM DISCLAIMS THE WARRANTY
 * OF NON INFRINGEMENT OF THIRD PARTY RIGHTS.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU Affero General Public License for more
 * details.
 *
 * You should have received a copy of the GNU Affero General Public License along with
 * this program; if not, see http://www.gnu.org/licenses or write to the Free
 * Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA
 * 02110-1301 USA.
 *
 * You can contact SugarCRM, Inc. headquarters at 10050 North Wolfe Road,
 * SW2-130, Cupertino, CA 95014, USA. or at email address contact@sugarcrm.com.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU Affero General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU Affero General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "Powered by
 * SugarCRM" logo and "Supercharged by SuiteCRM" logo. If the display of the logos is not
 * reasonably feasible for technical reasons, the Appropriate Legal Notices must
 * display the words "Powered by SugarCRM" and "Supercharged by SuiteCRM".
 */

if (!defined('sugarEntry') || !sugarEntry) {
    die('Not A Valid Entry Point');
}

/*********************************************************************************
 * Description: This file handles the Data base functionality for the application.
 * It acts as the DB abstraction layer for the application. It depends on helper classes
 * which generate the necessary SQL. This sql is then passed to PEAR DB classes.
 * The helper class is chosen in DBManagerFactory, which is driven by 'db_type' in 'dbconfig' under config.php.
 *
 * All the functions in this class will work with any bean which implements the meta interface.
 * The passed bean is passed to helper class which uses these functions to generate correct sql.
 *
 * The meta interface has the following functions:
 * getTableName()                Returns table name of the object.
 * getFieldDefinitions()            Returns a collection of field definitions in order.
 * getFieldDefintion(name)        Return field definition for the field.
 * getFieldValue(name)            Returns the value of the field identified by name.
 *                            If the field is not set, the function will return boolean FALSE.
 * getPrimaryFieldDefinition()    Returns the field definition for primary key
 *
 * The field definition is an array with the following keys:
 *
 * name        This represents name of the field. This is a required field.
 * type        This represents type of the field. This is a required field and valid values are:
 *           �   int
 *           �   long
 *           �   varchar
 *           �   text
 *           �   date
 *           �   datetime
 *           �   double
 *           �   float
 *           �   uint
 *           �   ulong
 *           �   time
 *           �   short
 *           �   enum
 * length    This is used only when the type is varchar and denotes the length of the string.
 *           The max value is 255.
 * enumvals  This is a list of valid values for an enum separated by "|".
 *           It is used only if the type is �enum�;
 * required  This field dictates whether it is a required value.
 *           The default value is �FALSE�.
 * isPrimary This field identifies the primary key of the table.
 *           If none of the fields have this flag set to �TRUE�,
 *           the first field definition is assume to be the primary key.
 *           Default value for this field is �FALSE�.
 * default   This field sets the default value for the field definition.
 *
 *
 * Portions created by SugarCRM are Copyright (C) SugarCRM, Inc.
 * All Rights Reserved.
 * Contributor(s): ______________________________________..
 ********************************************************************************/

/**
 * MySQL manager implementation for mysql extension
 */
class MysqlManager extends DBManager
{
    /**
     * @see DBManager::$dbType
     */
    public string $dbType = 'mysql';
    /**
     * @var string
     */
    public string $variant = 'mysql';
    /**
     * @var string
     */
    public string $dbName = 'MySQL';
    /**
     * @var string
     */
    public string $label = 'LBL_MYSQL';

    /**
     * @var array|int[]
     */
    protected array $maxNameLengths = array(
        'table' => 64,
        'column' => 64,
        'index' => 64,
        'alias' => 256
    );

    /**
     * @var array|string[]
     */
    protected array $type_map = array(
        'int' => 'int',
        'double' => 'double',
        'float' => 'float',
        'uint' => 'int unsigned',
        'ulong' => 'bigint unsigned',
        'long' => 'bigint',
        'short' => 'smallint',
        'varchar' => 'varchar',
        'text' => 'text',
        'longtext' => 'longtext',
        'date' => 'date',
        'enum' => 'varchar',
        'relate' => 'varchar',
        'multienum' => 'text',
        'html' => 'text',
        'emailbody' => 'nvarchar(max)',
        'longhtml' => 'longtext',
        'datetime' => 'datetime',
        'datetimecombo' => 'datetime',
        'time' => 'time',
        'bool' => 'bool',
        'tinyint' => 'tinyint',
        'char' => 'char',
        'blob' => 'blob',
        'longblob' => 'longblob',
        'currency' => 'decimal(26,6)',
        'decimal' => 'decimal',
        'decimal2' => 'decimal',
        'id' => 'char(36)',
        'url' => 'varchar',
        'encrypt' => 'varchar',
        'file' => 'varchar',
        'decimal_tpl' => 'decimal(%d, %d)',

    );

    /**
     * @var array|true[]
     */
    protected array $capabilities = array(
        "affected_rows" => true,
        "select_rows" => true,
        "inline_keys" => true,
        "create_user" => true,
        "fulltext" => true,
        "collation" => true,
        "create_db" => true,
        "disable_keys" => true,
    );

    /**
     * Parses and runs queries
     *
     * @param string $sql SQL Statement to execute
     * @param bool $dieOnError True if we want to call die if the query returns errors
     * @param string $msg Message to log if error occurs
     * @param bool $suppress Flag to suppress all error output unless in debug logging mode.
     * @param bool $keepResult True if we want to push this result into the $lastResult array.
     * @return resource result set
     */
    public function query(string|array $sql, bool $dieOnError = false, string $msg = '', bool $suppress = false, bool $keepResult = false)
    {
        if (is_array($sql)) {
            return $this->queryArray($sql, $dieOnError, $msg, $suppress);
        }

        $this->countQuery($sql);
        $GLOBALS['log']->info('Query:' . $sql);
        $this->checkConnection();
        $this->query_time = microtime(true);
        $this->lastsql = $sql;
        $result = $suppress ? @mysql_query($sql, $this->database) : mysql_query($sql, $this->database);

        $this->query_time = microtime(true) - $this->query_time;
        $GLOBALS['log']->info('Query Execution Time:' . $this->query_time);


        if ($keepResult) {
            $this->lastResult = $result;
        }

        $this->checkError($msg . ' Query Failed:' . $sql . '::', $dieOnError);

        return $result;
    }

    /**
     * Returns the number of rows affected by the last query
     * @param $result
     * @return int
     */
    public function getAffectedRowCount(mixed $result): int
    {
        return mysql_affected_rows($this->getDatabase());
    }

    /**
     * Returns the number of rows returned by the result
     *
     * This function can't be reliably implemented on most DB, do not use it.
     * @abstract
     * @param resource $result
     * @return int
     * @deprecated
     */
    public function getRowCount(mixed $result): int
    {
        return mysql_num_rows($result);
    }

    /**
     * Disconnects from the database
     *
     * Also handles any cleanup needed
     */
    public function disconnect(): void
    {
        $GLOBALS['log']->debug('Calling MySQL::disconnect()');
        if (!empty($this->database)) {
            $this->freeResult();
            mysql_close($this->database);
            $this->database = null;
        }
    }

    /**
     * @see DBManager::freeDbResult()
     */
    protected function freeDbResult(mixed $dbResult): void
    {
        if (!empty($dbResult)) {
            mysql_free_result($dbResult);
        }
    }

    /**
     * @abstract
     * Check if query has LIMIT clause
     * Relevant for now only for Mysql
     * @param string $sql
     * @return bool
     */
    protected function hasLimit(string $sql): bool
    {
        return stripos($sql, ' limit ') !== false;
    }

    /**
     * @see DBManager::limitQuery()
     */
    public function limitQuery(string $sql, int $start, int $count, bool $dieOnError = false, string $msg = '', bool $execute = true)
    {
        $start = (int)$start;
        $count = (int)$count;
        if ($start < 0) {
            $start = 0;
        }
        $GLOBALS['log']->debug('Limit Query:' . $sql . ' Start: ' . $start . ' count: ' . $count);

        $sql = "$sql LIMIT $start,$count";
        $this->lastsql = $sql;

        if (!empty($GLOBALS['sugar_config']['check_query'])) {
            $this->checkQuery($sql);
        }
        if (!$execute) {
            return $sql;
        }

        return $this->query($sql, $dieOnError, $msg);
    }

    /**
     * @see DBManager::checkQuery()
     */
    protected function checkQuery(string $sql, string $object_name = ''): bool
    {
        $result = $this->query('EXPLAIN ' . $sql);
        $badQuery = array();
        while ($row = $this->fetchByAssoc($result)) {
            if (empty($row['table'])) {
                continue;
            }
            $badQuery[$row['table']] = '';
            if (strtoupper($row['type']) === 'ALL') {
                $badQuery[$row['table']] .= ' Full Table Scan;';
            }
            if (empty($row['key'])) {
                $badQuery[$row['table']] .= ' No Index Key Used;';
            }
            if (!empty($row['Extra']) && substr_count((string)$row['Extra'], 'Using filesort') > 0) {
                $badQuery[$row['table']] .= ' Using FileSort;';
            }
            if (!empty($row['Extra']) && substr_count((string)$row['Extra'], 'Using temporary') > 0) {
                $badQuery[$row['table']] .= ' Using Temporary Table;';
            }
        }

        if (empty($badQuery)) {
            return true;
        }

        foreach ($badQuery as $table => $data) {
            if (!empty($data)) {
                $warning = ' Table:' . $table . ' Data:' . $data;
                if (!empty($GLOBALS['sugar_config']['check_query_log'])) {
                    $GLOBALS['log']->fatal($sql);
                    $GLOBALS['log']->fatal('CHECK QUERY:' . $warning);
                } else {
                    $GLOBALS['log']->warn('CHECK QUERY:' . $warning);
                }
            }
        }

        return false;
    }

    /**
     * @see DBManager::get_columns()
     */
    public function get_columns(string $table_name): array
    {
        //find all unique indexes and primary keys.
        $result = $this->query("DESCRIBE $table_name");

        $columns = array();
        while (($row = $this->fetchByAssoc($result)) != null) {
            $name = strtolower($row['Field']);
            $columns[$name]['name'] = $name;
            $matches = array();
            preg_match_all('/(\w+)(?:\(([0-9]+,?[0-9]*)\)|)( unsigned)?/i', (string)$row['Type'], $matches);
            $columns[$name]['type'] = strtolower($matches[1][0]);
            if (isset($matches[2][0]) && in_array(
                    strtolower($matches[1][0]),
                    array('varchar', 'char', 'varchar2', 'int', 'decimal', 'float')
                )
            ) {
                $columns[$name]['len'] = strtolower($matches[2][0]);
            }
            if (stripos((string)$row['Extra'], 'auto_increment') !== false) {
                $columns[$name]['auto_increment'] = '1';
            }
            if ($row['Null'] === 'NO' && stripos((string)$row['Key'], 'PRI') === false) {
                $columns[$name]['required'] = 'true';
            }
            if (!empty($row['Default'])) {
                $columns[$name]['default'] = $row['Default'];
            }
        }

        return $columns;
    }

    /**
     * @see DBManager::getFieldsArray()
     */
    public function getFieldsArray(mixed $result, bool $make_lower_case = false): array
    {
        $field_array = array();

        if (empty($result)) {
            return 0;
        }

        $fields = mysql_num_fields($result);
        for ($i = 0; $i < $fields; $i++) {
            $meta = mysql_fetch_field($result, $i);
            if (!$meta) {
                return array();
            }

            if ($make_lower_case == true) {
                $meta->name = strtolower($meta->name);
            }

            $field_array[] = $meta->name;
        }

        return $field_array;
    }

    /**
     * @see DBManager::fetchRow()
     */
    public function fetchRow(mixed $result): array
    {
        if (empty($result)) {
            return false;
        }

        return mysql_fetch_assoc($result);
    }

    /**
     * @see DBManager::getTablesArray()
     */
    public function getTablesArray(): array
    {
        $this->log->debug('Fetching table list');

        if ($this->getDatabase()) {
            $tables = array();
            $r = $this->query('SHOW TABLES');
            if (!empty($r)) {
                while ($a = $this->fetchByAssoc($r)) {
                    $row = array_values($a);
                    $tables[] = $row[0];
                }

                return $tables;
            }
        }

        return false; // no database available
    }

    /**
     * @see DBManager::version()
     */
    public function version(): string
    {
        return $this->getOne("SELECT version() version");
    }

    /**
     * @see DBManager::tableExists()
     */
    public function tableExists(string $table_name): bool
    {
        $this->log->info("tableExists: $table_name");

        if ($this->getDatabase()) {
            $result = $this->query('SHOW TABLES LIKE ' . $this->quoted($table_name));
            if (empty($result)) {
                return false;
            }
            $row = $this->fetchByAssoc($result);

            return !empty($row);
        }

        return false;
    }

    /**
     * Get tables like expression
     * @param string $like
     * @return array
     */
    public function tablesLike(string $like): array
    {
        if ($this->getDatabase()) {
            $tables = array();
            $r = $this->query('SHOW TABLES LIKE ' . $this->quoted($like));
            if (!empty($r)) {
                while ($a = $this->fetchByAssoc($r)) {
                    $row = array_values($a);
                    $tables[] = $row[0];
                }

                return $tables;
            }
        }

        return false;
    }

    /**
     * @see DBManager::quote()
     */
    public function quote(string $string): string
    {
        if ($string === '') {
            return $this->arrayQuote($string);
        }

        return mysql_real_escape_string($this->quoteInternal($string), $this->getDatabase());
    }

    /**
     * @see DBManager::quoteIdentifier()
     */
    public function quoteIdentifier(string $string): string
    {
        return '`' . $string . '`';
    }

    /**
     * @see DBManager::connect()
     */
    public function connect(array $configOptions = null, bool $dieOnError = false): bool
    {
        global $sugar_config;

        if (is_null($configOptions)) {
            $configOptions = $sugar_config['dbconfig'];
        }

        if ($this->getOption('persistent')) {
            $this->database = @mysql_pconnect(
                $configOptions['db_host_name'],
                $configOptions['db_user_name'],
                $configOptions['db_password']
            );
        }

        if (!$this->database) {
            $this->database = mysql_connect(
                $configOptions['db_host_name'],
                $configOptions['db_user_name'],
                $configOptions['db_password']
            );
            if (empty($this->database)) {
                $GLOBALS['log']->fatal("Could not connect to server " . $configOptions['db_host_name'] . " as " . $configOptions['db_user_name'] . ":" . mysql_error());
                if ($dieOnError) {
                    if (isset($GLOBALS['app_strings']['ERR_NO_DB'])) {
                        sugar_die($GLOBALS['app_strings']['ERR_NO_DB']);
                    } else {
                        sugar_die("Could not connect to the database. Please refer to suitecrm.log for details (1).");
                    }
                } else {
                    return false;
                }
            }
            // Do not pass connection information because we have not connected yet
            if ($this->database && $this->getOption('persistent')) {
                $_SESSION['administrator_error'] = "<b>Severe Performance Degradation: Persistent Database Connections "
                    . "not working.  Please set \$sugar_config['dbconfigoption']['persistent'] to false "
                    . "in your config.php file</b>";
            }
        }
        if (!empty($configOptions['db_name']) && !@mysql_select_db($configOptions['db_name'])) {
            $GLOBALS['log']->fatal("Unable to select database {$configOptions['db_name']}: " . mysql_error($this->database));
            if ($dieOnError) {
                sugar_die($GLOBALS['app_strings']['ERR_NO_DB']);
            } else {
                return false;
            }
        }

        // cn: using direct calls to prevent this from spamming the Logs
        $charset = $this->getCharset();

        if (!empty($charset)) {
            $msg = "Error setting character set";
            $this->query("SET CHARACTER SET $charset", true, $msg);

            $names = "SET NAMES '$charset'";
            $collation = $this->getCollation();

            if (!empty($collation)) {
                $names .= " COLLATE '$collation'";
            }

            $msg = "Error setting character set and collation";
            $this->query($names, true, $msg);
        }

        if (!$this->checkError('Could Not Connect:', $dieOnError)) {
            $GLOBALS['log']->info("connected to db");
        }
        $this->connectOptions = $configOptions;

        $GLOBALS['log']->info("Connect:" . $this->database);

        return true;
    }

    /**
     * @see DBManager::repairTableParams()
     *
     * For MySQL, we can write the ALTER TABLE statement all in one line, which speeds things
     * up quite a bit. So here, we'll parse the returned SQL into a single ALTER TABLE command.
     */
    public function repairTableParams(string $table_name, array $fielddefs, array $indices, bool $execute = true, string $engine = null): string
    {
        $sql = parent::repairTableParams($table_name, $fielddefs, $indices, false, $engine);

        if ($sql === '') {
            return '';
        }

        if (stripos($sql, 'create table') !== false) {
            if ($execute) {
                $msg = "Error creating table: " . $table_name . ":";
                $this->query($sql, true, $msg);
            }

            return $sql;
        }

        // first, parse out all the comments
        $match = array();
        preg_match_all('!/\*.*?\*/!is', $sql, $match);
        $commentBlocks = $match[0];
        $sql = preg_replace('!/\*.*?\*/!is', '', $sql);

        // now, we should only have alter table statements
        // let's replace the 'alter table name' part with a comma
        $sql = preg_replace("!alter table $table_name!is", ', ', $sql);

        // re-add it at the beginning
        $sql = substr_replace($sql, '', strpos($sql, ','), 1);
        $sql = str_replace([';', "\n"], '', $sql);
        $sql = "ALTER TABLE $table_name $sql";

        if ($execute) {
            $this->query($sql, 'Error with MySQL repair table');
        }

        // and re-add the comments at the beginning
        $sql = implode("\n", $commentBlocks) . "\n" . $sql . "\n";

        return $sql;
    }

    /**
     * @see DBManager::convert()
     */
    public function convert(string $string, string $type, array $additional_parameters = array()): string
    {
        $all_parameters = $additional_parameters;
        if (is_array($string)) {
            $all_parameters = array_merge($string, $all_parameters);
        } elseif (!is_null($string)) {
            array_unshift($all_parameters, $string);
        }
        $all_strings = implode(',', $all_parameters);

        switch (strtolower($type)) {
            case 'today':
                return "CURDATE()";
            case 'left':
                return "LEFT($all_strings)";
            case 'date_format':
                if (empty($additional_parameters)) {
                    return "DATE_FORMAT($string,'%Y-%m-%d')";
                }

                $format = $additional_parameters[0];
                if ($format[0] !== '\'') {
                    $format = $this->quoted($format);
                }

                return "DATE_FORMAT($string,$format)";
            // no break
            case 'ifnull':
                if (empty($additional_parameters) && strpos($all_strings, ",") === false) {
                    $all_strings .= ",''";
                }

                return "IFNULL($all_strings)";
            case 'concat':
                return "CONCAT($all_strings)";
            case 'quarter':
                return "QUARTER($string)";
            case "length":
                return "LENGTH($string)";
            case 'month':
                return "MONTH($string)";
            case 'add_date':
                return "DATE_ADD($string, INTERVAL {$additional_parameters[0]} {$additional_parameters[1]})";
            case 'add_time':
                return "DATE_ADD($string, INTERVAL + CONCAT({$additional_parameters[0]}, ':', {$additional_parameters[1]}) HOUR_MINUTE)";
            case 'add_tz_offset':
                $getUserUTCOffset = $GLOBALS['timedate']->getUserUTCOffset();
                $operation = $getUserUTCOffset < 0 ? '-' : '+';

                return $string . ' ' . $operation . ' INTERVAL ' . abs($getUserUTCOffset) . ' MINUTE';
            case 'avg':
                return "avg($string)";
            case 'now':
                return 'NOW()';
        }

        return $string;
    }

    /**
     * (non-PHPdoc)
     * @see DBManager::fromConvert()
     */
    public function fromConvert(string $string, string $type): string
    {
        return $string;
    }

    /**
     * Returns the name of the engine to use or null if we are to use the default
     *
     * @param object $bean SugarBean instance
     * @return string
     */
    protected function getEngine(SugarBean $bean): ?string
    {
        global $dictionary;
        $engine = null;
        if (isset($dictionary[$bean->getObjectName()]['engine'])) {
            $engine = $dictionary[$bean->getObjectName()]['engine'];
        }

        return $engine;
    }

    /**
     * Returns true if the engine given is enabled in the backend
     *
     * @param string $engine
     * @return bool
     */
    protected function isEngineEnabled(string $engine): bool
    {
        if ($engine === '') {
            return false;
        }

        $engine = strtoupper($engine);

        $r = $this->query('SHOW ENGINES');

        while ($row = $this->fetchByAssoc($r)) {
            if (strtoupper($row['Engine']) == $engine) {
                return ($row['Support'] == 'YES' || $row['Support'] == 'DEFAULT');
            }
        }

        return false;
    }

    /**
     * @see DBManager::createTableSQL()
     */
    public function createTableSQL(SugarBean $bean): string
    {
        $table_name = $bean->getTableName();
        $fieldDefs = $bean->getFieldDefinitions();
        $indices = $bean->getIndices();
        $engine = $this->getEngine($bean);

        return $this->createTableSQLParams($table_name, $fieldDefs, $indices, $engine);
    }

    /**
     * Generates sql for create table statement for a bean.
     *
     * @param string $table_name
     * @param array $field_definitions
     * @param array $indices
     * @return string SQL Create Table statement
     */
    public function createTableSQLParams(string $table_name, array $field_definitions, array $indices): string
    {
        if (empty($engine) && isset($field_definitions['engine'])) {
            $engine = $field_definitions['engine'];
        }
        if (!$this->isEngineEnabled($engine)) {
            $engine = '';
        }

        $columns = $this->columnSQLRep($field_definitions, false, $table_name);
        if (empty($columns)) {
            return false;
        }

        $keys = $this->keysSQL($indices);
        if (!empty($keys)) {
            $keys = ",$keys";
        }

        // cn: bug 9873 - module tables do not get created in utf8 with assoc collation
        $collation = $this->getCollation();
        $charset = $this->getCharset();

        $sql = "CREATE TABLE $table_name ($columns $keys) CHARACTER SET $charset COLLATE $collation";

        if (!empty($engine)) {
            $sql .= " ENGINE=$engine";
        }

        return $sql;
    }

    /**
     * Does this type represent text (i.e., non-varchar) value?
     * @param string $type
     */
    public function isTextType(string $type): bool
    {
        $type = $this->getColumnType(strtolower($type));

        return in_array($type, array('blob', 'text', 'longblob', 'longtext'));
    }

    /**
     * @see DBManager::oneColumnSQLRep()
     */
    protected function oneColumnSQLRep(array $field_definition, bool $ignore_required = false, string $table = '', bool $return_as_array = false): array|string
    {
        // always return as array for post-processing
        $ref = parent::oneColumnSQLRep($field_definition, $ignore_required, $table, true);

        if ($ref['colType'] == 'int' && !empty($field_definition['len'])) {
            $ref['colType'] .= "(" . $field_definition['len'] . ")";
        }

        // bug 22338 - don't set a default value on text or blob fields
        if (isset($ref['default']) &&
            in_array($ref['colBaseType'], array('text', 'blob', 'longtext', 'longblob'))
        ) {
            $ref['default'] = '';
        }

        // Quote the name column incase it has been reserved by dbms
        $ref['name'] = $this->quoteIdentifier($ref['name']);

        if ($return_as_array) {
            return $ref;
        }

        return "{$ref['name']} {$ref['colType']} {$ref['default']} {$ref['required']} {$ref['auto_increment']}";
    }

    /**
     * @see DBManager::changeColumnSQL()
     */
    protected function changeColumnSQL($table_name, array $field_definitions, string $action, $ignoreRequired = false): array|string
    {
        $columns = array();
        if ($this->isFieldArray($field_definitions)) {
            foreach ($field_definitions as $def) {
                if ($action == 'drop') {
                    $columns[] = $def['name'];
                } else {
                    $columns[] = $this->oneColumnSQLRep($def, $ignoreRequired);
                }
            }
        } else {
            if ($action == 'drop') {
                $columns[] = $field_definitions['name'];
            } else {
                $columns[] = $this->oneColumnSQLRep($field_definitions);
            }
        }

        return "ALTER TABLE $table_name $action COLUMN " . implode(",$action column ", $columns);
    }

    /**
     * Generates SQL for key specification inside CREATE TABLE statement
     *
     * The passes array is an array of field definitions or a field definition
     * itself. The keys generated will be either primary, foreign, unique, index
     * or none at all depending on the setting of the "key" parameter of a field definition
     *
     * @param array $indices
     * @param bool $alter_table
     * @param string $alter_action
     * @return string SQL Statement
     */
    protected function keysSQL($indices, $alter_table = false, $alter_action = ''): string
    {
        // check if the passed value is an array of fields.
        // if not, convert it into an array
        if (!$this->isFieldArray($indices)) {
            $indices[] = $indices;
        }

        $columns = array();
        foreach ($indices as $index) {
            if (!empty($index['db']) && $index['db'] != $this->dbType) {
                continue;
            }
            if (isset($index['source']) && $index['source'] !== 'db') {
                continue;
            }

            $type = $index['type'];
            $name = $index['name'];

            if (is_array($index['fields'])) {
                $fields = implode(", ", $index['fields']);
            } else {
                $fields = $index['fields'];
            }

            switch ($type) {
                case 'unique':
                    $columns[] = " UNIQUE $name ($fields)";
                    break;
                case 'primary':
                    $columns[] = " PRIMARY KEY ($fields)";
                    break;
                case 'index':
                case 'foreign':
                case 'clustered':
                case 'alternate_key':
                    /**
                     * @todo here it is assumed that the primary key of the foreign
                     * table will always be named 'id'. It must be noted though
                     * that this can easily be fixed by referring to db dictionary
                     * to find the correct primary field name
                     */
                    if ($alter_table) {
                        $columns[] = " INDEX $name ($fields)";
                    } else {
                        $columns[] = " KEY $name ($fields)";
                    }
                    break;
                case 'fulltext':
                    if ($this->isFullTextIndexingInstalled()) {
                        $columns[] = " FULLTEXT ($fields)";
                    } else {
                        $GLOBALS['log']->debug(
                            'MYISAM engine is not available/enabled, full-text indexes will be skipped. Skipping:',
                            $name
                        );
                    }
                    break;
            }
        }
        $columns = implode(", $alter_action ", $columns);
        if (!empty($alter_action)) {
            $columns = $alter_action . ' ' . $columns;
        }

        return $columns;
    }

    /**
     * @see DBManager::setAutoIncrement()
     */
    protected function setAutoIncrement(string $table, string $field_name): string
    {
        return "auto_increment";
    }

    /**
     * Sets the next auto-increment value of a column to a specific value.
     *
     * @param string $table tablename
     * @param string $field_name
     */
    public function setAutoIncrementStart(string $table, string $field_name, $start_value): string
    {
        $start_value = (int)$start_value;

        return $this->query("ALTER TABLE $table AUTO_INCREMENT = $start_value;");
    }

    /**
     * Returns the next value for an auto increment
     *
     * @param string $table tablename
     * @param string $field_name
     * @return string
     */
    public function getAutoIncrement(string $table, string $field_name): string
    {
        $result = $this->query("SHOW TABLE STATUS LIKE '$table'");
        $row = $this->fetchByAssoc($result);
        if (!empty($row['Auto_increment'])) {
            return $row['Auto_increment'];
        }

        return "";
    }

    /**
     * @see DBManager::get_indices()
     */
    public function get_indices(string $table_name): array
    {
        //find all unique indexes and primary keys.
        $result = $this->query("SHOW INDEX FROM $table_name");

        $indices = array();
        while (($row = $this->fetchByAssoc($result)) != null) {
            $index_type = 'index';
            if ($row['Key_name'] == 'PRIMARY') {
                $index_type = 'primary';
            } elseif ($row['Non_unique'] == '0') {
                $index_type = 'unique';
            }
            $name = strtolower($row['Key_name']);
            $indices[$name]['name'] = $name;
            $indices[$name]['type'] = $index_type;
            $field = strtolower($row['Column_name']);

            if (is_numeric($row['Sub_part'])) {
                $field = strtolower($row['Column_name']) . " ({$row['Sub_part']})";
            }
            $indices[$name]['fields'][] = $field;
        }

        return $indices;
    }

    /**
     * @see DBManager::addDropConstraint()
     */
    public function addDropConstraint(string $table, array $definition, $drop = false): string
    {
        $type = $definition['type'];
        $fields = implode(',', $definition['fields']);
        $name = $definition['name'];
        $sql = '';

        switch ($type) {
            // generic indices
            case 'index':
            case 'alternate_key':
            case 'clustered':
                if ($drop) {
                    $sql = "ALTER TABLE {$table} DROP INDEX {$name} ";
                } else {
                    $sql = "ALTER TABLE {$table} ADD INDEX {$name} ({$fields})";
                }
                break;
            // constraints as indices
            case 'unique':
                if ($drop) {
                    $sql = "ALTER TABLE {$table} DROP INDEX $name";
                } else {
                    $sql = "ALTER TABLE {$table} ADD CONSTRAINT UNIQUE {$name} ({$fields})";
                }
                break;
            case 'primary':
                if ($drop) {
                    $sql = "ALTER TABLE {$table} DROP PRIMARY KEY";
                } else {
                    $sql = "ALTER TABLE {$table} ADD CONSTRAINT PRIMARY KEY ({$fields})";
                }
                break;
            case 'foreign':
                if ($drop) {
                    $sql = "ALTER TABLE {$table} DROP FOREIGN KEY ({$fields})";
                } else {
                    $sql = "ALTER TABLE {$table} ADD CONSTRAINT FOREIGN KEY {$name} ({$fields}) REFERENCES {$definition['foreignTable']}({$definition['foreignField']})";
                }
                break;
        }

        return $sql;
    }

    /**
     * Runs a query and returns a single row
     *
     * @param string $sql SQL Statement to execute
     * @param bool $dieOnError True if we want to call die if the query returns errors
     * @param string $msg Message to log if error occurs
     * @param bool $suppress Message to log if error occurs
     * @return array    single row from the query
     */
    public function fetchOne(string $sql, bool $dieOnError = false, string $msg = '', bool $suppress = false): array
    {
        if (stripos($sql, ' LIMIT ') === false) {
            // little optimization to just fetch one row
            $sql .= " LIMIT 0,1";
        }

        return parent::fetchOne($sql, $dieOnError, $msg, $suppress);
    }

    /**
     * @see DBManager::isFullTextIndexingInstalled()
     */
    public function isFullTextIndexingInstalled(string $dbname = null): bool
    {
        return $this->isEngineEnabled('MyISAM');
    }

    /**
     * @see DBManager::massageFieldDef()
     */
    public function massageFieldDef(array &$field_definition, string $table_name): void
    {
        parent::massageFieldDef($field_definition, $table_name);

        if (isset($field_definition['default']) &&
            ($field_definition['dbType'] === 'text'
                || $field_definition['dbType'] === 'blob'
                || $field_definition['dbType'] === 'longtext'
                || $field_definition['dbType'] === 'longblob')
        ) {
            unset($field_definition['default']);
        }
        if ($field_definition['dbType'] === 'uint') {
            $field_definition['len'] = '10';
        }
        if ($field_definition['dbType'] === 'ulong') {
            $field_definition['len'] = '20';
        }
        if ($field_definition['dbType'] === 'bool') {
            $field_definition['type'] = 'tinyint';
        }
        if ($field_definition['dbType'] === 'bool' && empty($field_definition['default'])) {
            $field_definition['default'] = '0';
        }
        if (($field_definition['dbType'] === 'varchar' || $field_definition['dbType'] === 'enum') && empty($field_definition['len'])) {
            $field_definition['len'] = '255';
        }
        if ($field_definition['dbType'] === 'uint') {
            $field_definition['len'] = '10';
        }
        if ($field_definition['dbType'] === 'int' && empty($field_definition['len'])) {
            $field_definition['len'] = '11';
        }

        if ($field_definition['dbType'] === 'decimal') {
            if (isset($field_definition['len'])) {
                if (strpos((string)$field_definition['len'], ",") === false) {
                    $field_definition['len'] .= ",0";
                }
            } else {
                $field_definition['len'] = '10,0';
            }
        }
    }

    /**
     * Generates SQL for dropping a table.
     *
     * @param string $name table name
     * @return string SQL statement
     */
    public function dropTableNameSQL(string $name): string
    {
        return 'DROP TABLE IF EXISTS ' . $name;
    }

    /**
     * @param $table_name
     * @param array $indexes
     * @param bool $execute
     * @return string
     */
    public function dropIndexes(string $table_name, array $indexes, bool $execute = true): string
    {
        $sql = array();
        foreach ($indexes as $index) {
            $name = $index['name'];
            if ($execute) {
                unset(self::$index_descriptions[$table_name][$name]);
            }
            if ($index['type'] == 'primary') {
                $sql[] = 'DROP PRIMARY KEY';
            } else {
                $sql[] = "DROP INDEX $name";
            }
        }
        if (!empty($sql)) {
            $sql = "ALTER TABLE $table_name " . implode(",", $sql) . ";";
            if ($execute) {
                $this->query($sql);
            }
        } else {
            $sql = '';
        }

        return $sql;
    }

    /**
     * Get default collation settings
     * @return string
     */
    public function getCollation(): string
    {
        $collation = $this->getOption('collation');
        if (empty($collation)) {
            $collation = $this->getDefaultCollation();
        }

        return $this->quote($collation);
    }

    /**
     * Get default charset settings
     * @return string
     */
    public function getCharset(): string
    {
        $charset = $this->getOption('charset');
        if (empty($charset)) {
            $charset = $this->getDefaultCharset();
        }

        return $this->quote($charset);
    }

    /**
     * List of available collation settings
     * @return string
     */
    public function getDefaultCollation(): ?string
    {
        return 'utf8_general_ci';
    }

    /**
     * Get default charset settings
     * @return string
     */
    public function getDefaultCharset(): string
    {
        return 'utf8';
    }

    /**
     * List of available collation settings
     * @return array
     */
    public function getCollationList(): array
    {
        $q = 'SHOW COLLATION LIKE \'utf8%\'';
        $r = $this->query($q);
        $res = array();
        while ($a = $this->fetchByAssoc($r)) {
            $res[] = $a['Collation'];
        }

        return $res;
    }

    /**
     * (non-PHPdoc)
     * @see DBManager::renameColumnSQL()
     */
    public function renameColumnSQL($table_name, $column, $new_name): void
    {
        $field = $this->describeField($column, $table_name);
        $field['name'] = $new_name;

        return "ALTER TABLE $table_name CHANGE COLUMN $column " . $this->oneColumnSQLRep($field);
    }

    /**
     * @param $type
     * @return int|mixed|string
     */
    public function emptyValue($type): mixed
    {
        $ctype = $this->getColumnType($type);
        if ($ctype == "datetime") {
            return 'NULL';
        }
        if ($ctype == "date") {
            return 'NULL';
        }
        if ($ctype == "time") {
            return 'NULL';
        }

        return parent::emptyValue($type);
    }

    /**
     * (non-PHPdoc)
     * @see DBManager::lastDbError()
     */
    public function lastDbError(): bool|string
    {
        if ($this->database) {
            if (mysql_errno($this->database)) {
                return 'MySQL error ' . mysql_errno($this->database) . ': ' . mysql_error($this->database);
            }
        } else {
            $err = mysql_error();
            if ($err) {
                return $err;
            }
        }

        return false;
    }

    /**
     * Quote MySQL search term
     * @param unknown_type $term
     */
    protected function quoteTerm($term)
    {
        if (strpos($term, ' ') !== false) {
            return '"' . $term . '"';
        }

        return $term;
    }

    /**
     * Generate fulltext query from set of terms
     * @param string $fields Field to search against
     * @param array $terms Search terms that may be or not be in the result
     * @param array $must_terms Search terms that have to be in the result
     * @param array $exclude_terms Search terms that have to be not in the result
     */
    public function getFulltextQuery(string $field, array $terms, array $must_terms = array(), array $exclude_terms = array()): string
    {
        $condition = array();
        foreach ($terms as $term) {
            $condition[] = $this->quoteTerm($term);
        }
        foreach ($must_terms as $term) {
            $condition[] = '+' . $this->quoteTerm($term);
        }
        foreach ($exclude_terms as $term) {
            $condition[] = '-' . $this->quoteTerm($term);
        }
        $condition = $this->quoted(implode(" ", $condition));

        return "MATCH($field) AGAINST($condition IN BOOLEAN MODE)";
    }

    /**
     * Get list of all defined charsets
     * @return array
     */
    protected function getCharsetInfo()
    {
        $charsets = array();
        $res = $this->query('show variables like \'character\_set\_%\'');
        while ($row = $this->fetchByAssoc($res)) {
            $charsets[$row['Variable_name']] = $row['Value'];
        }

        return $charsets;
    }

    /**
     * @return array
     */
    public function getDbInfo(): ?array
    {
        $charsets = $this->getCharsetInfo();
        $charset_str = array();
        foreach ($charsets as $name => $value) {
            $charset_str[] = "$name = $value";
        }

        return array(
            'MySQL Version' => @mysql_get_client_info(),
            'MySQL Host Info' => @mysql_get_host_info($this->database),
            'MySQL Server Info' => @mysql_get_server_info($this->database),
            'MySQL Client Encoding' => @mysql_client_encoding($this->database),
            'MySQL Character Set Settings' => implode(', ', $charset_str),
        );
    }

    /**
     * Check if this query is valid
     * Validates only SELECT queries
     * @param string $query
     * @return bool
     */
    public function validateQuery(string $query): bool
    {
        $res = $this->query("EXPLAIN $query");

        return !empty($res);
    }

    /**
     * @param $table
     * @return bool
     */
    protected function makeTempTableCopy(string $table): bool
    {
        $this->log->debug("creating temp table for [$table]...");
        $result = $this->query("SHOW CREATE TABLE {$table}");
        if (empty($result)) {
            return false;
        }
        $row = $this->fetchByAssoc($result);
        if (empty($row) || empty($row['Create Table'])) {
            return false;
        }
        $create = $row['Create Table'];
        // rewrite DDL with _temp name
        $tempTableQuery = str_replace("CREATE TABLE `{$table}`", "CREATE TABLE `{$table}__uw_temp`", (string)$create);
        $r2 = $this->query($tempTableQuery);
        if (empty($r2)) {
            return false;
        }

        // get sample data into the temp table to test for data/constraint conflicts
        $this->log->debug('inserting temp dataset...');
        $q3 = "INSERT INTO `{$table}__uw_temp` SELECT * FROM `{$table}` LIMIT 10";
        $this->query($q3, false, "Preflight Failed for: {$q3}");

        return true;
    }

    /**
     * Tests an ALTER TABLE query
     * @param string table The table name to get DDL
     * @param string query The query to test.
     * @return string Non-empty if error found
     */
    protected function verifyAlterTable(string $table, string $query): string
    {
        $this->log->debug('verifying ALTER TABLE');
        // Skipping ALTER TABLE [table] DROP PRIMARY KEY because primary keys are not being copied
        // over to the temp tables
        if (strpos(strtoupper($query), 'DROP PRIMARY KEY') !== false) {
            $this->log->debug('Skipping DROP PRIMARY KEY');

            return '';
        }
        if (!$this->makeTempTableCopy($table)) {
            return 'Could not create temp table copy';
        }

        // test the query on the test table
        $this->log->debug('testing query: [' . $query . ']');
        $tempTableTestQuery = str_replace("ALTER TABLE `{$table}`", "ALTER TABLE `{$table}__uw_temp`", (string)$query);
        if (strpos($tempTableTestQuery, 'idx') === false) {
            if (strpos($tempTableTestQuery, '__uw_temp') === false) {
                return 'Could not use a temp table to test query!';
            }

            $this->log->debug('testing query on temp table: [' . $tempTableTestQuery . ']');
            $this->query($tempTableTestQuery, false, "Preflight Failed for: {$query}");
        } else {
            // test insertion of an index on a table
            $tempTableTestQuery_idx = str_replace('ADD INDEX `idx_', 'ADD INDEX `temp_idx_', $tempTableTestQuery);
            $this->log->debug('testing query on temp table: [' . $tempTableTestQuery_idx . ']');
            $this->query($tempTableTestQuery_idx, false, "Preflight Failed for: {$query}");
        }
        $mysqlError = $this->getL();
        if (!empty($mysqlError)) {
            return $mysqlError;
        }
        $this->dropTableName("{$table}__uw_temp");

        return '';
    }

    /**
     * @param $querytype
     * @param $table
     * @param $query
     * @return bool|string
     */
    protected function verifyGenericReplaceQuery(string $querytype, string $table, string $query): bool|string
    {
        $this->log->debug("verifying $querytype statement");

        if (!$this->makeTempTableCopy($table)) {
            return 'Could not create temp table copy';
        }
        // test the query on the test table
        $this->log->debug('testing query: [' . $query . ']');
        $tempTableTestQuery = str_replace("$querytype `{$table}`", "$querytype `{$table}__uw_temp`", (string)$query);
        if (strpos($tempTableTestQuery, '__uw_temp') === false) {
            return 'Could not use a temp table to test query!';
        }

        $this->query($tempTableTestQuery, false, "Preflight Failed for: {$query}");
        $error = $this->lastError(); // empty on no-errors
        $this->dropTableName("{$table}__uw_temp"); // just in case

        return $error;
    }

    /**
     * Tests a DROP TABLE query
     * @param string table The table name to get DDL
     * @param string query The query to test.
     * @return string Non-empty if error found
     */
    public function verifyDropTable(string $table, string $query): bool|string
    {
        return $this->verifyGenericReplaceQuery('DROP TABLE', $table, $query);
    }

    /**
     * Tests an INSERT INTO query
     * @param string table The table name to get DDL
     * @param string query The query to test.
     * @return string Non-empty if error found
     */
    public function verifyInsertInto(string $table, string $query): bool|string
    {
        return $this->verifyGenericReplaceQuery('INSERT INTO', $table, $query);
    }

    /**
     * Tests an UPDATE query
     * @param string table The table name to get DDL
     * @param string query The query to test.
     * @return string Non-empty if error found
     */
    public function verifyUpdate(string $table, string $query): bool|string
    {
        return $this->verifyGenericReplaceQuery('UPDATE', $table, $query);
    }

    /**
     * Tests an DELETE FROM query
     * @param string table The table name to get DDL
     * @param string query The query to test.
     * @return string Non-empty if error found
     */
    public function verifyDeleteFrom(string $table, string $query): bool|string
    {
        return $this->verifyGenericReplaceQuery('DELETE FROM', $table, $query);
    }

    /**
     * Check if certain database exists
     * @param string $dbname
     */
    public function dbExists(string $dbname): bool
    {
        $db = $this->getOne('SELECT SCHEMA_NAME FROM INFORMATION_SCHEMA.SCHEMATA WHERE SCHEMA_NAME = ' . $this->quoted($dbname));

        return !empty($db);
    }

    /**
     * Select database
     * @param string $dbname
     */
    protected function selectDb(string $dbname)
    {
        return mysql_select_db($dbname);
    }

    /**
     * Check if certain DB user exists
     * @param string $username
     */
    public function userExists(string $username)
    {
        $db = $this->getOne('SELECT DATABASE()');
        if (!$this->selectDb('mysql')) {
            return false;
        }
        $user = $this->getOne("select count(*) from user where user = " . $this->quoted($username));
        if (!$this->selectDb($db)) {
            $this->checkError("Cannot select database $db", true);
        }

        return !empty($user);
    }

    /**
     * Create DB user
     * @param string $database_name
     * @param string $host_name
     * @param string $user
     * @param string $password
     */
    public function createDbUser(string $database_name, string $host_name, string $user, string $password)
    {
        $qpassword = $this->quote($password);
        $this->query("GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, DROP, INDEX
                            ON `$database_name`.*
                            TO \"$user\"@\"$host_name\"
                            IDENTIFIED BY '{$qpassword}';", true);

        $this->query("SET PASSWORD FOR \"{$user}\"@\"{$host_name}\" = password('{$qpassword}');", true);
        if ($host_name != 'localhost') {
            $this->createDbUser($database_name, 'localhost', $user, $password);
        }
    }

    /**
     * Create a database
     * @param string $dbname
     */
    public function createDatabase(string $dbname)
    {
        $collation = $this->getCollation();
        $charset = $this->getCharset();

        $this->query("CREATE DATABASE `$dbname` CHARACTER SET $charset COLLATE $collation", true);
    }

    /**
     * @return void
     */
    public function preInstall(): void
    {
        $setup_db_database_name = '';
        $collation = $this->getCollation();
        $charset = $this->getCharset();

        $this->query("ALTER DATABASE `{$setup_db_database_name}` DEFAULT CHARACTER SET $charset", true);
        $this->query("ALTER DATABASE `{$setup_db_database_name}` DEFAULT COLLATE $collation", true);
    }

    /**
     * Drop a database
     * @param string $dbname
     */
    public function dropDatabase(string $dbname)
    {
        return $this->query("DROP DATABASE IF EXISTS `$dbname`", true);
    }

    /**
     * Check if this driver can be used
     * @return bool
     */
    public function valid(): bool
    {
        return function_exists('mysql_connect');
    }

    /**
     * Check DB version
     * @see DBManager::canInstall()
     */
    public function canInstall(): bool
    {
        $db_version = $this->version();
        if (empty($db_version)) {
            return array('ERR_DB_VERSION_FAILURE');
        }
        if (version_compare($db_version, '4.1.2') < 0) {
            return array('ERR_DB_MYSQL_VERSION', $db_version);
        }

        return true;
    }

    /**
     * @return array
     */
    public function installConfig(): void
    {
        return array(
            'LBL_DBCONFIG_MSG3' => array(
                'setup_db_database_name' => array('label' => 'LBL_DBCONF_DB_NAME', 'required' => true),
            ),
            'LBL_DBCONFIG_MSG2' => array(
                'setup_db_host_name' => array('label' => 'LBL_DBCONF_HOST_NAME', 'required' => true),
            ),
            'LBL_DBCONF_TITLE_USER_INFO' => array(),
            'LBL_DBCONFIG_B_MSG1' => array(
                'setup_db_admin_user_name' => array('label' => 'LBL_DBCONF_DB_ADMIN_USER', 'required' => true),
                'setup_db_admin_password' => array('label' => 'LBL_DBCONF_DB_ADMIN_PASSWORD', 'type' => 'password'),
            )
        );
    }

    /**
     * Disable keys on the table
     * @abstract
     * @param string $tableName
     */
    public function disableKeys(string $tableName): void
    {
        return $this->query('ALTER TABLE ' . $tableName . ' DISABLE KEYS');
    }

    /**
     * Re-enable keys on the table
     * @abstract
     * @param string $tableName
     */
    public function enableKeys(string $tableName): void
    {
        return $this->query('ALTER TABLE ' . $tableName . ' ENABLE KEYS');
    }

    /**
     * Returns a DB specific FROM clause which can be used to select against functions.
     * Note that depending on the database that this may also be an empty string.
     * @return string
     */
    public function getFromDummyTable(): string
    {
        return '';
    }

    /**
     * Returns a DB specific piece of SQL which will generate GUID (UUID)
     * This string can be used in dynamic SQL to do multiple inserts with a single query.
     * I.e. generate a unique Sugar id in a sub select of an insert statement.
     * @return string
     */

    public function getGuidSQL(): string
    {
        return 'UUID()';
    }
}
