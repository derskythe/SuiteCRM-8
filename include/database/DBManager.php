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
 * Base database driver implementation
 * @api
 */
#[\AllowDynamicProperties]
abstract class DBManager
{
    /**
     * Name of database
     * @var resource
     */
    public mixed $database = null;

    /**
     * Indicates whether we should die when we get an error from the DB
     */
    protected bool $dieOnError = false;

    /**
     * Indicates whether we should html encode the results from a query by default
     */
    protected bool $encode = true;

    /**
     * Records the execution time of the last query
     */
    protected int $query_time = 0;

    /**
     * Last error message from the DB backend
     */
    protected bool $last_error = false;

    /**
     * Registry of available result sets
     */
    protected $lastResult;

    /**
     * Current query count
     */
    private static int $queryCount = 0;

    /**
     * Query threshold limit
     */
    private static int $queryLimit = 0;

    /**
     * Array of prepared statements and their correspoding parsed tokens
     */
    protected array $preparedTokens = array();

    /**
     * TimeDate instance
     * @var TimeDate
     */
    protected TimeDate $timedate;

    /**
     * PHP Logger
     * @var LoggerManager|null
     */
    protected LoggerManager|null $log;

    /**
     * Table descriptions
     * @var array
     */
    protected static array $table_descriptions = array();

    /**
     * Index descriptions
     * @var array
     */
    protected static array $index_descriptions = array();

    /**
     * Maximum length of identifiers
     * @abstract
     * @var array
     */
    protected array $maxNameLengths = array(
        'table' => 64,
        'column' => 64,
        'index' => 64,
        'alias' => 64
    );

    /**
     * DB driver priority
     * Higher priority drivers override lower priority ones
     * @var int
     */
    public int $priority = 0;

    /**
     * Driver name label, for install
     * @absrtact
     * @var string
     */
    public string $label = '';

    /**
     * Type names map
     * @abstract
     * @var array
     */
    protected array $type_map = array();

    /**
     * Type classification into:
     * - int
     * - bool
     * - float
     * - date
     * @abstract
     * @var array
     */
    protected array $type_class = array(
        'int' => 'int',
        'double' => 'float',
        'float' => 'float',
        'uint' => 'int',
        'ulong' => 'bigint',
        'long' => 'bigint',
        'short' => 'int',
        'date' => 'date',
        'datetime' => 'date',
        'datetimecombo' => 'date',
        'time' => 'time',
        'bool' => 'bool',
        'tinyint' => 'int',
        'currency' => 'float',
        'decimal' => 'float',
        'decimal2' => 'float',
    );

    /**
     * Capabilities this DB supports. Supported list:
     * affected_rows    Can report query affected rows for UPDATE/DELETE
     *                    implement getAffectedRowCount()
     * select_rows        Can report row count for SELECT
     *                    implement getRowCount()
     * case_sensitive    Supports case-sensitive text columns
     * fulltext            Supports fulltext search indexes
     * inline_keys        Supports defining keys together with the table
     * auto_increment_sequence Autoincrement support implemented as sequence
     * limit_subquery   Supports LIMIT clauses in subqueries
     * create_user        Can create users for Sugar
     * create_db        Can create databases
     * collation        Supports setting collations
     * disable_keys     Supports temporarily disabling keys (for upgrades, etc.)
     *
     * @abstract
     * Special cases:
     * fix:expandDatabase - needs expandDatabase fix, see expandDatabase.php
     * TODO: verify if we need these cases
     */
    protected array $capabilities = array();

    /**
     * Database options
     * @var array
     */
    protected array $options = array();

    /**
     * Create DB Driver
     */
    public function __construct()
    {
        $this->timedate = TimeDate::getInstance();
        $this->log = $GLOBALS['log'] ?? null;
        $this->helper = $this; // compatibility
    }

    /**
     * Wrapper for those trying to access the private and protected class members directly
     * @param string $p var name
     * @return mixed
     */
    public function __get(string $p)
    {
        $this->log->info('Call to DBManager::$' . $p . ' is deprecated');

        if (property_exists($this, $p)) {
            return $this->$p;
        } else {
            return null;
        }
    }

    /**
     * @param $p
     * @param $v
     * @return void
     */
    public function __set($p, $v)
    {
        $this->log->info('Call to DBManager::$' . $p . ' is deprecated');
        if (property_exists($this, $p)) {
            $this->$p = $v;
        }
    }

    /**
     * @param $p
     * @return bool
     */
    public function __isset($p): bool
    {
        $this->log->info('Call to DBManager::$' . $p . ' is deprecated');
        if (property_exists($this, $p)) {
            return isset($this->$p);
        }

        return false;
    }

    public function getLog()
    {
        if ($this->log === null) {
            $this->log = LoggerManager::getLogger();
        } else {
            return $this->log;
        }
    }

    /**
     * Returns the current database handle
     * @return resource
     */
    public function getDatabase(): mixed
    {
        $this->checkConnection();

        return $this->database;
    }

    /**
     * Returns this instance's DBManager
     * Actually now returns $this
     * @return DBManager
     * @deprecated
     */
    public function getHelper(): DBManager
    {
        return $this;
    }

    /**
     * Checks for error happening in the database
     *
     * @param string $msg message to prepend to the error message
     * @param bool $dieOnError true if we want to die immediately on error
     * @return bool True if there was an error
     */
    public function checkError(string $msg = '', bool $dieOnError = false): bool
    {
        if (empty($this->database)) {
            $this->registerError($msg, 'Database Is Not Connected', $dieOnError);

            return true;
        }

        $dberror = $this->lastDbError();
        if ($dberror === false) {
            $this->last_error = false;

            return false;
        }
        $this->registerError($msg, $dberror, $dieOnError);

        return true;
    }

    /**
     * Register database error
     * If die-on-error flag is set, logs the message and dies,
     * otherwise sets last_error to the message
     * @param string $userMessage Message from function user
     * @param string $message Message from SQL driver
     * @param bool $dieOnError
     */
    protected function registerError(string $userMessage, string $message, bool $dieOnError = false): void
    {
        if (empty($message)) {
            $message = 'Database error';
        }
        if (!empty($userMessage)) {
            $message = "$userMessage: $message";
        }

        $this->log->fatal(sprintf('%s%s%s', $message, PHP_EOL, $this->lastError()));
        if ($dieOnError || $this->dieOnError) {
            if (isset($GLOBALS['app_strings']['ERR_DB_FAIL'])) {
                sugar_die(snprintf('%s%s%s%s%sd',
                    $GLOBALS['app_strings']['ERR_DB_FAIL'], PHP_EOL,
                    $message, PHP_EOL,
                    $this->lastError()));
            } else {
                sugar_die(snprintf('Database error. Please check suitecrm.log for details.%sError: %s',
                    PHP_EOL, $this->lastError()));
            }
        } else {
            $this->last_error = $message;
        }
    }

    /**
     * Return DB error message for the last query executed
     * @return string Last error message
     */
    public function lastError(): string
    {
        return $this->last_error;
    }

    /**
     * This method is called by every method that runs a query.
     * If slow query dumping is turned on and the query time is beyond
     * the time limit, we will log the query. This function may do
     * additional reporting or log in a different area in the future.
     *
     * @param string $query query to log
     * @return bool true if the query was logged, false otherwise
     */
    protected function dump_slow_queries(string $query): bool
    {
        global $sugar_config;

        $do_the_dump = $sugar_config['dump_slow_queries'] ?? false;
        $slow_query_time_msec = $sugar_config['slow_query_time_msec'] ?? 5000;

        if ($do_the_dump) {
            if ($slow_query_time_msec < ($this->query_time * 1000)) {
                // Then log both the query and the query time
                $this->log->fatal('Slow Query (time:' . $this->query_time . "\n" . $query . ')');

                return true;
            }
        }

        return false;
    }

    /**
     * Scans order by to ensure that any field being ordered by is.
     *
     * It will throw a warning error to the log file - fatal if slow query logging is enabled
     *
     * @param string $sql query to be run
     * @param bool $object_name optional, object to look up indices in
     * @return bool   true if an index is found false otherwise
     */
    protected function checkQuery(string $sql, string $object_name = ''): bool
    {
        $match = array();
        preg_match_all("'.* FROM ([^ ]*).* ORDER BY (.*)'is", $sql, $match);
        $indices = false;
        if (!empty($match[1][0])) {
            $table = $match[1][0];
        } else {
            return false;
        }

        if (!empty($object_name) && !empty($GLOBALS['dictionary'][$object_name])) {
            $indices = $GLOBALS['dictionary'][$object_name]['indices'];
        }

        if (empty($indices)) {
            foreach ($GLOBALS['dictionary'] as $current) {
                if ($current['table'] === $table) {
                    $indices = $current['indices'];
                    break;
                }
            }
        }
        if (empty($indices)) {
            $this->log->warn('CHECK QUERY: Could not find index definitions for table ' . $table);

            return false;
        }
        if (!empty($match[2][0])) {
            $orderBys = explode(' ', $match[2][0]);
            foreach ($orderBys as $orderBy) {
                $orderBy = trim($orderBy);
                if (empty($orderBy)) {
                    continue;
                }
                $orderBy = strtolower($orderBy);
                if ($orderBy === 'asc' || $orderBy === 'desc') {
                    continue;
                }

                $orderBy = str_replace(array($table . '.', ','), '', $orderBy);

                foreach ($indices as $index) {
                    if (empty($index['db']) || $index['db'] === $this->dbType) {
                        foreach ($index['fields'] as $field) {
                            if ($field === $orderBy) {
                                return true;
                            }
                        }
                    }
                }

                $warning = 'Missing Index For Order By Table: ' . $table . ' Order By:' . $orderBy;
                if (!empty($GLOBALS['sugar_config']['dump_slow_queries'])) {
                    $this->log->fatal('CHECK QUERY:' . $warning);
                } else {
                    $this->log->warn('CHECK QUERY:' . $warning);
                }
            }
        }

        return false;
    }

    /**
     * Returns the time the last query took to execute
     *
     * @return int
     */
    public function getQueryTime(): int
    {
        return $this->query_time;
    }

    /**
     * Checks the current connection; if it is not connected then reconnect
     */
    public function checkConnection(): void
    {
        $this->last_error = '';
        if (!isset($this->database)) {
            $this->connect();
        }
    }

    /**
     * Sets the dieOnError value
     *
     * @param bool $value
     */
    public function setDieOnError(bool $value): void
    {
        $this->dieOnError = $value;
    }

    /**
     * Implements a generic insert for any bean.
     *
     * @param SugarBean $bean SugarBean instance
     * @return bool query result
     *
     */
    public function insert(SugarBean $bean)
    {
        $sql = $this->insertSQL($bean);
        $table_name = $bean->getTableName();
        $msg = 'Error inserting into table: ' . $table_name;

        return $this->query($sql, true, $msg);
    }

    /**
     * Insert data into table by parameter definition
     * @param string $table Table name
     * @param array $field_defs Definitions in vardef-like format
     * @param array $data Key/value to insert
     * @param array $field_map Fields map from SugarBean
     * @param bool $execute Execute or return query?
     * @return bool query result
     */
    public function insertParams(string $table, array $field_defs, array $data, array $field_map = null, bool $execute = true)
    {
        $values = array();
        if (!is_array($field_defs) && !is_object($field_defs)) {
            $GLOBALS['log']->fatal('$filed_defs should be an array');
        } else {
            foreach ((array)$field_defs as $field => $field_definition) {
                if (isset($field_definition['source']) && $field_definition['source'] !== 'db') {
                    continue;
                }//custom fields handle there save seperatley
                if (!empty($field_map) && !empty($field_map[$field]['custom_type'])) {
                    continue;
                }

                if (isset($data[$field])) {
                    // clean the incoming value..
                    $val = from_html($data[$field]);
                } else {
                    if (isset($field_definition['default']) && (string)$field_definition['default'] !== '') {
                        $val = $field_definition['default'];
                    } else {
                        $val = null;
                    }
                }

                //handle auto increment values here - we may have to do something like nextval for oracle
                if (!empty($field_definition['auto_increment'])) {
                    $auto = $this->getAutoIncrementSQL($table, $field_definition['name']);
                    if (!empty($auto)) {
                        $values[$field] = $auto;
                    }
                } elseif (isset($field_definition['name']) && $field_definition['name'] === 'deleted') {
                    $values['deleted'] = (int)$val;
                } else {
                    // need to do some thing about types of values
                    if (!is_null($val) || !empty($field_definition['required'])) {
                        $values[$field] = $this->massageValue($val, $field_definition);
                    }
                }
            }
        }

        if (empty($values)) {
            return $execute ? true : '';
        } // no columns set

        // get the entire sql
        $query = sprintf('INSERT INTO %s (%s) VALUES (%s)',
            $table,
            implode(',', array_keys($values)),
            implode(',', $values));

        return $execute ? $this->query($query) : $query;
    }

    /**
     * Implements a generic update for any bean
     *
     * @param SugarBean $bean Sugarbean instance
     * @param array $where values with the keys as names of fields.
     * If we want to pass multiple values for a name, pass it as an array
     * If where is not passed, it defaults to id of table
     * @return bool query result
     *
     */
    public function update(SugarBean $bean, array $where = array())
    {
        $sql = $this->updateSQL($bean, $where);
        $table_name = $bean->getTableName();
        $msg = "Error updating table: $table_name:";

        return $this->query($sql, true, $msg);
    }

    /**
     * Implements a generic delete for any bean identified by id
     *
     * @param SugarBean $bean Sugarbean instance
     * @param array $where values with the keys as names of fields.
     * If we want to pass multiple values for a name, pass it as an array
     * If where is not passed, it defaults to id of table
     * @return bool query result
     */
    public function delete(SugarBean $bean, array $where = array())
    {
        $sql = $this->deleteSQL($bean, $where);
        $tableName = $bean->getTableName();
        $msg = 'Error deleting from table: ' . $tableName . ':';

        return $this->query($sql, true, $msg);
    }

    /**
     * Implements a generic retrieve for any bean identified by id
     *
     * If we want to pass multiple values for a name, pass it as an array
     * If where is not passed, it defaults to id of table
     *
     * @param SugarBean $bean Sugarbean instance
     * @param array $where values with the keys as names of fields.
     * @return resource result from the query
     */
    public function retrieve(SugarBean $bean, array $where = array())
    {
        $sql = $this->retrieveSQL($bean, $where);
        $tableName = $bean->getTableName();
        $msg = 'Error retriving values from table:' . $tableName . ':';

        return $this->query($sql, true, $msg);
    }

    /**
     * Implements a generic retrieve for a collection of beans.
     *
     * These beans will be joined in the sql by the key attribute of field defs.
     * Currently, this function does support outer joins.
     *
     * @param array $beans Sugarbean instance(s)
     * @param array $cols columns to be returned with the keys as names of bean as identified by
     * get_class of bean. Values of this array is the array of fieldDefs to be returned for a bean.
     * If an empty array is passed, all columns are selected.
     * @param array $where values with the keys as names of bean as identified by get_class of bean
     * Each value at the first level is an array of values for that bean identified by name of fields.
     * If we want to pass multiple values for a name, pass it as an array
     * If where is not passed, all the rows will be returned.
     * @return resource
     */
    public function retrieveView(array $beans, array $cols = array(), array $where = array())
    {
        $sql = $this->retrieveViewSQL($beans, $cols, $where);
        $msg = 'Error retriving values from View Collection:';

        return $this->query($sql, true, $msg);
    }


    /**
     * Implements creation of a db table for a bean.
     *
     * NOTE: does not handle out-of-table constraints, use createConstraintSQL for that
     * @param SugarBean $bean Sugarbean instance
     */
    public function createTable(SugarBean $bean)
    {
        $sql = $this->createTableSQL($bean);
        $table_name = $bean->getTableName();
        $msg = "Error creating table: $table_name:";
        $this->query($sql, true, $msg);
        if (!$this->supports('inline_keys')) {
            // handle constraints and indices
            $indicesArr = $this->createConstraintSql($bean);
            if (count($indicesArr) > 0) {
                foreach ($indicesArr as $indexSql) {
                    $this->query($indexSql, true, $msg);
                }
            }
        }
    }

    /**
     * returns SQL to create constraints or indices
     *
     * @param SugarBean $bean SugarBean instance
     * @return array list of SQL statements
     */
    protected function createConstraintSql(SugarBean $bean): array
    {
        return $this->getConstraintSql($bean->getIndices(), $bean->getTableName());
    }

    /**
     * Implements creation of a db table
     *
     * @param string $table_name
     * @param array $field_definitions Field definitions, in vardef format
     * @param array $indices Index definitions, in vardef format
     * @param string $engine Engine parameter, used for MySQL engine so far
     * @return bool success value
     * @todo: refactor engine param to be more generic
     */
    public function createTableParams(string $table_name, array $field_definitions, array $indices, string $engine = null): bool
    {
        if (!empty($field_definitions)) {
            $sql = $this->createTableSQLParams($table_name, $field_definitions, $indices, $engine);
            $res = true;
            if ($sql) {
                $msg = "Error creating table: $table_name";
                $res = ($res && $this->query($sql, true, $msg));
            }
            if (!$this->supports('inline_keys')) {
                // handle constraints and indices
                $indicesArr = $this->getConstraintSql($indices, $table_name);
                if (count($indicesArr) > 0) {
                    foreach ($indicesArr as $indexSql) {
                        $res = ($res && $this->query($indexSql, true, 'Error creating indexes'));
                    }
                }
            }

            return $res;
        }

        return false;
    }

    /**
     * Implements repair of a db table for a bean.
     *
     * @param SugarBean $bean SugarBean instance
     * @param bool $execute true if we want the action to take place, false if we just want the sql returned
     * @return string SQL statement or empty string, depending upon $execute
     */
    public function repairTable(SugarBean $bean, bool $execute = true): string
    {
        $indices = $bean->getIndices();
        $fielddefs = $bean->getFieldDefinitions();
        $table_name = $bean->getTableName();

        //Clean the indexes to prevent duplicate definitions
        $new_index = array();
        foreach ($indices as $ind_def) {
            $new_index[$ind_def['name']] = $ind_def;
        }
        //jc: added this for beans that do not actually have a table, namely
        //ForecastOpportunities
        if ($table_name === 'does_not_exist' || $table_name === '') {
            return '';
        }

        global $dictionary;
        $engine = null;
        if (isset($dictionary[$bean->getObjectName()]['engine']) && !empty($dictionary[$bean->getObjectName()]['engine'])) {
            $engine = $dictionary[$bean->getObjectName()]['engine'];
        }

        return $this->repairTableParams($table_name, $fielddefs, $new_index, $execute, $engine);
    }

    /**
     * Can this field be null?
     * Auto-increment and ID fields can not be null
     * @param array $vardef
     * @return bool
     */
    protected function isNullable(array $vardef): bool
    {
        if (isset($vardef['isnull']) && (strtolower($vardef['isnull']) === 'false' || $vardef['isnull'] === false)
            && !empty($vardef['required'])
        ) {
            /* required + is_null=false => not null */
            return false;
        }
        if (empty($vardef['auto_increment']) && (empty($vardef['type']) || $vardef['type'] !== 'id')
            && (empty($vardef['dbType']) || $vardef['dbType'] !== 'id')
            && (empty($vardef['name']) || ($vardef['name'] !== 'id' && $vardef['name'] !== 'deleted'))
        ) {
            return true;
        }

        return false;
    }

    /**
     * Builds the SQL commands that repair a table structure
     *
     * @param string $table_name
     * @param array $fielddefs Field definitions, in vardef format
     * @param array $indices Index definitions, in vardef format
     * @param bool $execute optional, true if we want the queries executed instead of returned
     * @param string $engine optional, MySQL engine
     * @return string
     * @todo: refactor engine param to be more generic
     */
    public function repairTableParams(string $table_name, array $fielddefs, array $indices, bool $execute = true, string $engine = null): string
    {
        //jc: had a bug when running the repair if the tablename is blank the repair will
        //fail when it tries to create a repair table
        if ($table_name === '' || empty($fielddefs)) {
            return '';
        }

        //if the table does not exist create it and we are done
        $sql = "/* Table : $table_name */\n";
        if (!$this->tableExists($table_name)) {
            $createtablesql = $this->createTableSQLParams($table_name, $fielddefs, $indices, $engine);
            if ($execute && $createtablesql) {
                $this->createTableParams($table_name, $fielddefs, $indices, $engine);
            }

            $sql .= "/* MISSING TABLE: {$table_name} */\n";
            $sql .= $createtablesql . "\n";

            return $sql;
        }

        $compareFieldDefs = $this->get_columns($table_name);
        $compareIndices = $this->get_indices($table_name);

        $take_action = false;

        // do column comparisons
        $sql .= "/*COLUMNS*/\n";
        foreach ($fielddefs as $name => $value) {
            if (isset($value['source']) && $value['source'] !== 'db') {
                continue;
            }

            // Bug #42406. Skipping breaked vardef without type or name
            if (!isset($value['name']) || !$value['name']) {
                $sql .= "/* NAME IS MISSING IN VARDEF $table_name::$name */\n";
                continue;
            }
            if (!isset($value['type']) || !$value['type']) {
                $sql .= "/* TYPE IS MISSING IN VARDEF $table_name::$name */\n";
                continue;
            }


            $name = strtolower($value['name']);
            // add or fix the field defs per what the DB is expected to give us back
            $this->massageFieldDef($value, $table_name);

            $ignorerequired = false;

            //Do not track requiredness in the DB, auto_increment, ID,
            // and deleted fields are always required in the DB, so don't force those
            if ($this->isNullable($value)) {
                $value['required'] = false;
            }
            // Should match the conditions in DBManager::oneColumnSQLRep for DB required fields, type='id' fields will sometimes
            // come into this function as 'type' = 'char', 'dbType' = 'id' without required set in $value.
            // Assume they are correct and leave them alone.
            else {
                if (($name === 'id' || $value['type'] === 'id' || (isset($value['dbType']) && $value['dbType'] === 'id'))
                    && (!isset($value['required']) && isset($compareFieldDefs[$name]['required']))
                ) {
                    $value['required'] = $compareFieldDefs[$name]['required'];
                }
            }

            if (!isset($compareFieldDefs[$name])) {
                // ok we need this field lets create it
                $sql .= "/*MISSING IN DATABASE - $name -  ROW*/\n";
                $sql .= $this->addColumnSQL($table_name, $value) . "\n";
                if ($execute) {
                    $this->addColumn($table_name, $value);
                }
                $take_action = true;
            } elseif (!$this->compareVarDefs($compareFieldDefs[$name], $value)) {
                //fields are different lets alter it
                $sql .= "/*MISMATCH WITH DATABASE - $name -  ROW ";
                foreach ($compareFieldDefs[$name] as $rKey => $rValue) {
                    $sql .= "[$rKey] => '$rValue'  ";
                }
                $sql .= "*/\n";
                $sql .= "/* VARDEF - $name -  ROW";
                foreach ($value as $rKey => $rValue) {
                    $sql .= "[$rKey] => '$rValue'  ";
                }
                $sql .= "*/\n";

                //jc: oracle will complain if you try to execute a statement that sets a column to (not) null
                //when it is already (not) null
                if (isset($value['isnull']) && isset($compareFieldDefs[$name]['isnull']) &&
                    $value['isnull'] === $compareFieldDefs[$name]['isnull']
                ) {
                    unset($value['required']);
                    $ignorerequired = true;
                }

                //dwheeler: Once a column has been defined as null, we cannot try to force it back to !null
                if ((isset($value['required']) && ($value['required'] === true || $value['required'] === 'true' || $value['required'] === 1))
                    && (empty($compareFieldDefs[$name]['required']) || $compareFieldDefs[$name]['required'] !== 'true')
                ) {
                    $ignorerequired = true;
                }
                $altersql = $this->alterColumnSQL($table_name, $value, $ignorerequired);
                if (is_array($altersql)) {
                    $altersql = implode("\n", $altersql);
                }
                $sql .= $altersql . "\n";
                if ($execute) {
                    $this->alterColumn($table_name, $value, $ignorerequired);
                }
                $take_action = true;
            }
        }

        // do index comparisons
        $sql .= "/* INDEXES */\n";
        $correctedIndexs = array();

        $compareIndices_case_insensitive = array();

        // do indices comparisons case-insensitive
        foreach ($compareIndices as $k => $value) {
            $value['name'] = strtolower($value['name']);
            $compareIndices_case_insensitive[strtolower($k)] = $value;
        }
        $compareIndices = $compareIndices_case_insensitive;
        unset($compareIndices_case_insensitive);

        foreach ($indices as $value) {
            if (isset($value['source']) && $value['source'] !== 'db') {
                continue;
            }

            $validDBName = $this->getValidDBName($value['name'], true, 'index', true);
            if (isset($compareIndices[$validDBName])) {
                $value['name'] = $validDBName;
            }
            $name = strtolower($value['name']);

            //Don't attempt to fix the same index twice in one pass;
            if (isset($correctedIndexs[$name])) {
                continue;
            }

            //don't bother checking primary nothing we can do about them
            if (isset($value['type']) && $value['type'] === 'primary') {
                continue;
            }

            //database helpers do not know how to handle full text indices
            if ($value['type'] === 'fulltext') {
                continue;
            }

            if (in_array($value['type'], array('alternate_key', 'foreign'))) {
                $value['type'] = 'index';
            }

            if (isset($value['fields'])) {
                $value['fields'] = $this->removeIndexLimit($value['fields']);
            }

            if (!isset($compareIndices[$name])) {
                //First check if an index exists that doesn't match our name, if so, try to rename it
                $found = false;
                $ex_name = '';
                $ex_value = '';
                foreach ($compareIndices as $ex_name => $ex_value) {
                    if ($this->compareVarDefs($ex_value, $value, true)) {
                        $found = $ex_name;
                        break;
                    }
                }
                if ($found) {
                    $sql .= "/*MISSNAMED INDEX IN DATABASE - $name - $ex_name */\n";
                    $rename = $this->renameIndexDefs($ex_value, $value, $table_name);
                    if ($execute) {
                        $this->query($rename, true, 'Cannot rename index');
                    }
                    $sql .= is_array($rename) ? implode("\n", $rename) . "\n" : $rename . "\n";
                } else {
                    // ok we need this field lets create it
                    $sql .= "/*MISSING INDEX IN DATABASE - $name -{$value['type']}  ROW */\n";
                    $sql .= $this->addIndexes($table_name, array($value), $execute) . "\n";
                }
                $take_action = true;
                $correctedIndexs[$name] = true;
            } elseif (!$this->compareVarDefs($compareIndices[$name], $value)) {
                // fields are different lets alter it
                $sql .= "/*INDEX MISMATCH WITH DATABASE - $name -  ROW ";
                foreach ($compareIndices[$name] as $n1 => $t1) {
                    $sql .= "<$n1>";
                    if ($n1 === 'fields') {
                        foreach ($t1 as $rKey => $rValue) {
                            $sql .= "[$rKey] => '$rValue'  ";
                        }
                    } else {
                        $sql .= " $t1 ";
                    }
                }
                $sql .= "*/\n";
                $sql .= "/* VARDEF - $name -  ROW";
                foreach ($value as $n1 => $t1) {
                    $sql .= "<$n1>";
                    if ($n1 === 'fields') {
                        foreach ($t1 as $rKey => $rValue) {
                            $sql .= "[$rKey] => '$rValue'  ";
                        }
                    } else {
                        $sql .= " $t1 ";
                    }
                }
                $sql .= "*/\n";
                $sql .= $this->modifyIndexes($table_name, array($value), $execute) . "\n";
                $take_action = true;
                $correctedIndexs[$name] = true;
            }
        }

        return ($take_action === true) ? $sql : '';
    }

    /**
     * Compares two vardefs
     *
     * @param array $fielddef1 This is from the database
     * @param array $fielddef2 This is from the vardef
     * @param bool $ignoreName Ignore name-only differences?
     * @return bool   true if they match, false if they don't
     */
    public function compareVarDefs(array $fielddef1, array $fielddef2, bool $ignoreName = false): bool
    {
        foreach ($fielddef1 as $key => $value) {
            if ($key === 'name' && $ignoreName) {
                continue;
            }
            if (isset($fielddef2[$key])) {
                if (!is_array($value) && !is_array($fielddef2[$key])) {
                    if (strtolower($value) === strtolower($fielddef2[$key])) {
                        continue;
                    }
                } else {
                    $f1 = fixIndexArrayFormat($value);
                    $f2 = fixIndexArrayFormat($fielddef2[$key]);
                    if (array_map('strtolower', $f1) === array_map('strtolower', $f2)) {
                        continue;
                    }
                }
            }
            //Ignore len if its not set in the vardef
            if ($key === 'len' && empty($fielddef2[$key])) {
                continue;
            }
            // if the length in db is greather than the vardef, ignore it
            if ($key === 'len' && ($value >= $fielddef2[$key])) {
                continue;
            }

            return false;
        }

        return true;
    }

    /**
     * Compare a field in two tables
     * @param string $name field name
     * @param string $table1
     * @param string $table2
     * @return array  array with keys 'msg','table1','table2'
     * @deprecated
     */
    public function compareFieldInTables(string $name, string $table1, string $table2)
    {
        $row1 = $this->describeField($name, $table1);
        $row2 = $this->describeField($name, $table2);
        $return_array = array(
            'table1' => $row1,
            'table2' => $row2,
            'msg' => 'error',
        );

        $ignore_filter = array('Key' => 1);
        if ($row1) {
            if (!$row2) {
                // Exists on table1 but not table2
                $return_array['msg'] = 'not_exists_table2';
            } else {
                if (count($row1) !== count($row2)) {
                    $return_array['msg'] = 'no_match';
                } else {
                    $return_array['msg'] = 'match';
                    foreach ($row1 as $key => $value) {
                        //ignore keys when checking we will check them when we do the index check
                        if (!isset($ignore_filter[$key]) && (!isset($row2[$key]) || $value !== $row2[$key])) {
                            $return_array['msg'] = 'no_match';
                        }
                    }
                }
            }
        } else {
            $return_array['msg'] = 'not_exists_table1';
        }

        return $return_array;
    }


    /**
     * Creates an index identified by name on the given fields.
     *
     * @param SugarBean $bean SugarBean instance
     * @param array $field_definitions Field definitions, in vardef format
     * @param string $name index name
     * @param bool $unique optional, true if we want to create an unique index
     * @return bool query result
     */
    public function createIndex(SugarBean $bean, array $field_definitions, string $name, bool $unique = true): bool
    {
        $sql = $this->createIndexSQL($bean, $field_definitions, $name, $unique);
        $table_name = $bean->getTableName();
        $msg = "Error creating index $name on table: $table_name:";

        return $this->query($sql, true, $msg);
    }

    /**
     * returns a SQL query that creates the indices as defined in metadata
     * @param array $indices Assoc array with index definitions from vardefs
     * @param string $table Focus table
     * @return array  Array of SQL queries to generate indices
     */
    public function getConstraintSql($indices, $table)
    {
        if (!$this->isFieldArray($indices)) {
            $indices = array($indices);
        }

        $columns = array();

        foreach ($indices as $index) {
            if (!empty($index['db']) && ($index['db'] !== $this->dbType)) {
                continue;
            }
            if (isset($index['source']) && $index['source'] !== 'db') {
                continue;
            }

            $sql = $this->addDropConstraint($table, $index);

            if (!empty($sql)) {
                $columns[] = $sql;
            }
        }

        return $columns;
    }

    /**
     * Adds a new indexes
     *
     * @param string $table_name
     * @param array $indexes indexes to add
     * @param bool $execute true if we want to execute the returned sql statement
     * @return string SQL statement
     */
    public function addIndexes(string $table_name, $indexes, bool $execute = true): string
    {
        $alters = $this->getConstraintSql($indexes, $table_name);
        if ($execute) {
            foreach ($alters as $sql) {
                $this->query($sql, true, 'Error adding index: ');
            }
        }
        if (!empty($alters)) {
            $sql = implode(";\n", $alters) . ";\n";
        } else {
            $sql = '';
        }

        return $sql;
    }

    /**
     * Drops indexes
     *
     * @param string $table_name
     * @param array $indexes indexes to drop
     * @param bool $execute true if we want to execute the returned sql statement
     * @return string SQL statement
     */
    public function dropIndexes(string $table_name, array $indexes, bool $execute = true): string
    {
        $sqls = array();
        foreach ($indexes as $index) {
            $name = $index['name'];
            $sqls[$name] = $this->addDropConstraint($table_name, $index, true);
        }
        if (!empty($sqls) && $execute) {
            foreach ($sqls as $name => $sql) {
                unset(self::$index_descriptions[$table_name][$name]);
                $this->query($sql);
            }
        }
        if (!empty($sqls)) {
            return implode(";\n", $sqls) . ';';
        }
        return '';
    }

    /**
     * Modifies indexes
     *
     * @param string $table_name
     * @param array $indexes indexes to modify
     * @param bool $execute true if we want to execute the returned sql statement
     * @return string SQL statement
     */
    public function modifyIndexes(string $table_name, array $indexes, bool $execute = true): string
    {
        return $this->dropIndexes($table_name, $indexes, $execute) . "\n" .
            $this->addIndexes($table_name, $indexes, $execute);
    }

    /**
     * Adds a column to table identified by field def.
     *
     * @param string $table_name
     * @param array $field_definitions
     * @return bool query result
     */
    public function addColumn(string $table_name, array $field_definitions)
    {
        $sql = $this->addColumnSQL($table_name, $field_definitions);
        if ($this->isFieldArray($field_definitions)) {
            $columns = array();
            foreach ($field_definitions as $field_definition) {
                $columns[] = $field_definition['name'];
            }
            $columns = implode(',', $columns);
        } else {
            $columns = $field_definitions['name'];
        }
        $msg = "Error adding column(s) $columns on table: $table_name:";

        return $this->query($sql, true, $msg);
    }

    /**
     * Alters old column identified by oldFieldDef to new fieldDef.
     *
     * @param string $table_name
     * @param array $newFieldDef
     * @param bool $ignoreRequired optional, true if we are ignoring this being a required field
     * @return bool query result
     */
    public function alterColumn(string $table_name, array $newFieldDef, bool $ignoreRequired = false)
    {
        $sql = $this->alterColumnSQL($table_name, $newFieldDef, $ignoreRequired);
        if ($this->isFieldArray($newFieldDef)) {
            $columns = array();
            foreach ($newFieldDef as $field_definition) {
                $columns[] = $field_definition['name'];
            }
            $columns = implode(',', $columns);
        } else {
            $columns = $newFieldDef['name'];
        }

        $msg = "Error altering column(s) $columns on table: $table_name:";
        $res = $this->query($sql, true, $msg);
        if ($res) {
            $this->getTableDescription($table_name, true); // reload table description after altering
        }

        return $res;
    }

    /**
     * Drops the table associated with a bean
     *
     * @param SugarBean $bean SugarBean instance
     * @return bool query result
     */
    public function dropTable(SugarBean $bean)
    {
        return $this->dropTableName($bean->getTableName());
    }

    /**
     * Drops the table by name
     *
     * @param string $name Table name
     * @return bool query result
     */
    public function dropTableName(string $name)
    {
        $sql = $this->dropTableNameSQL($name);

        return $this->query($sql, true, "Error dropping table $name:");
    }

    /**
     * Deletes a column identified by fieldDef.
     *
     * @param SugarBean $bean SugarBean containing the field
     * @param array $field_definitions Vardef definition of the field
     * @return bool query result
     */
    public function deleteColumn(SugarBean $bean, array $field_definitions)
    {
        $table_name = $bean->getTableName();
        $sql = $this->dropColumnSQL($table_name, $field_definitions);
        $msg = "Error deleting column(s) on table: $table_name:";

        return $this->query($sql, true, $msg);
    }

    /**
     * Generate a set of Insert statements based on the bean given
     *
     * @param SugarBean $bean the bean from which table we will generate insert stmts
     * @param string $select_query the query which will give us the set of objects that
     * we want to place into our insert statement
     * @param int $start the first row to query
     * @param int $count the number of rows to query
     * @param string $table the table to query from
     * @param bool $is_related_query
     * @return array SQL insert statement
     * @deprecated
     *
     */
    public function generateInsertSQL(
        SugarBean $bean,
        string $select_query,
        int    $start,
        int    $count = -1,
        string $table = '',
        bool   $is_related_query = false
    ) : array
    {
        $this->log->info('call to DBManager::generateInsertSQL() is deprecated');

        if (!$table) {
            $GLOBALS['log']->fatal('empty table name');
        }

        global $sugar_config;

        $rows_found = 0;
        $count_query = $bean->create_list_count_query($select_query);
        if (!empty($count_query)) {
            // We have a count query.  Run it and get the results.
            $result = $this->query($count_query, true, "Error running count query for $this->object_name List: ");
            $assoc = $this->fetchByAssoc($result);
            if (!empty($assoc['c'])) {
                $rows_found = $assoc['c'];
            }
        }
        if ($count === -1) {
            $count = $sugar_config['list_max_entries_per_page'];
        }
        $next_offset = $start + $count;

        $result = $this->limitQuery($select_query, $start, $count);
        // get basic insert
        $sql = 'INSERT INTO ' . $table;
        $custom_sql = 'INSERT INTO ' . $table . '_cstm';

        // get field definitions
        $fields = $bean->getFieldDefinitions();
        $custom_fields = array();

        if ($bean->hasCustomFields()) {
            foreach ($fields as $field_definition) {
                if ($field_definition['source'] === 'custom_fields') {
                    $custom_fields[$field_definition['name']] = $field_definition['name'];
                }
            }
            if (!empty($custom_fields)) {
                $custom_fields['id_c'] = 'id_c';
                $id_field = array('name' => 'id_c', 'custom_type' => 'id',);
                $fields[] = $id_field;
            }
        }

        // get column names and values
        $row_array = array();
        $columns = array();
        $cstm_row_array = array();
        $cstm_columns = array();
        $built_columns = false;
        while (($row = $this->fetchByAssoc($result)) !== null) {
            $values = array();
            $cstm_values = array();
            if (!$is_related_query) {
                foreach ($fields as $field_definition) {
                    if (isset($field_definition['source']) && $field_definition['source'] !== 'db' && $field_definition['source'] !== 'custom_fields') {
                        continue;
                    }
                    $val = $row[$field_definition['name']];

                    //handle auto increment values here only need to do this on insert not create
                    if ($field_definition['name'] === 'deleted') {
                        $values['deleted'] = $val;
                        if (!$built_columns) {
                            $columns[] = 'deleted';
                        }
                    } else {
                        $type = $field_definition['type'];
                        if (!empty($field_definition['custom_type'])) {
                            $type = $field_definition['custom_type'];
                        }
                        // need to do some thing about types of values
                        if ($this->dbType === 'mysql' && $val === ''
                            && ($type === 'datetime' || $type === 'date' || $type === 'int' || $type === 'currency' || $type === 'decimal')) {
                            if (!empty($custom_fields[$field_definition['name']])) {
                                $cstm_values[$field_definition['name']] = 'null';
                            } else {
                                $values[$field_definition['name']] = 'null';
                            }
                        } else {
                            if (isset($type) && $type === 'int') {
                                if (!empty($custom_fields[$field_definition['name']])) {
                                    $cstm_values[$field_definition['name']] = DBManagerFactory::getInstance() !== null
                                        ? DBManagerFactory::getInstance()->quote(from_html($val))
                                        : null;
                                } else {
                                    $values[$field_definition['name']] = DBManagerFactory::getInstance() !== null
                                        ? DBManagerFactory::getInstance()->quote(from_html($val))
                                        : null;
                                }
                            } else {
                                if (!empty($custom_fields[$field_definition['name']])) {
                                    $cstm_values[$field_definition['name']] = '\'' . (DBManagerFactory::getInstance() !== null
                                            ? DBManagerFactory::getInstance()->quote(from_html($val))
                                            : null) . '\'';
                                } else {
                                    $values[$field_definition['name']] = '\'' . (DBManagerFactory::getInstance() !== null
                                            ? DBManagerFactory::getInstance()->quote(from_html($val))
                                            : null) . '\'';
                                }
                            }
                        }
                        if (!$built_columns) {
                            if (!empty($custom_fields[$field_definition['name']])) {
                                $cstm_columns[] = $field_definition['name'];
                            } else {
                                $columns[] = $field_definition['name'];
                            }
                        }
                    }
                }
            } else {
                foreach ($row as $key => $val) {
                    if ($key !== 'orc_row') {
                        $values[$key] = "'$val'";
                        if (!$built_columns) {
                            $columns[] = $key;
                        }
                    }
                }
            }
            $built_columns = true;
            if (!empty($values)) {
                $row_array[] = $values;
            }
            if (!empty($cstm_values) && !empty($cstm_values['id_c']) && (strlen($cstm_values['id_c']) > 7)) {
                $cstm_row_array[] = $cstm_values;
            }
        }

        //if (sizeof ($values) == 0) return ""; // no columns set

        // get the entire sql
        $sql .= '(' . implode(',', $columns) . ') ';
        $sql .= 'VALUES';
        foreach ($row_array as $i => $iValue) {
            $sql .= ' (' . implode(',', $iValue) . ')';
            if ($i < (count($row_array) - 1)) {
                $sql .= ', ';
            }
        }
        //custom
        // get the entire sql
        $custom_sql .= '(' . implode(',', $cstm_columns) . ') ';
        $custom_sql .= 'VALUES';

        foreach ($cstm_row_array as $i => $iValue) {
            $custom_sql .= ' (' . implode(',', $iValue) . ')';
            if ($i < (count($cstm_row_array) - 1)) {
                $custom_sql .= ', ';
            }
        }

        return array(
            'data' => $sql,
            'cstm_sql' => $custom_sql, /*'result_count' => $row_count, */
            'total_count' => $rows_found,
            'next_offset' => $next_offset
        );
    }

    /**
     * @deprecated
     * Disconnects all instances
     */
    public function disconnectAll(): void
    {
        DBManagerFactory::disconnectAll();
    }

    /**
     * This function sets the query threshold limit
     *
     * @param int $limit value of query threshold limit
     */
    public static function setQueryLimit(int $limit): void
    {
        //reset the queryCount
        self::$queryCount = 0;
        self::$queryLimit = $limit;
    }

    /**
     * Returns the static queryCount value
     *
     * @return int value of the queryCount static variable
     */
    public static function getQueryCount(): int
    {
        return self::$queryCount;
    }

    /**
     * Resets the queryCount value to 0
     *
     */
    public static function resetQueryCount(): void
    {
        self::$queryCount = 0;
    }

    /**
     * This function increments the global $sql_queries variable
     */
    public function countQuery(): void
    {
        if (self::$queryLimit !== 0 && ++self::$queryCount > self::$queryLimit
            && (empty($GLOBALS['current_user']) || !is_admin($GLOBALS['current_user']))
        ) {
            require_once('include/resource/ResourceManager.php');
            $resource_manager = ResourceManager::getInstance();
            if ($resource_manager !== null) {
                $resource_manager->notifyObservers('ERR_QUERY_LIMIT');
            }
        }
    }

    /**
     * Pre-process string for quoting
     * @param string $string
     * @return string
     * @internal
     */
    protected function quoteInternal(string $string): string
    {
        return from_html($string);
    }

    /**
     * Return string properly quoted with ''
     * @param string $string
     * @return string
     */
    public function quoted(string $string): string
    {
        return '\'' . $this->quote($string) . '\'';
    }

    /**
     * Quote value according to type
     * Numerics aren't quoted
     * Dates are converted and quoted
     * Rest is just quoted
     * @param string $type
     * @param string $value
     * @return string Quoted value
     */
    public function quoteType(string $type, string $value): string
    {
        if ($type === 'date') {
            return $this->convert($this->quoted($value), 'date');
        }
        if ($type === 'time') {
            return $this->convert($this->quoted($value), 'time');
        }
        if (isset($this->type_class[$type]) && $this->type_class[$type] === 'date') {
            return $this->convert($this->quoted($value), 'datetime');
        }
        if ($this->isNumericType($type)) {
            return (int)$value; // ensure it's numeric
        }

        return $this->quoted($value);
    }

    /**
     * Quote the strings of the passed in array
     *
     * The array must only contain strings
     *
     * @param array $array
     * @return array Quoted strings
     */
    public function arrayQuote(array &$array): array
    {
        foreach ($array as &$val) {
            $val = $this->quote($val);
        }

        return $array;
    }

    /**
     * Frees out previous results
     *
     * @param bool $result optional, pass if you want to free a single result instead of all results
     */
    protected function freeResult(bool $result = false): void
    {
        if ($result) {
            $this->freeDbResult($result);
        }
        if ($this->lastResult) {
            $this->freeDbResult($this->lastResult);
            $this->lastResult = null;
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
        return false;
    }

    /**
     * Runs a query and returns a single row containing single value
     *
     * @param string $sql SQL Statement to execute
     * @param bool $dieOnError True if we want to call die if the query returns errors
     * @param string $msg Message to log if error occurs
     * @return array    single value from the query
     */
    public function getOne(string $sql, bool $dieOnError = false, string $msg = ''): array
    {
        $this->log->info("Get One: |$sql|");
        if (!$this->hasLimit($sql)) {
            $queryresult = $this->limitQuery($sql, 0, 1, $dieOnError, $msg);
        } else {
            // support old code that passes LIMIT to sql
            // works only for mysql, so do not rely on this
            $queryresult = $this->query($sql, $dieOnError, $msg);
        }
        $this->checkError($msg . ' Get One Failed:' . $sql, $dieOnError);
        if (!$queryresult) {
            return [];
        }
        $row = $this->fetchByAssoc($queryresult);
        if (!empty($row)) {
            return array_shift($row);
        }

        return [];
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
        $this->log->info("Fetch One: |$sql|");
        $this->checkConnection();
        $queryresult = $this->query($sql, $dieOnError, $msg);
        $this->checkError($msg . ' Fetch One Failed:' . $sql, $dieOnError);

        if (!$queryresult) {
            return [];
        }

        $row = $this->fetchByAssoc($queryresult);
        if (!$row) {
            return [];
        }

        $this->freeResult($queryresult);

        return $row;
    }

    /**
     * Returns the number of rows affected by the last query
     * @abstract
     * See also affected_rows capability, will return 0 unless the DB supports it
     * @param resource $result query result resource
     * @return int
     */
    public function getAffectedRowCount($result): int
    {
        return 0;
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
    public function getRowCount($result): int
    {
        return 0;
    }

    /**
     * Get table description
     * @param string $table_name
     * @param bool $reload true means load from DB, false allows using cache
     * @return array Vardef-format table description
     *
     */
    public function getTableDescription(string $table_name, bool $reload = false): array
    {
        if ($reload || empty(self::$table_descriptions[$table_name])) {
            self::$table_descriptions[$table_name] = $this->get_columns($table_name);
        }

        return self::$table_descriptions[$table_name];
    }

    /**
     * Returns the field description for a given field in table
     *
     * @param string $name
     * @param string $table_name
     * @return array
     */
    protected function describeField(string $name, string $table_name): array
    {
        $table = $this->getTableDescription($table_name);
        if (!empty($table) && isset($table[$name])) {
            return $table[$name];
        }

        $table = $this->getTableDescription($table_name, true);

        if (isset($table[$name])) {
            return $table[$name];
        }

        return array();
    }

    /**
     * Returns the index description for a given index in table
     *
     * @param string $name
     * @param string $table_name
     * @return array
     */
    protected function describeIndex(string $name, string $table_name): array
    {
        if (isset(self::$index_descriptions[$table_name][$name])) {
            return self::$index_descriptions[$table_name][$name];
        }

        self::$index_descriptions[$table_name] = $this->get_indices($table_name);

        if (isset(self::$index_descriptions[$table_name][$name])) {
            return self::$index_descriptions[$table_name][$name];
        }

        return array();
    }

    /**
     * Truncates a string to a given length
     *
     * @param string $string
     * @param int $len length to trim to
     * @return string
     *
     */
    public function truncate(string $string, int $len = 0): string
    {
        if ($len > 0) {
            $string = mb_substr($string, 0, (int)$len, 'UTF-8');
        }

        return $string;
    }

    /**
     * Returns the database string needed for concatinating multiple database strings together
     *
     * @param string $table table name of the database fields to concat
     * @param array $fields fields in the table to concat together
     * @param string $space Separator between strings, default is single space
     * @return string
     */
    public function concat(string $table, array $fields, string $space = ' '): string
    {
        if (empty($fields)) {
            return '';
        }
        $elems = array();
        $space = $this->quoted($space);
        foreach ($fields as $field) {
            if (!empty($elems)) {
                $elems[] = $space;
            }
            $elems[] = $this->convert("$table.$field", 'IFNULL', array('\'\''));
        }
        $first = array_shift($elems);

        return 'LTRIM(RTRIM(' . $this->convert($first, 'CONCAT', $elems) . '))';
    }

    /**
     * Given a sql stmt attempt to parse it into the sql and the tokens. Then return the index of this prepared statement
     * Tokens can come in the following forms:
     * ? - a scalar which will be quoted
     * ! - a literal which will not be quoted
     * & - binary data to read from a file
     *
     * @param string $sql The sql to parse
     * @return int index of the prepared statement to be used with execute
     */
    public function prepareQuery(string $sql): int
    {
        // Parse out the tokens
        // - Don't select the "!" in "!=".
        // - No backslashes before tokens.
        // - Only detect "&", "?", or "!".
        $tokens = preg_split('/((?<!\\\)(?!!=)[&?!])/', $sql, -1, PREG_SPLIT_DELIM_CAPTURE);

        // Maintain a count of the actual tokens for quick reference in execute
        $count = 0;

        $sql_str = '';
        foreach ($tokens as $key => $val) {
            switch ($val) {
                case '?':
                case '!':
                case '&':
                    $count++;
                $sql_str .= '?';
                    break;

                default:
                    //escape any special characters
                    $tokens[$key] = preg_replace('/\\\([&?!])/', "\\1", (string)$val);
                    $sql_str .= $tokens[$key];
                    break;
            } // switch
        } // foreach

        $this->preparedTokens[] = array('tokens' => $tokens, 'tokenCount' => $count, 'sqlString' => $sql_str);

        return array_key_last($this->preparedTokens);
    }

    /**
     * Takes a prepared stmt index and the data to replace and creates the query and runs it.
     *
     * @param int $stmt The index of the prepared statement from preparedTokens
     * @param array $data The array of data to replace the tokens with.
     * @return resource result set or false on error
     * @deprecated This is no longer used and will be removed in a future release. See createPreparedQuery() for an alternative.
     *
     */
    public function executePreparedQuery(int $stmt, array $data = array())
    {
        if (!empty($this->preparedTokens[$stmt])) {
            if (!is_array($data)) {
                $data = array($data);
            }
            $prepared_tokens = $this->preparedTokens[$stmt];
            //ensure that the number of data elements matches the number of replacement tokens
            //we found in prepare().
            if (count($data) !== $prepared_tokens['tokenCount']) {
                //error the data count did not match the token count
                return false;
            }
            $query = '';
            $data_index = 0;
            $tokens = $prepared_tokens['tokens'];
            foreach ($tokens as $val) {
                switch ($val) {
                    case '?':
                        $query .= $this->quote($data[$data_index++]);
                        break;
                    case '&':
                        $filename = $data[$data_index++];
                        $query .= file_get_contents($filename);
                        break;
                    case '!':
                        $query .= $data[$data_index++];
                        break;
                    default:
                        $query .= $val;
                        break;
                }//switch
            }//foreach
            return $this->query($query);
        }
        return false;
    }

    /**
     * Takes a prepared stmt index and the data to replace and creates the query and runs it.
     *
     * @param int $stmt The index of the prepared statement from preparedTokens
     * @param array $data The array of data to replace the tokens with.
     * @return resource result set or false on error
     */
    public function createPreparedQuery(int $stmt, array $data = array())
    {
        return $this->executePreparedQuery($stmt, $data);
    }

    /**
     * Run both prepare and execute without the client having to run both individually.
     *
     * @param string $sql The sql to parse
     * @param array $data The array of data to replace the tokens with.
     * @return resource result set or false on error
     */
    public function pQuery(string $sql, array $data = array())
    {
        $stmt = $this->prepareQuery($sql);

        $query = $this->createPreparedQuery($stmt, $data);

        if ($query === false) {
            return false;
        }

        return $this->query($query);
    }

    /********************** SQL FUNCTIONS ****************************/
    /**
     * Generates sql for create table statement for a bean.
     *
     * NOTE: does not handle out-of-table constraints, use createConstraintSQL for that
     * @param SugarBean $bean SugarBean instance
     * @return string SQL Create Table statement
     */
    public function createTableSQL(SugarBean $bean): string
    {
        $table_name = $bean->getTableName();
        $field_definitions = $bean->getFieldDefinitions();
        $indices = $bean->getIndices();

        return $this->createTableSQLParams($table_name, $field_definitions, $indices);
    }

    /**
     * Generates SQL for insert statement.
     *
     * @param SugarBean $bean SugarBean instance
     * @return string SQL Create Table statement
     */
    public function insertSQL(SugarBean $bean)
    {
        // get column names and values
        return $this->insertParams(
            $bean->getTableName(),
            $bean->getFieldDefinitions(),
            get_object_vars($bean),
            $bean->field_name_map ?? null,
            false
        );
    }

    /**
     * Generates SQL for update statement.
     *
     * @param SugarBean $bean SugarBean instance
     * @param array $where Optional, where conditions in an array
     * @return string SQL Create Table statement
     */
    public function updateSQL(SugarBean $bean, array $where = array()): string
    {
        $primary_field = $bean->getPrimaryFieldDefinition();
        $columns = array();
        $fields = $bean->getFieldDefinitions();
        // get column names and values
        if (!is_array($fields) && !is_object($fields)) {
            $GLOBALS['log']->fatal('Field Definition should be an array.');
        } else {
            foreach ((array)$fields as $field_key => $field_definition) {
                $val = '';
                if (isset($field_definition['source']) && $field_definition['source'] !== 'db') {
                    continue;
                }// Do not write out the id field on the update statement.
                // We are not allowed to change ids.
                if (empty($field_definition['name']) || $field_definition['name'] === $primary_field['name']) {
                    continue;
                }

                // If the field is an auto_increment field, then we shouldn't be setting it.  This was added
                // specially for Bugs and Cases which have a number associated with them.
                if (!empty($bean->field_name_map[$field_key]['auto_increment'])) {
                    continue;
                }

                //custom fields handle their save separately
                if (isset($bean->field_name_map) && !empty($bean->field_name_map[$field_key]['custom_type'])) {
                    continue;
                }

                // no need to clear deleted since we only update not deleted records anyway
                if ($field_definition['name'] === 'deleted' && empty($bean->deleted)) {
                    continue;
                }

                if (isset($bean->$field_key)) {
                    $val = from_html($bean->$field_key);
                } else {
                    continue;
                }

                if (!empty($field_definition['type']) && $field_definition['type'] === 'bool') {
                    $val = $bean->getFieldValue($field_key);
                }

                if ((string)$val === '') {
                    if (isset($field_definition['default']) && strlen((string)$field_definition['default']) > 0) {
                        $val = $field_definition['default'];
                    } else {
                        $val = null;
                    }
                }

                if (!empty($val) && !empty($field_definition['len']) && strlen((string)$val) > $field_definition['len']) {
                    $val = $this->truncate($val, $field_definition['len']);
                }

                if (!empty($bean->bean_fields_to_save) && !in_array($field_definition['name'], $bean->bean_fields_to_save, true)) {
                    continue;
                }

                $column_name = $this->quoteIdentifier($field_definition['name']);
                if (!is_null($val) || !empty($field_definition['required'])) {
                    $columns[] = "{$column_name}=" . $this->massageValue($val, $field_definition);
                } elseif ($this->isNullable($field_definition)) {
                    $columns[] = "{$column_name}=NULL";
                } else {
                    $columns[] = "{$column_name}=" . $this->emptyValue($field_definition['type']);
                }
            }
        }

        if (count($columns) === 0) {
            return '';
        } // no columns set

        // build where clause
        $where = $this->getWhereClause($bean, $this->updateWhereArray($bean, $where));
        if (isset($fields['deleted'])) {
            $where .= ' AND deleted=0';
        }

        return sprintf('UPDATE %s SET %s %s',
            $bean->getTableName(),
            implode(',', $columns),
            $where);
    }

    /**
     * This method returns a where array so that it has id entry if
     * where is not an array or is empty
     *
     * @param SugarBean $bean SugarBean instance
     * @param array $where Optional, where conditions in an array
     * @return array
     */
    protected function updateWhereArray(SugarBean $bean, array $where = array()): array
    {
        if (count($where) === 0) {
            $field_definition = $bean->getPrimaryFieldDefinition();
            $primaryColumn = $field_definition['name'];

            $val = $bean->getFieldValue($field_definition['name']);
            if (!empty($val)) {
                $where[$primaryColumn] = $val;
            }
        }

        return $where;
    }

    /**
     * Returns a where clause without the 'where' key word
     *
     * The clause returned does not have an 'and' at the beginning and the columns
     * are joined by 'and'.
     *
     * @param string $table table name
     * @param array $whereArray Optional, where conditions in an array
     * @return string
     */
    protected function getColumnWhereClause(string $table, array $whereArray = array()): string
    {
        $where = array();
        foreach ($whereArray as $name => $val) {
            $op = '=';
            if (is_array($val)) {
                $op = 'IN';
                $temp = array();
                foreach ($val as $tval) {
                    $temp[] = $this->quoted($tval);
                }
                $val = implode(',', $temp);
                $val = "($val)";
            } else {
                $val = $this->quoted($val);
            }

            $where[] = " $table.$name $op $val";
        }

        if (!empty($where)) {
            return implode(' AND ', $where);
        }

        return '';
    }

    /**
     * This method returns a complete where clause built from the
     * where values specified.
     *
     * @param SugarBean $bean SugarBean that describes the table
     * @param array $whereArray Optional, where conditions in an array
     * @return string
     */
    protected function getWhereClause(SugarBean $bean, array $whereArray = array()): string
    {
        return ' WHERE ' . $this->getColumnWhereClause($bean->getTableName(), $whereArray);
    }

    /**
     * Outputs a correct string for the sql statement according to value
     *
     * @param mixed $val
     * @param array $field_definition field definition
     * @return mixed
     */
    public function massageValue(mixed $val, array $field_definition): mixed
    {
        $type = $this->getFieldType($field_definition);

        if (isset($this->type_class[$type])) {
            // handle some known types
            switch ($this->type_class[$type]) {
                case 'bool':
                case 'int':
                if (!empty($field_definition['required']) && $val == '') {
                    if (isset($field_definition['default'])) {
                        return $field_definition['default'];
                        }

                        return 0;
                    }

                    return $val === '' ? 'NULL' : (int)$val; // Fix #9440 - Forcing default null value for numeric fields.
                case 'bigint':
                    $val = (float)$val;
                    if (!empty($field_definition['required']) && $val == false) {
                        if (isset($field_definition['default'])) {
                            return $field_definition['default'];
                        }

                        return 0;
                    }

                    return $val;
                case 'float':
                    if (!empty($field_definition['required']) && $val == '') {
                        if (isset($field_definition['default'])) {
                            return $field_definition['default'];
                        }

                        return 0;
                    }

                    return $val === '' ? 'NULL' : (float)$val; // Fix #9440 - Forcing default null value for numeric fields.
                case 'time':
                case 'date':
                    // empty date can't be '', so convert it to either NULL or empty date value
                if ($val === '') {
                    if (!empty($field_definition['required'])) {
                        if (isset($field_definition['default'])) {
                            return $field_definition['default'];
                            }

                            return $this->emptyValue($type);
                        }

                    return 'NULL';
                    }
                    break;
            }
        } else {
            if (!empty($val) && !empty($field_definition['len']) && strlen((string)$val) > $field_definition['len']) {
                $val = $this->truncate($val, $field_definition['len']);
            }
        }

        if (is_null($val)) {
            if (!empty($field_definition['required'])) {
                if (isset($field_definition['default']) && $field_definition['default'] !== '') {
                    return $field_definition['default'];
                }

                return $this->emptyValue($type);
            }
            return 'NULL';
        }
        if ($type === 'datetimecombo') {
            $type = 'datetime';
        }

        return $this->convert($this->quoted($val), $type);
    }

    /**
     * Massages the field defintions to fill in anything else the DB backend may add
     *
     * @param array $field_definition
     * @param string $table_name
     * @return array
     */
    public function massageFieldDef(array &$field_definition, string $table_name): array
    {
        if (!isset($field_definition['dbType'])) {
            if (isset($field_definition['dbtype'])) {
                $field_definition['dbType'] = $field_definition['dbtype'];
            } else {
                $field_definition['dbType'] = $field_definition['type'];
            }
        }
        $type = $this->getColumnType($field_definition['dbType'], $field_definition['name'], $table_name);
        $matches = array();
        // len can be a number or a string like 'max', for example, nvarchar(max)
        preg_match_all('/(\w+)(?:\(([0-9]+,?[0-9]*|\w+)\)|)/i', $type, $matches);
        if (isset($matches[1][0])) {
            $field_definition['type'] = $matches[1][0];
        }
        if (isset($matches[2][0]) && empty($field_definition['len'])) {
            $field_definition['len'] = $matches[2][0];
        }
        if (!empty($field_definition['precision']) && is_numeric($field_definition['precision']) && strpos((string)$field_definition['len'], ',') === false) {
            $field_definition['len'] .= ",{$field_definition['precision']}";
        }
        if (!empty($field_definition['required']) || ($field_definition['name'] === 'id' && !isset($field_definition['required']))) {
            $field_definition['required'] = 'true';
        }
    }

    /**
     * Take an SQL statement and produce a list of fields used in that select
     * @param string $select_statement
     * @return array
     */
    public function getSelectFieldsFromQuery(string $select_statement): array
    {
        $select_statement = trim($select_statement);
        if (stripos($select_statement, 'SELECT') === 0) {
            $select_statement = trim(substr($select_statement, 6));
        }

        //Due to sql functions existing in many selects, we can't use php explode
        $fields = array();
        $level = 0;
        $selected_field = '';
        $str_len = strlen($select_statement);
        for ($i = 0; $i < $str_len; $i++) {
            $char = $select_statement[$i];

            if ($char === ',' && $level === 0) {
                $field = $this->getFieldNameFromSelect(trim($selected_field));
                $fields[$field] = $selected_field;
                $selected_field = '';
            } elseif ($char === '(') {
                $level++;
                $selected_field .= $char;
            } else {
                if ($char === ')') {
                    $level--;
                }
                $selected_field .= $char;
            }
        }
        $fields[$this->getFieldNameFromSelect($selected_field)] = $selected_field;

        return $fields;
    }

    /**
     * returns the field name used in a select
     * @param string $string SELECT query
     * @return string
     */
    protected function getFieldNameFromSelect(string $string): string
    {
        if (strncasecmp($string, 'DISTINCT ', 9) === 0) {
            $string = substr($string, 9);
        }
        if (stripos($string, ' as ') !== false) { //"as" used for an alias
            return trim(substr($string, strripos($string, ' as ') + 4));
        }
        if (strrpos($string, ' ') !== 0) { //Space used as a delimiter for an alias
            return trim(substr($string, strrpos($string, ' ')));
        }
        if (strpos($string, '.') !== false) { //No alias, but a table.field format was used
            return substr($string, strpos($string, '.') + 1);
        }   //Give up and assume the whole thing is the field name
        return $string;
    }

    /**
     * Generates SQL for delete statement identified by id.
     *
     * @param SugarBean $bean SugarBean instance
     * @param array $where where conditions in an array
     * @return string SQL Update Statement
     */
    public function deleteSQL(SugarBean $bean, array $where): string
    {
        $sql_where = $this->getWhereClause($bean, $this->updateWhereArray($bean, $where));

        return 'UPDATE ' . $bean->getTableName() . ' SET deleted=1 ' . $sql_where;
    }

    /**
     * Generates SQL for select statement for any bean identified by id.
     *
     * @param SugarBean $bean SugarBean instance
     * @param array $where where conditions in an array
     * @return string SQL Select Statement
     */
    public function retrieveSQL(SugarBean $bean, array $where): string
    {
        $sql_where = $this->getWhereClause($bean, $this->updateWhereArray($bean, $where));

        return 'SELECT * FROM ' . $bean->getTableName() . ' ' . $sql_where . ' AND deleted=0';
    }

    /**
     * This method implements a generic sql for a collection of beans.
     *
     * Currently, this function does not support outer joins.
     *
     * @param array $beans Array of values returned by get_class method as the keys and a bean as
     *      the value for that key. These beans will be joined in the sql by the key
     *      attribute of field defs.
     * @param array $cols Optional, columns to be returned with the keys as names of bean
     *      as identified by get_class of bean. Values of this array is the array of fieldDefs
     *      to be returned for a bean. If an empty array is passed, all columns are selected.
     * @param array $whereClause Optional, values with the keys as names of bean as identified
     *      by get_class of bean. Each value at the first level is an array of values for that
     *      bean identified by name of fields. If we want to pass multiple values for a name,
     *      pass it as an array. If where is not passed, all the rows will be returned.
     *
     * @return string SQL Select Statement
     */
    public function retrieveViewSQL(array $beans, array $cols = array(), array $whereClause = array()): string
    {
        $relationship = []; // stores relations between tables as they are discovered
        $where = [];
        $select = [];
        $table = '';
        $aliases = [];
        foreach ($beans as $bean_id => $bean) {
            $table_name = $bean->getTableName();
            $bean_tables[$bean_id] = $table_name;

            $table = $bean_id;
            $tables[$table] = $table_name;
            $aliases[$table_name][] = $table;

            // build part of select for this table
            if (is_array($cols[$bean_id])) {
                foreach ($cols[$bean_id] as $def) {
                    $select[] = $table . '.' . $def['name'];
                }
            }

            // build part of where clause
            if (is_array($whereClause[$bean_id])) {
                $where[] = $this->getColumnWhereClause($table, $whereClause[$bean_id]);
            }
            // initialize so that it can be used properly in form clause generation
            $table_used_in_from[$table] = false;

            $indices = $bean->getIndices();
            foreach ($indices as $index) {
                if ($index['type'] === 'foreign') {
                    $relationship[$table][] = array(
                        'foreignTable' => $index['foreignTable'],
                        'foreignColumn' => $index['foreignField'],
                        'localColumn' => $index['fields']
                    );
                }
            }
            $where[] = " $table.deleted = 0";
        }

        // join these clauses
        $select = !empty($select) ? implode(',', $select) : '*';
        $where = implode(' AND ', $where);

        // generate the from clause. Use relations array to generate outer joins
        // all the rest of the tables will be used as a simple from
        // relations table define relations between table1 and table2 through column on table 1
        // table2 is assumed to joining through primary key called id
        $separator = '';
        $from = '';
        $table_used_in_from = array();
        foreach ($relationship as $right_table => $right_side_array) {
            if ($table_used_in_from[$right_table]) {
                continue;
            } // table has been joined

            $from .= $separator . ' ' . $right_table;
            $table_used_in_from[$right_table] = true;
            foreach ($right_side_array as $table_array) {
                $table2 = $table_array['foreignTable']; // get foreign table
                $table_alias = $aliases[$table2]; // get a list of aliases for this table
                foreach ($table_alias as $table2) {
                    //choose first alias that does not match
                    // we are doing this because of self joins.
                    // in case of self joins, the same table will have many aliases.
                    if ($table2 !== $right_table) {
                        break;
                    }
                }

                $col = $table_array['foreingColumn'];
                $name = $table_array['localColumn'];
                $from .= sprintf(' LEFT JOIN %s on (%s.%s = %s.%s)', $table, $right_table, $name, $table2, $col);
                $table_used_in_from[$table2] = true;
            }
            $separator = ',';
        }

        return sprintf('SELECT %s FROM %s WHERE %s', $select, $from, $where);
    }

    /**
     * Generates SQL for create index statement for a bean.
     *
     * @param SugarBean $bean SugarBean instance
     * @param array $fields fields used in the index
     * @param string $name index name
     * @param bool $unique Optional, set to true if this is an unique index
     * @return string SQL Select Statement
     */
    public function createIndexSQL(SugarBean $bean, array $fields, string $name, bool $unique = true): string
    {
        $index_type = $unique ? 'unique' : '';
        $table_name = $bean->getTableName();
        $columns = array();
        // get column names
        foreach ($fields as $field_info) {
            $columns[] = $field_info['name'];
        }

        if (empty($columns)) {
            return '';
        }

        $columns = implode(',', $columns);

        return sprintf('CREATE %s INDEX %s ON %s (%s)', $index_type, $name, $table_name, $columns);
    }

    /**
     * Returns the type of the variable in the field
     *
     * @param array $field_definition Vardef-format field def
     * @return string
     */
    public function getFieldType(array $field_definition): ?string
    {
        // get the type for db type. if that is not set,
        // get it from type. This is done so that
        // we do not have change a lot of existing code
        // and add dbtype where type is being used for some special
        // purposes like referring to foreign table etc.
        if (!empty($field_definition['dbType'])) {
            return $field_definition['dbType'];
        }
        if (!empty($field_definition['dbtype'])) {
            return $field_definition['dbtype'];
        }
        if (!empty($field_definition['type'])) {
            return $field_definition['type'];
        }
        if (!empty($field_definition['Type'])) {
            return $field_definition['Type'];
        }
        if (!empty($field_definition['data_type'])) {
            return $field_definition['data_type'];
        }

        return null;
    }

    /**
     * retrieves the different components from the passed column type as it is used in the type mapping and vardefs
     * type format: <baseType>[(<len>[,<scale>])]
     * @param string $type Column type
     * @return array|bool array containing the different components of the passed in type or false in case the type contains illegal characters
     */
    public function getTypeParts(string $type)
    {
        if (preg_match("#(?P<type>\w+)\s*(?P<arg>\((?P<len>\w+)\s*(,\s*(?P<scale>\d+))*\))*#", $type, $matches)) {
            $return = array();  // Not returning matches array as such as we don't want to expose the regex make up on the interface
            $return['baseType'] = $matches['type'];
            if (isset($matches['arg'])) {
                $return['arg'] = $matches['arg'];
            }
            if (isset($matches['len'])) {
                $return['len'] = $matches['len'];
            }
            if (isset($matches['scale'])) {
                $return['scale'] = $matches['scale'];
            }

            return $return;
        }
        return false;
    }

    /**
     * Returns the defintion for a single column
     *
     * @param array $field_definition Vardef-format field def
     * @param bool $ignore_required Optional, true if we should ignore this being a required field
     * @param string $table Optional, table name
     * @param bool $return_as_array Optional, true if we should return the result as an array instead of sql
     * @return string|array or array if $return_as_array is true
     */
    protected function oneColumnSQLRep(array $field_definition, bool $ignore_required = false, string $table = '', bool $return_as_array = false): string|array
    {
        $column_base_type = '';
        $len = '255';
        if (!isset($field_definition['name'])) {
            $GLOBALS['log']->fatal('"name" field does not exists in field definition.');
            $name = null;
        } else {
            $name = $field_definition['name'];
        }
        $type = $this->getFieldType($field_definition);
        $column_type = $this->getColumnType($type);

        if ($parts = $this->getTypeParts($column_type)) {
            $column_base_type = $parts['baseType'];
            $len = $parts['len'] ?? '255'; // Use the mappings length (precision) as default if it exists
        }

        if (!empty($field_definition['len'])) {
            if (in_array($column_base_type, array(
                'nvarchar',
                'nchar',
                'varchar',
                'varchar2',
                'char',
                'clob',
                'blob',
                'text'
            ))) {
                $column_type = "$column_base_type({$field_definition['len']})";
            } elseif (($column_base_type === 'decimal' || $column_base_type === 'float')) {
                if (!empty($field_definition['precision']) && is_numeric($field_definition['precision'])) {
                    if (!str_contains((string)$field_definition['len'], ',')) {
                        $column_type = $column_base_type . '(' . $field_definition['len'] . ',' . $field_definition['precision'] . ')';
                    } else {
                        $column_type = $column_base_type . '(' . $field_definition['len'] . ')';
                    }
                } else {
                    $column_type = $column_base_type . '(' . $field_definition['len'] . ')';
                }
            }
        } else {
            if (in_array($column_base_type, array('nvarchar', 'nchar', 'varchar', 'varchar2', 'char'))) {
                $column_type = "$column_base_type($len)";
            }
        }

        $default = '';

        // Bug #52610 We should have ability don't add DEFAULT part to query for boolean fields
        if (!empty($field_definition['no_default'])) {
            // nothing to do
        } elseif (isset($field_definition['default']) && (string)$field_definition['default'] !== '') {
            $default = ' DEFAULT ' . $this->quoted($field_definition['default']);
        } elseif (!isset($default) && $type === 'bool') {
            $default = ' DEFAULT 0 ';
        }

        $auto_increment = '';
        if (!empty($field_definition['auto_increment']) && $field_definition['auto_increment']) {
            $auto_increment = $this->setAutoIncrement($table, $field_definition['name']);
        }

        $required = 'NULL';  // MySQL defaults to NULL, SQL Server defaults to NOT NULL -- must specify
        //Starting in 6.0, only ID and auto_increment fields will be NOT NULL in the DB.
        if ((empty($field_definition['isnull']) || strtolower($field_definition['isnull']) === 'false') &&
            (!empty($auto_increment) || $name === 'id' || ($field_definition['type'] === 'id' && !empty($field_definition['required'])))
        ) {
            $required = 'NOT NULL';
        }
        // If the field is marked both required & isnull=>false - alwqys make it not null
        // Use this to ensure primary key fields never defined as null
        if (isset($field_definition['isnull']) && (strtolower($field_definition['isnull']) === 'false' || $field_definition['isnull'] === false)
            && !empty($field_definition['required'])
        ) {
            $required = 'NOT NULL';
        }
        if ($ignore_required) {
            $required = '';
        }

        if ($return_as_array) {
            return array(
                'name' => $name,
                'colType' => $column_type,
                'colBaseType' => $column_base_type,  // Adding base type for easier processing in derived classes
                'default' => $default,
                'required' => $required,
                'auto_increment' => $auto_increment,
                'full' => "$name $column_type $default $required $auto_increment",
            );
        }
        return sprintf("%s %s %s %s %s", $name, $column_type, $default, $required, $auto_increment);
    }

    /**
     * Returns SQL defintions for all columns in a table
     *
     * @param array $field_definition Vardef-format field def
     * @param bool $ignore_required Optional, true if we should ignore this being a required field
     * @param string|null $table_name Optional, table name
     * @return array|string SQL column definitions
     */
    protected function columnSQLRep(array $field_definition, bool $ignore_required, string $table_name = null): array|string
    {
        // set $ignoreRequired = false by default
        if (!is_bool($ignore_required)) {
            $ignore_required = false;
        }

        $columns = array();

        if ($this->isFieldArray($field_definition)) {
            foreach ($field_definition as $field) {
                if (!isset($field['source']) || $field['source'] === 'db') {
                    $columns[] = $this->oneColumnSQLRep($field, false, $table_name);
                }
            }
            $columns = implode(',', $columns);
        } else {
            $columns = $this->oneColumnSQLRep($field_definition, $ignore_required, $table_name);
        }

        return $columns;
    }

    /**
     * Returns the next value for an auto increment
     * @abstract
     * @param string $table Table name
     * @param string $field_name Field name
     * @return string
     */
    public function getAutoIncrement(string $table, string $field_name): string
    {
        return '';
    }

    /**
     * Returns the sql for the next value in a sequence
     * @abstract
     * @param string $table Table name
     * @param string $field_name Field name
     * @return string
     */
    public function getAutoIncrementSQL(string $table, string $field_name): string
    {
        return '';
    }

    /**
     * Either creates an auto increment through queries or returns sql for auto increment
     * that can be appended to the end of column defination (mysql)
     * @abstract
     * @param string $table Table name
     * @param string $field_name Field name
     * @return string
     */
    protected function setAutoIncrement(string $table, string $field_name): string
    {
        $this->deleteAutoIncrement($table, $field_name);

        return '';
    }

    /**
     * Sets the next auto-increment value of a column to a specific value.
     * @abstract
     * @param string $table Table name
     * @param string $field_name Field name
     * @param int $start_value Starting autoincrement value
     * @return string
     *
     */
    public function setAutoIncrementStart(string $table, string $field_name, int $start_value): string
    {
        return '';
    }

    /**
     * Deletes an auto increment
     * @abstract
     * @param string $table tablename
     * @param string $field_name
     */
    public function deleteAutoIncrement(string $table, string $field_name): string
    {
        return '';
    }

    /**
     * This method generates sql for adding a column to table identified by field def.
     *
     * @param string $table_name
     * @param array $field_definition
     * @return string SQL statement
     */
    public function addColumnSQL(string $table_name, array $field_definition): array|string
    {
        return $this->changeColumnSQL($table_name, $field_definition, 'add');
    }

    /**
     * This method genrates sql for altering old column identified by oldFieldDef to new fieldDef.
     *
     * @param string $table_name
     * @param array $newFieldDefs
     * @param bool $ignore_required Optional, true if we should ignor this being a required field
     * @return string|array SQL statement(s)
     */
    public function alterColumnSQL(string $table_name, array $newFieldDefs, bool $ignore_required = false): array|string
    {
        return $this->changeColumnSQL($table_name, $newFieldDefs, 'modify', $ignore_required);
    }

    /**
     * Generates SQL for dropping a table.
     *
     * @param SugarBean $bean Sugarbean instance
     * @return string SQL statement
     */
    public function dropTableSQL(SugarBean $bean): string
    {
        return $this->dropTableNameSQL($bean->getTableName());
    }

    /**
     * Generates SQL for dropping a table.
     *
     * @param string $name table name
     * @return string SQL statement
     */
    public function dropTableNameSQL(string $name): string
    {
        return 'DROP TABLE ' . $name;
    }

    /**
     * Generates SQL for truncating a table.
     * @param string $name table name
     * @return string
     */
    public function truncateTableSQL(string $name): string
    {
        return 'TRUNCATE ' . $name;
    }

    /**
     * This method generates sql that deletes a column identified by fieldDef.
     *
     * @param SugarBean $bean Sugarbean instance
     * @param array $field_definitions
     * @return string SQL statement
     */
    public function deleteColumnSQL(SugarBean $bean, array $field_definitions): array|string
    {
        return $this->dropColumnSQL($bean->getTableName(), $field_definitions);
    }

    /**
     * This method generates sql that drops a column identified by fieldDef.
     * Designed to work like the other addColumnSQL() and alterColumnSQL() functions
     *
     * @param string $table_name
     * @param array $field_definitions
     * @return string SQL statement
     */
    public function dropColumnSQL(string $table_name, array $field_definitions): array|string
    {
        return $this->changeColumnSQL($table_name, $field_definitions, 'drop');
    }

    /**
     * Return a version of $proposed that can be used as a column name in any of our supported databases
     * Practically this means no longer than 25 characters as the smallest identifier length for our supported DBs is 30 chars for Oracle plus we add on at least four characters in some places (for indicies for example)
     * @param array|string $name Proposed name for the column
     * @param bool|string $ensureUnique Ensure the name is unique
     * @param string $type Name type (table, column)
     * @param bool $force Force new name
     * @return string|array Valid column name trimmed to right length and with invalid characters removed
     */
    public function getValidDBName(array|string $name, bool|string $ensureUnique = false, string $type = 'column', bool $force = false): array|string
    {
        if (is_array($name)) {
            $result = array();
            foreach ($name as $field) {
                $result[] = $this->getValidDBName($field, $ensureUnique, $type);
            }

            return $result;
        }
        if (str_contains($name, '.')) {
            // this is a compound name with dots, handle separately
            $parts = explode('.', $name);
            if (count($parts) > 2) {
                // some weird name, cut to table.name
                array_splice($parts, 0, count($parts) - 2);
            }
            $parts = $this->getValidDBName($parts, $ensureUnique, $type, $force);

            return implode('.', $parts);
        }
        // first strip any invalid characters - all but word chars (which is alphanumeric and _)
        $name = preg_replace('/[^\w]+/i', '', $name);
        $len = strlen($name);
        $maxLen = empty($this->maxNameLengths[$type]) ? $this->maxNameLengths[$type]['column'] : $this->maxNameLengths[$type];
        if ($len <= $maxLen && !$force) {
            return strtolower($name);
        }
        if ($ensureUnique) {
            $md5str = md5($name);
            $tail = substr($name, -11);
            $temp = substr($md5str, strlen($md5str) - 4);
            $result = substr($name, 0, 10) . $temp . $tail;
        } else {
            $result = substr($name, 0, 11) . substr($name, 11 - $maxLen);
        }

        return strtolower($result);
    }

    /**
     * Returns the valid type for a column given the type in fieldDef
     *
     * @param string $type field type
     * @return string valid type for the given field
     */
    public function getColumnType(string $type): string
    {
        return $this->type_map[$type] ?? $type;
    }

    /**
     * Checks to see if passed array is truely an array of defitions
     *
     * Such an array may have type as a key but it will point to an array
     * for a true array of definitions an to a col type for a definition only
     *
     * @param mixed $defArray
     * @return bool
     */
    public function isFieldArray(mixed $defArray): bool
    {
        if (!is_array($defArray)) {
            return false;
        }

        if (isset($defArray['type'])) {
            // type key exists. May be an array of defs or a simple definition
            return is_array($defArray['type']); // type is not an array => definition else array
        }

        // type does not exist. Must be array of definitions
        return true;
    }

    /**
     * returns true if the type can be mapped to a valid column type
     *
     * @param string $type
     * @return bool
     */
    protected function validColumnType(string $type): bool
    {
        $type = $this->getColumnType($type);

        return !empty($type);
    }

    /**
     * Generate query for audit table
     * @param SugarBean $bean SugarBean that was changed
     * @param array $changes List of changes, contains 'before' and 'after'
     * @return string  Audit table INSERT query
     */
    protected function auditSQL(SugarBean $bean, array $changes): string
    {
        global $current_user;
        $sql = 'INSERT INTO ' . $bean->get_audit_table_name();
        //get field defs for the audit table.
        $dictionary = [];
        require('metadata/audit_templateMetaData.php');
        $field_definitions = $dictionary['audit']['fields'];

        $values = array();
        $values['id'] = $this->massageValue(create_guid(), $field_definitions['id']);
        $values['parent_id'] = $this->massageValue($bean->id, $field_definitions['parent_id']);
        $values['field_name'] = $this->massageValue($changes['field_name'], $field_definitions['field_name']);
        $values['data_type'] = $this->massageValue($changes['data_type'], $field_definitions['data_type']);
        if ($changes['data_type'] === 'text' || $changes['data_type'] === 'multienum') {
            $values['before_value_text'] = $this->massageValue($changes['before'], $field_definitions['before_value_text']);
            $values['after_value_text'] = $this->massageValue($changes['after'], $field_definitions['after_value_text']);
        } else {
            $values['before_value_string'] = $this->massageValue($changes['before'], $field_definitions['before_value_string']);
            $values['after_value_string'] = $this->massageValue($changes['after'], $field_definitions['after_value_string']);
        }
        $values['date_created'] = $this->massageValue(TimeDate::getInstance()->nowDb(), $field_definitions['date_created']);
        $values['created_by'] = $this->massageValue($current_user->id, $field_definitions['created_by']);

        $sql .= "(" . implode(",", array_keys($values)) . ") ";
        $sql .= "VALUES(" . implode(",", $values) . ")";

        return $sql;
    }

    /**
     * Saves changes to module's audit table
     *
     * @param SugarBean $bean Sugarbean instance that was changed
     * @param array $changes List of changes, contains 'before' and 'after'
     * @return bool query result
     *
     */
    public function save_audit_records(SugarBean $bean, array $changes)
    {
        return $this->query($this->auditSQL($bean, $changes));
    }

    /**
     * Finds fields whose value has changed.
     * The before and after values are stored in the bean.
     * Uses $bean->fetched_row && $bean->fetched_rel_row to compare
     *
     * @param SugarBean $bean Sugarbean instance that was changed
     * @param array|null $field_filter Array of filter names to be inspected (NULL means all fields)
     * @return array
     */
    public function getDataChanges(SugarBean &$bean, array $field_filter = null): array
    {
        $bean->fixUpFormatting();
        $changed_values = array();

        $fetched_row = array();
        if (is_array($bean->fetched_row)) {
            $fetched_row = array_merge($bean->fetched_row, $bean->fetched_rel_row);
        }

        if ($fetched_row) {
            $field_defs = $bean->field_defs;

            if (is_array($field_filter)) {
                $field_defs = array_intersect_key($field_defs, array_flip($field_filter));
            }

            // remove fields which do not present in fetched row
            $field_defs = array_intersect_key($field_defs, $fetched_row);

            // remove fields which do not exist as bean property
            $field_defs = array_intersect_key($field_defs, (array)$bean);

            foreach ($field_defs as $field => $properties) {
                $before_value = from_html($fetched_row[$field] ?? '') ?? '';
                $after_value = $bean->$field;
                if (isset($properties['type'])) {
                    $field_type = $properties['type'];
                } else {
                    if (isset($properties['dbType'])) {
                        $field_type = $properties['dbType'];
                    } else {
                        if (isset($properties['data_type'])) {
                            $field_type = $properties['data_type'];
                        } else {
                            $field_type = $properties['dbtype'];
                        }
                    }
                }

                //Because of bug #25078(sqlserver haven't 'date' type, trim extra "00:00:00" when insert into *_cstm table).
                // so when we read the audit datetime field from sqlserver, we have to replace the extra "00:00:00" again.
                if (!empty($field_type) && $field_type === 'date') {
                    $before_value = $this->fromConvert($before_value, $field_type) ?? '';
                }
                //if the type and values match, do nothing.
                if (!($this->_emptyValue($before_value, $field_type) && $this->_emptyValue(
                        $after_value,
                        $field_type
                    ))
                ) {
                    $change = false;
                    if (trim((string)$before_value) !== trim((string)$after_value)) {
                        // decode value for field type of 'text' or 'varchar' to check before audit if the value contain trip tags or special character
                        if ($field_type === 'varchar' || $field_type === 'name' || $field_type === 'text') {
                            $decode_before_value = strip_tags(html_entity_decode((string)$before_value));
                            $decode_after_value = strip_tags(html_entity_decode((string)$after_value));
                            if ($decode_before_value === $decode_after_value) {
                                continue;
                            }
                            $change = true;
                        }
                        // Bug #42475: Don't directly compare numeric values, instead do the subtract and see if the comparison comes out to be "close enough", it is necessary for floating point numbers.
                        // Manual merge of fix 95727f2eed44852f1b6bce9a9eccbe065fe6249f from DBHelper
                        // This fix also fixes Bug #44624 in a more generic way and therefore eliminates the need for fix 0a55125b281c4bee87eb347709af462715f33d2d in DBHelper
                        elseif ($this->isNumericType($field_type)) {
                            $numerator = abs(2 * ((trim((float)$before_value) + 0) - (trim((float)$after_value) + 0)));
                            $denominator = abs(((trim((float)$before_value) + 0) + (trim((float)$after_value) + 0)));
                            // detect whether to use absolute or relative error. use absolute if denominator is zero to avoid division by zero
                            $error = ($denominator === 0) ? $numerator : $numerator / $denominator;
                            if ($error >= 0.0000000001) {    // Smaller than 10E-10
                                $change = true;
                            }
                        } else {
                            if ($this->isBooleanType($field_type)) {
                                if ($this->_getBooleanValue($before_value) !== $this->_getBooleanValue($after_value)) {
                                    $change = true;
                                }
                            } else {
                                $change = true;
                            }
                        }
                        if ($change) {
                            $changed_values[$field] = array(
                                'field_name' => $field,
                                'data_type' => $field_type,
                                'before' => $before_value,
                                'after' => $after_value
                            );
                        }
                    }
                }
            }
        }

        return $changed_values;
    }

    /**
     * Uses the audit enabled fields array to find fields whose value has changed.
     * The before and after values are stored in the bean.
     * Uses $bean->fetched_row && $bean->fetched_rel_row to compare
     *
     * @param SugarBean $bean Sugarbean instance that was changed
     * @return array
     */
    public function getAuditDataChanges(SugarBean $bean): array
    {
        $audit_fields = $bean->getAuditEnabledFieldDefinitions();

        return $this->getDataChanges($bean, array_keys($audit_fields));
    }

    /**
     * Setup FT indexing
     * @abstract
     */
    public function full_text_indexing_setup(): void
    {
        // Most DBs have nothing to setup, so provide default empty function
    }

    /**
     * Quotes a string for storing in the database
     * @param string $string
     * @return string
     * @deprecated
     * Return value will be not surrounded by quotes
     *
     */
    public function escape_quote(string $string): string
    {
        return $this->quote($string);
    }

    /**
     * Quotes a string for storing in the database
     * @param string $string
     * @return string
     * @deprecated
     * Return value will be not surrounded by quotes
     *
     */
    public function quoteFormEmail(string $string): string
    {
        return $this->quote($string);
    }

    /**
     * Renames an index using fields definition
     *
     * @param array $old_definition
     * @param array $new_definition
     * @param string $table_name
     * @return array SQL statement
     */
    public function renameIndexDefs(array $old_definition, array $new_definition, string $table_name): array
    {
        return array(
            $this->addDropConstraint($table_name, $old_definition, true),
            $this->addDropConstraint($table_name, $new_definition),
            false
        );
    }

    /**
     * Check if type is boolean
     * @param string $type
     * @return bool
     */
    public function isBooleanType(string $type): bool
    {
        return 'bool' === $type;
    }

    /**
     * Get truth value for boolean type
     * Allows 'off' to mean false, along with all 'empty' values
     * @param mixed $val
     * @return bool
     */
    protected function _getBooleanValue(mixed $val): bool
    {
        //need to put the === sign here otherwise true == 'non empty string'
        if (empty($val) || $val === 'off') {
            return false;
        }

        return true;
    }

    /**
     * Check if type is a number
     * @param string $type
     * @return bool
     */
    public function isNumericType(string $type): bool
    {
        if (isset($this->type_class[$type]) && ($this->type_class[$type] === 'int' || $this->type_class[$type] === 'float')) {
            return true;
        }

        return false;
    }

    /**
     * Check if the value is empty value for this type
     * @param mixed $val Value
     * @param string $type Type (one of vardef types)
     * @return bool true if the value if empty
     */
    protected function _emptyValue(mixed $val, string $type): bool
    {
        if (empty($val)) {
            return true;
        }

        if ($this->emptyValue($type) === $val) {
            return true;
        }
        switch ($type) {
            case 'decimal':
            case 'decimal2':
            case 'int':
            case 'double':
            case 'float':
            case 'uint':
            case 'ulong':
            case 'long':
            case 'short':
            return (is_float($val) || is_integer($val));
            case 'date':
                if ($val === '0000-00-00') {
                    return true;
                }
                if ($val === 'NULL') {
                    return true;
                }

                return false;
        }

        return false;
    }

    /**
     * @abstract
     * Does this type represent text (i.e., non-varchar) value?
     * @param string $type
     * @return bool
     */
    public function isTextType(string $type): bool
    {
        return false;
    }

    /**
     * Check if this DB supports certain capability
     * See $this->capabilities for the list
     * @param string $cap
     * @return bool
     */
    public function supports(string $cap): bool
    {
        return !empty($this->capabilities[$cap]);
    }

    /**
     * Create ORDER BY clause for ENUM type field
     * @param string $order_by Field name
     * @param array $values Possible enum value
     * @param string $order_dir Order direction, ASC or DESC
     * @return string
     */
    public function orderByEnum(string $order_by, array $values, string $order_dir): string
    {
        $i = 0;
        $order_by_arr = array();
        foreach ($values as $key => $value) {
            if ($key === '') {
                $order_by_arr[] = "WHEN ($order_by='' OR $order_by IS NULL) THEN $i";
            } else {
                $order_by_arr[] = "WHEN $order_by=" . $this->quoted($key) . " THEN $i";
            }
            $i++;
        }

        return 'CASE ' . implode("\n", $order_by_arr) . " ELSE $i END $order_dir\n";
    }

    /**
     * Return representation of an empty value depending on type
     * The value is fully quoted, converted, etc.
     * @param string $type
     * @return mixed Empty value
     */
    public function emptyValue($type)
    {
        if (isset($this->type_class[$type])
            && ($this->type_class[$type] === 'bool' || $this->type_class[$type] === 'int' || $this->type_class[$type] === 'float')) {
            return 0;
        }

        return '\'\'';
    }

    /**
     * List of available collation settings
     * @abstract
     * @return string
     */
    public function getDefaultCollation(): ?string
    {
        return null;
    }

    /**
     * List of available collation settings
     * @abstract
     * @return array
     */
    public function getCollationList()
    {
        return null;
    }

    /**
     * Returns the number of columns in a table
     *
     * @param string $table_name
     * @return int
     */
    public function number_of_columns(string $table_name): int
    {
        $table = $this->getTableDescription($table_name);

        return count($table);
    }

    /**
     * Return limit query based on given query
     * @param string $sql
     * @param int $start
     * @param int $count
     * @param bool $dieOnError
     * @param string $msg
     * @return resource|bool query result
     * @see DBManager::limitQuery()
     */
    public function limitQuerySql(string $sql, int $start, int $count, bool $dieOnError = false, string $msg = '')
    {
        return $this->limitQuery($sql, $start, $count, $dieOnError, $msg, false);
    }

    /**
     * Return current time in format fit for insertion into DB (with quotes)
     * @return string
     */
    public function now(): string
    {
        return $this->convert($this->quoted(TimeDate::getInstance()->nowDb()), 'datetime');
    }

    /**
     * Check if connecting user has certain privilege
     * @param string $privilege
     * @return bool Privilege allowed?
     */
    public function checkPrivilege(string $privilege): bool
    {
        switch ($privilege) {
            case 'CREATE TABLE':
                $this->query('CREATE TABLE temp (id varchar(36))');
                break;
            case 'DROP TABLE':
                $sql = $this->dropTableNameSQL('temp');
                $this->query($sql);
                break;
            case 'INSERT':
                $this->query('INSERT INTO temp (id) VALUES (\'abcdef0123456789abcdef0123456789abcd\')');
                break;
            case 'UPDATE':
                $this->query('UPDATE temp SET id = \'100000000000000000000000000000000000\' WHERE id = \'abcdef0123456789abcdef0123456789abcd\'');
                break;
            case 'SELECT':
                return $this->getOne('SELECT id FROM temp WHERE id=\'100000000000000000000000000000000000\'', false);
            case 'DELETE':
                $this->query('DELETE FROM temp WHERE id = \'100000000000000000000000000000000000\'');
                break;
            case 'ADD COLUMN':
                $test = array('test' => array('name' => 'test', 'type' => 'varchar', 'len' => 50));
                $sql = $this->changeColumnSQL('temp', $test, 'add');
                $this->query($sql);
                break;
            case 'CHANGE COLUMN':
                $test = array('test' => array('name' => 'test', 'type' => 'varchar', 'len' => 100));
                $sql = $this->changeColumnSQL('temp', $test, 'modify');
                $this->query($sql);
                break;
            case 'DROP COLUMN':
                $test = array('test' => array('name' => 'test', 'type' => 'varchar', 'len' => 100));
                $sql = $this->changeColumnSQL('temp', $test, 'drop');
                $this->query($sql);
                break;
            default:
                return false;
        }
        if ($this->checkError('Checking privileges')) {
            return false;
        }

        return true;
    }

    /**
     * Check if the query is a select query
     * @param string $query
     * @return bool  Is query SELECT?
     */
    protected function isSelect(string $query): bool
    {
        $query = trim($query);
        $select_check = stripos($query, 'SELECT');
        //Checks to see if there is union select which is valid
        $select_check2 = stripos($query, '(SELECT');
        if ($select_check === 0 || $select_check2 === 0) {
            //Returning false means query is ok!
            return true;
        }

        return false;
    }

    /**
     * Parse fulltext search query with mysql syntax:
     *  terms quoted by ""
     *  + means the term must be included
     *  - means the term must be excluded
     *  * or % at the end means wildcard
     * @param string $query
     * @return bool|array of 3 elements - query terms, mandatory terms and excluded terms
     */
    public function parseFulltextQuery(string $query): bool|array
    {
        /* split on space or comma, double quotes with \ for escape */
        if (strpbrk($query, ' ,')) {
            // ("([^"]*?)"|[^" ,]+)((, )+)?
            // '/([^" ,]+|".*?[^\\\\]")(,|\s)\s*/'
            if (!preg_match_all('/("([^"]*?)"|[^"\s,]+)((,\s)+)?/', $query, $m)) {
                return false;
            }
            $query_terms = $m[1];
        } else {
            $query_terms = array($query);
        }
        $terms = $must_terms = $not_terms = array();
        foreach ($query_terms as $item) {
            if ($item[0] === '"') {
                $item = trim($item, '"');
            }
            if ($item[0] === '+') {
                if (strlen((string)$item) > 1) {
                    $must_terms[] = substr((string)$item, 1);
                }
                continue;
            }
            if ($item[0] === '-') {
                if (strlen((string)$item) > 1) {
                    $not_terms[] = substr((string)$item, 1);
                }
                continue;
            }
            $terms[] = $item;
        }

        return array($terms, $must_terms, $not_terms);
    }

    // Methods to check respective queries
    protected array $standardQueries = array(
        'ALTER TABLE' => 'verifyAlterTable',
        'DROP TABLE' => 'verifyDropTable',
        'CREATE TABLE' => 'verifyCreateTable',
        'INSERT INTO' => 'verifyInsertInto',
        'UPDATE' => 'verifyUpdate',
        'DELETE FROM' => 'verifyDeleteFrom',
    );


    /**
     * Extract table name from a query
     * @param string $query SQL query
     * @return string
     */
    protected function extractTableName(string $query): string
    {
        $query = preg_replace('/[^A-Za-z0-9_\s]/', '', $query);
        $query = trim(str_replace(array_keys($this->standardQueries), '', $query));

        $first_space = strpos($query, ' ');
        $end = ($first_space > 0) ? $first_space : strlen($query);

        return substr($query, 0, $end);
    }

    /**
     * Verify SQl statement using per-DB verification function
     * provided the function exists
     * @param string $query Query to verify
     * @param array $skipTables List of blacklisted tables that aren't checked
     * @return string
     */
    public function verifySQLStatement(string $query, array $skipTables): string
    {
        $query = trim($query);
        foreach ($this->standardQueries as $start => $check) {
            if (strncasecmp($start, $query, strlen((string)$start)) === 0) {
                if (is_callable(array($this, $check))) {
                    $table = $this->extractTableName($query);
                    if (!in_array($table, $skipTables, true)) {
                        return $this->$check($table, $query);
                    }
                    $this->log->debug("Skipping table $table as blacklisted");
                } else {
                    $this->log->debug("No verification for $start on {$this->dbType}");
                }
                break;
            }
        }

        return '';
    }

    /**
     * Tests an CREATE TABLE query
     * @param string $table The table name to get DDL
     * @param string $query The query to test.
     * @return string Non-empty if error found
     */
    protected function verifyCreateTable(string $table, string $query): string
    {
        $this->log->debug('verifying CREATE statement...');

        // rewrite DDL with _temp name
        $this->log->debug('testing query: [' . $query . ']');
        $temp_name = $table . '__uw_temp';
        $temp_table_query = str_replace("CREATE TABLE {$table}", "CREATE TABLE $temp_name", $query);

        if (!str_contains($temp_table_query, '__uw_temp')) {
            return 'Could not use a temp table to test query!';
        }

        $this->query($temp_table_query, false, sprintf('Preflight Failed for: %s', $query));

        $error = $this->lastError(); // empty on no-errors
        if (!empty($error)) {
            return $error;
        }

        // check if table exists
        $this->log->debug('testing for table: ' . $table);
        if (!$this->tableExists($temp_name)) {
            return 'Failed to create temp table!';
        }

        $this->dropTableName($temp_name);

        return '';
    }

    /**
     * Execute multiple queries one after another
     * @param array $sqls Queries
     * @param bool $dieOnError Die on error, passed to query()
     * @param string $msg Error message, passed to query()
     * @param bool $suppress Supress errors, passed to query()
     * @return resource|bool result set or success/failure bool
     */
    public function queryArray(array $sqls, bool $dieOnError = false, string $msg = '', bool $suppress = false)
    {
        $last = true;
        foreach ($sqls as $sql) {
            if (!($last = $this->query($sql, $dieOnError, $msg, $suppress))) {
                break;
            }
        }

        return $last;
    }

    /**
     * Fetches the next row in the query result into an associative array
     *
     * @param resource $result
     * @param bool $encode Need to HTML-encode the result?
     * @return array    returns false if there are no more rows available to fetch
     */
    public function fetchByAssoc($result, bool $encode = true): bool|array
    {
        if (empty($result)) {
            return false;
        }

        if ($encode && func_num_args() === 3) {
            // old API: $result, $rowNum, $encode
            $GLOBALS['log']->deprecated('Using row number in fetchByAssoc is not portable and no longer supported. Please fix your code.');
            $encode = func_get_arg(2);
        }
        $row = $this->fetchRow($result);
        if (!empty($row) && $encode && $this->encode) {
            return array_map('to_html', $row);
        }
        return $row;
    }

    /**
     * Get DB driver name used for install/upgrade scripts
     * @return string
     */
    public function getScriptName(): string
    {
        // Usually the same name as dbType
        return $this->dbType;
    }

    /**
     * Set database options
     * Options are usually db-dependant and derive from $config['dbconfigoption']
     * @param array $options
     * @return DBManager
     */
    public function setOptions(array $options): DBManager
    {
        $this->options = $options;

        return $this;
    }

    /**
     * Get DB options
     * @return array
     */
    public function getOptions(): array
    {
        return $this->options;
    }

    /**
     * Get DB option by name
     * @param string $option Option name
     * @return mixed Option value or null if doesn't exist
     */
    public function getOption(string $option): mixed
    {
        if (isset($this->options[$option])) {
            return $this->options[$option];
        }

        return null;
    }

    /**
     * Commits pending changes to the database when the driver is setup to support transactions.
     * Note that the default implementation is applicable for transaction-less or auto commit scenarios.
     * @abstract
     * @return bool true if commit succeeded, false if it failed
     */
    public function commit(): bool
    {
        $this->log->info('DBManager.commit() stub');

        return true;
    }

    /**
     * Rollsback pending changes to the database when the driver is setup to support transactions.
     * Note that the default implementation is applicable for transaction-less or auto commit scenarios.
     * Since rollbacks cannot be done, this implementation always returns false.
     * @abstract
     * @return bool true if rollback succeeded, false if it failed
     */
    public function rollback(): bool
    {
        $this->log->info('DBManager.rollback() stub');

        return false;
    }

    /**
     * Check if this DB name is valid
     *
     * @param string $name
     * @return bool
     */
    public function isDatabaseNameValid($name): bool
    {
        // Generic case - no slashes, no dots
        return preg_match('#[/.\\\\]#', $name) === 0;
    }

    /**
     * Check special requirements for DB installation.
     * @abstract
     * If everything is OK, return true.
     * If something's wrong, return array of error code and parameters
     * @return mixed
     */
    public function canInstall(): bool
    {
        return true;
    }

    /**
     * @abstract
     * Code run on new database before installing
     */
    public function preInstall(): void
    {
    }

    /**
     * @abstract
     * Code run on new database after installing
     */
    public function postInstall(): void
    {
    }

    /**
     * Disable keys on the table
     * @abstract
     * @param string $tableName
     */
    public function disableKeys(string $tableName): void
    {
    }

    /**
     * Re-enable keys on the table
     * @abstract
     * @param string $tableName
     */
    public function enableKeys(string $tableName): void
    {
    }

    /**
     * Quote string in DB-specific manner
     * @param string $string
     * @return string
     */
    abstract public function quote(string $string): string;

    /**
     * @param string $string
     * @return string
     */
    abstract public function quoteIdentifier(string $string): string;

    /**
     * Use when you need to convert a database string to a different value; this function does it in a
     * database-backend aware way
     * Supported conversions:
     *      today        return current date
     *      left        Take substring from the left
     *      date_format    Format date as string, supports %Y-%m-%d, %Y-%m, %Y
     *      time_format Format time as string
     *      date        Convert date string to datetime value
     *      time        Convert time string to datetime value
     *      datetime    Convert datetime string to datetime value
     *      ifnull        If var is null, use default value
     *      concat        Concatenate strings
     *      quarter        Quarter number of the date
     *      length        Length of string
     *      month        Month number of the date
     *      add_date    Add specified interval to a date
     *      add_time    Add time interval to a date
     *      text2char   Convert text field to varchar
     *
     * @param string $string database string to convert
     * @param string $type type of conversion to do
     * @param array $additional_parameters optional, additional parameters to pass to the db function
     * @return string
     */
    abstract public function convert(string $string, string $type, array $additional_parameters = array()): string;

    /**
     * Converts from Database data to app data
     *
     * Supported types
     * - date
     * - time
     * - datetime
     * - datetimecombo
     * - timestamp
     *
     * @param string $string database string to convert
     * @param string $type type of conversion to do
     * @return string
     */
    abstract public function fromConvert(string $string, string $type): string;

    /**
     * Parses and runs queries
     *
     * @param string $sql SQL Statement to execute
     * @param bool $dieOnError True if we want to call die if the query returns errors
     * @param string $msg Message to log if error occurs
     * @param bool $suppress Flag to suppress all error output unless in debug logging mode.
     * @param bool $keepResult Keep query result in the object?
     * @return resource|bool result set or success/failure bool
     */
    abstract public function query(string|array $sql, bool $dieOnError = false, string $msg = '', bool $suppress = false, bool $keepResult = false);

    /**
     * Runs a limit query: one where we specify where to start getting records and how many to get
     *
     * @param string $sql SELECT query
     * @param int $start Starting row
     * @param int $count How many rows
     * @param boolean $dieOnError True if we want to call die if the query returns errors
     * @param string $msg Message to log if error occurs
     * @param bool $execute Execute or return SQL?
     * @return resource query result
     */
    abstract public function limitQuery(string $sql, int $start, int $count, bool $dieOnError = false, string $msg = '', bool $execute = true);

    /**
     * Free Database result
     * @param resource $dbResult
     */
    abstract protected function freeDbResult(mixed $dbResult): void;

    /**
     * Rename column in the DB
     * @param string $table_name
     * @param string $column
     * @param string $new_name
     */
    abstract public function renameColumnSQL(string $table_name, string $column, string $new_name): void;

    /**
     * Returns definitions of all indies for passed table.
     *
     * return will is a multi-dimensional array that
     * categorizes the index definition by types, unique, primary and index.
     * <code>
     * <?php
     * array(                                                              O
     *       'index1'=> array (
     *           'name'   => 'index1',
     *           'type'   => 'primary',
     *           'fields' => array('field1','field2')
     *           )
     *       )
     * ?>
     * </code>
     * This format is similar to how indicies are defined in vardef file.
     *
     * @param string $table_name
     * @return array
     */
    abstract public function get_indices(string $table_name): array;

    /**
     * Returns definitions of all indies for passed table.
     *
     * return will is a multi-dimensional array that
     * categorizes the index definition by types, unique, primary and index.
     * <code>
     * <?php
     * array(
     *       'field1'=> array (
     *           'name'   => 'field1',
     *           'type'   => 'varchar',
     *           'len' => '200'
     *           )
     *       )
     * ?>
     * </code>
     * This format is similar to how indicies are defined in vardef file.
     *
     * @param string $table_name
     * @return array
     */
    abstract public function get_columns(string $table_name): array;

    /**
     * Generates alter constraint statement given a table name and vardef definition.
     *
     * Supports both adding and droping a constraint.
     *
     * @param string $table tablename
     * @param array $definition field definition
     * @param bool $drop true if we are dropping the constraint, false if we are adding it
     * @return string SQL statement
     */
    abstract public function addDropConstraint(string $table, array $definition, bool $drop = false): string;

    /**
     * Returns the description of fields based on the result
     *
     * @param resource $result
     * @param boolean $make_lower_case
     * @return array field array
     */
    abstract public function getFieldsArray(mixed $result, bool $make_lower_case = false): array;

    /**
     * Returns an array of tables for this database
     *
     * @return    array|false    an array of with table names, false if no tables found
     */
    abstract public function getTablesArray(): array;

    /**
     * Return's the version of the database
     *
     * @return string
     */
    abstract public function version(): string;

    /**
     * Checks if a table with the name $tableName exists
     * and returns true if it does or false otherwise
     *
     * @param string $table_name
     * @return bool
     */
    abstract public function tableExists(string $table_name): bool;

    /**
     * Fetches the next row in the query result into an associative array
     *
     * @param resource $result
     * @return array    returns false if there are no more rows available to fetch
     */
    abstract public function fetchRow(mixed $result): array;

    /**
     * Connects to the database backend
     *
     * Takes in the database settings and opens a database connection based on those
     * will open either a persistent or non-persistent connection.
     * If a persistent connection is desired but not available it will defualt to non-persistent
     *
     * configOptions must include
     * db_host_name - server ip
     * db_user_name - database user name
     * db_password - database password
     *
     * @param mixed[]|null $configOptions
     * @param boolean $dieOnError
     */
    abstract public function connect(array $configOptions = null, bool $dieOnError = false);

    /**
     * Generates sql for create table statement for a bean.
     *
     * @param string $table_name
     * @param array $field_definitions
     * @param array $indices
     * @return string SQL Create Table statement
     */
    abstract public function createTableSQLParams(string $table_name, array $field_definitions, array $indices): string;

    /**
     * Generates the SQL for changing columns
     *
     * @param string $table_name
     * @param array $field_definitions
     * @param string $action
     * @param bool $ignoreRequired Optional, true if we should ignor this being a required field
     * @return string|array
     */
    abstract protected function changeColumnSQL(string $table_name, array $field_definitions, string $action, bool $ignoreRequired = false): array|string;

    /**
     * Disconnects from the database
     *
     * Also handles any cleanup needed
     */
    abstract public function disconnect();

    /**
     * Get last database error
     * This function should return last error as reported by DB driver
     * and should return false if no error condition happened
     * @return string|false Error message or false if no error happened
     */
    abstract public function lastDbError(): bool|string;

    /**
     * Check if this query is valid
     * Validates only SELECT queries
     * @param string $query
     * @return bool
     */
    abstract public function validateQuery(string $query): bool;

    /**
     * Check if this driver can be used
     * @return bool
     */
    abstract public function valid(): bool;

    /**
     * Check if certain database exists
     * @param string $dbname
     */
    abstract public function dbExists(string $dbname): bool;

    /**
     * Get tables like expression
     * @param string $like Expression describing tables
     * @return array
     */
    abstract public function tablesLike(string $like): array;

    /**
     * Create a database
     * @param string $dbname
     */
    abstract public function createDatabase(string $dbname);

    /**
     * Drop a database
     * @param string $dbname
     */
    abstract public function dropDatabase(string $dbname);

    /**
     * Get database configuration information (DB-dependent)
     * @return array|null
     */
    abstract public function getDbInfo(): ?array;

    /**
     * Check if certain DB user exists
     * @param string $username
     */
    abstract public function userExists(string $username);

    /**
     * Create DB user
     * @param string $database_name
     * @param string $host_name
     * @param string $user
     * @param string $password
     */
    abstract public function createDbUser(string $database_name, string $host_name, string $user, string $password);

    /**
     * Check if the database supports fulltext indexing
     * Note that database driver can be capable of supporting FT (see supports('fulltext))
     * but particular instance can still have it disabled
     * @return bool
     */
    abstract public function isFullTextIndexingInstalled(): bool;

    /**
     * Generate fulltext query from set of terms
     * @param string $field Field to search against
     * @param array $terms Search terms that may be or not be in the result
     * @param array $must_terms Search terms that have to be in the result
     * @param array $exclude_terms Search terms that have to be not in the result
     */
    abstract public function getFulltextQuery(string $field, array $terms, array $must_terms = array(), array $exclude_terms = array());

    /**
     * Get install configuration for this DB
     * @return array
     */
    abstract public function installConfig(): void;

    /**
     * Returns a DB specific FROM clause which can be used to select against functions.
     * Note that depending on the database that this may also be an empty string.
     * @abstract
     * @return string
     */
    abstract public function getFromDummyTable(): string;

    /**
     * Returns a DB specific piece of SQL which will generate GUID (UUID)
     * This string can be used in dynamic SQL to do multiple inserts with a single query.
     * I.e. generate a unique Sugar id in a sub select of an insert statement.
     * @abstract
     * @return string
     */
    abstract public function getGuidSQL();

    /**
     * Returns a string without line breaks.
     * @param string $sql A SQL statement
     * @return string
     */
    public function removeLineBreaks(string $sql): string
    {
        return trim(str_replace(array("\r", "\n"), ' ', $sql));
    }

    /**
     * @param $fields
     * @return string|string[]|null
     */
    protected function removeIndexLimit(array|string|null $fields): array|string|null
    {
        return $fields;
    }
}
