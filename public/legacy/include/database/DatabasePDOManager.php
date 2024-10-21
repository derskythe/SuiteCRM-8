<?php

namespace SuiteCRM\database;

use AllowDynamicProperties;
use LoggerManager;
use PDO;
use Psr\Log\LogLevel;

/**
 * DatabasePDOManager
 */
#[AllowDynamicProperties]
class DatabasePDOManager
{
    /**
     * @var DatabasePDOManager|null
     */
    private static ?DatabasePDOManager $instance = null;
    /**
     * @var LoggerManager|null
     */
    private static ?LoggerManager $log = null;
    /**
     * @var array|null
     */
    private static ?array $dbConfig = null;
    /**
     * @var bool
     */
    private static bool $init = false;
    /**
     * @var PDO|null
     */
    private ?PDO $pdo = null;

    /**
     * Constructor
     */
    protected function __construct()
    {
    }

    /**
     * @return DatabasePDOManager
     */
    public static function getInstance(): DatabasePDOManager
    {
        if (null === self::$instance) {
            self::$instance = new DatabasePDOManager();
        }

        return self::$instance;
    }

    /**
     * @param array $dbConfig
     * @param LoggerManager $log
     * @return void
     */
    public static function initDatabasePDO(array $dbConfig, LoggerManager $log): void
    {
        self::$dbConfig = $dbConfig;
        self::$log = $log;
        self::$init = true;
    }

    /**
     * @return bool
     */
    public static function isInit(): bool
    {
        return self::$init;
    }

    /**
     * @param string $level
     * @param string $message
     * @param array $context
     * @return void
     */
    private function log(string $level, string $message, array $context = []): void
    {
        $msg = sprintf('%s%s%s', $message, PHP_EOL, print_r($context, true));
        if (null === self::$log) {
            switch ($level) {
                case LogLevel::NOTICE:
                case LogLevel::INFO:
                case LogLevel::DEBUG:
                    trigger_error($msg);
                    break;
                case LogLevel::ALERT:
                case LogLevel::WARNING:
                    trigger_error($msg, E_USER_WARNING);
                    break;
                case LogLevel::CRITICAL:
                case LogLevel::ERROR:
                case LogLevel::EMERGENCY:
                    trigger_error($msg, E_USER_ERROR);
            }
        } else {
            switch ($level) {
                case LogLevel::DEBUG:
                    self::$log->debug($msg);
                    break;
                case LogLevel::ALERT:
                case LogLevel::WARNING:
                    self::$log->warn($msg);
                    break;
                case LogLevel::CRITICAL:
                case LogLevel::EMERGENCY:
                    self::$log->fatal($msg);
                    break;
                case LogLevel::NOTICE:
                case LogLevel::INFO:
                    self::$log->info($msg);
                    break;
                case LogLevel::ERROR:
                    self::$log->error($msg);
                    break;
                default:
                    self::$log->error('Unexpected value' . $level);
                    self::$log->error($msg);
            }
        }
    }

    /**
     * @param bool $isThrowException
     * @return void
     * @throws \Exception
     */
    public function connect(bool $isThrowException = true): void
    {
        if (!self::$init) {
            $this->log(LogLevel::ERROR, 'Database PDO Manager not initialized');
            return;
        }

        $dsn = '';
        try {
            // Connection already exists and alive
            if ($this->pdo !== null && $this->executeNonQuery('SELECT 1', [], false) >= 0) {
                return;
            }

            $dsn = sprintf('%s:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                self::$dbConfig['db_type'],
                self::$dbConfig['db_host'],
                self::$dbConfig['db_port'],
                self::$dbConfig['db_name']);

            $this->log(LogLevel::INFO, 'Connecting to database', [$dsn]);
            $options = self::$dbConfig['db_options'];
            if (!isset($options[PDO::MYSQL_ATTR_INIT_COMMAND])) {
                $options[] = [\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4'"];
            }
            if (!isset($options[\PDO::ATTR_DEFAULT_FETCH_MODE])) {
                $options[] = [\PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC];
            }
            if (!isset($options[\PDO::ATTR_ERRMODE])) {
                $options[] = [\PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION];
            }
            if (!isset($options[\PDO::MYSQL_ATTR_IGNORE_SPACE])) {
                $options[] = [\PDO::MYSQL_ATTR_IGNORE_SPACE => true];
            }
            if (!isset($options[\PDO::ATTR_PERSISTENT])) {
                $options[] = [\PDO::ATTR_PERSISTENT => true];
            }
            if (!isset($options[\PDO::ATTR_AUTOCOMMIT])) {
                $options[] = [\PDO::ATTR_AUTOCOMMIT => true];
            }
            if (!isset($options[\PDO::ATTR_TIMEOUT])) {
                $options[] = [\PDO::ATTR_TIMEOUT => 60];
            }
            if (!isset($options[\PDO::ATTR_CASE])) {
                $options[] = [\PDO::CASE_UPPER => true];
            }
            if (!isset($options[\PDO::NULL_TO_STRING])) {
                $options[] = [\PDO::NULL_TO_STRING => true];
            }
            if (!isset($options[\PDO::MYSQL_ATTR_INIT_COMMAND])) {
                $options[] = [\PDO::MYSQL_ATTR_INIT_COMMAND => 'SELECT 1'];
            }

            $this->pdo = new PDO(
                $dsn,
                self::$dbConfig['db_user_name'],
                self::$dbConfig['db_password'],
                self::$dbConfig['db_options']
            );
        } catch (\PDOException $exp) {
            $msg = $exp->getMessage() . PHP_EOL . 'DSN:' . $dsn . PHP_EOL . $exp->getTraceAsString();
            self::$log->fatal($msg);

            if ($isThrowException) {
                throw $exp;
            }
            //trigger_error($msg, E_USER_ERROR);
        } catch (\Exception $exp) {
            $msg = $exp->getMessage() . PHP_EOL . 'DSN:' . $dsn . PHP_EOL . $exp->getTraceAsString();
            self::$log->fatal($msg);

            if ($isThrowException) {
                throw $exp;
            }
        }
    }

    /**
     * @param string $sql
     * @param array $params
     * @param bool $isThrowException
     * @return int
     * @throws \Exception
     */
    public function executeNonQuery(string $sql, array $params, bool $isThrowException = true): int
    {
        if (!self::$init) {
            $this->log(LogLevel::ERROR, 'Database PDO Manager not initialized. executeNonQuery', [$sql]);

            return -1;
        }
        if (null === $this->pdo) {
            $this->connect();
        }
        $this->log(LogLevel::DEBUG, 'executeNonQuery', [$sql]);

        try {
            $statement = $this->pdo->prepare($sql);

            if ($statement === false) {
                $this->log(LogLevel::ERROR, 'Failed to executeNonQuery',
                    [
                        'SQL' => $sql,
                        'ERROR_CODE' => $this->pdo->errorCode(),
                        'ERROR' => $this->pdo->errorInfo()
                    ]);

                return -1;
            }
            if (!empty($params)) {
                foreach ($params as $key => $value) {
                    if ($statement->bindParam($key, $value, self::getDatabaseFieldType($value)) === false) {
                        $this->log(LogLevel::ERROR, 'Failed to executeNonQuery bind param',
                            [
                                'SQL' => $sql,
                                'KEY' => $key,
                                'VALUE' => $value,
                                'ERROR_CODE' => $statement->errorCode(),
                                'ERROR' => $statement->errorInfo()
                            ]);

                        return -1;
                    }
                }
            }

            if ($statement->execute() === false) {
                $this->log(LogLevel::ERROR, 'Failed to executeNonQuery',
                    [
                        'SQL' => $sql,
                        'ERROR_CODE' => $statement->errorCode(),
                        'ERROR' => $statement->errorInfo()
                    ]);
                return -1;
            }

            return $statement->rowCount();
        } catch (\Exception $exp) {
            $this->log(LogLevel::ERROR, 'Failed to executeNonQuery', [
                    'MESSAGE' => $exp->getMessage(),
                    'TRACE' => $exp->getTraceAsString(),
                    'SQL' => $sql,
                    'FILE' => $exp->getFile(),
                    'LINE' => $exp->getLine()
                ]
            );
            if ($isThrowException) {
                throw $exp;
            }
        }

        return -1;
    }

    /**
     * @param string $sql
     * @param array $params
     * @param bool $isThrowException
     * @return int
     * @throws \Exception
     */
    public function executeInsertQuery(string $sql, array $params, bool $isThrowException = true): int
    {
        if (!self::$init) {
            $this->log(LogLevel::ERROR, 'Database PDO Manager not initialized. executeInsertQuery', [$sql]);

            return -1;
        }

        if (null === $this->pdo) {
            $this->connect();
        }
        $this->log(LogLevel::DEBUG, 'executeInsertQuery', [$sql]);

        try {
            $statement = $this->pdo->prepare($sql);

            if ($statement === false) {
                $this->log(LogLevel::ERROR, 'Failed to executeInsertQuery',
                    [
                        'SQL' => $sql,
                        'ERROR_CODE' => $this->pdo->errorCode(),
                        'ERROR' => $this->pdo->errorInfo()
                    ]);

                return -1;
            }
            if (!empty($params)) {
                foreach ($params as $key => $value) {
                    if ($statement->bindParam($key, $value, self::getDatabaseFieldType($value)) === false) {
                        $this->log(LogLevel::ERROR, 'Failed to executeInsertQuery bind param',
                            [
                                'SQL' => $sql,
                                'KEY' => $key,
                                'VALUE' => $value,
                                'ERROR_CODE' => $statement->errorCode(),
                                'ERROR' => $statement->errorInfo()
                            ]);

                        return -1;
                    }
                }
            }

            if ($statement->execute() === false) {
                $this->log(LogLevel::ERROR, 'Failed to executeInsertQuery',
                    [
                        'SQL' => $sql,
                        'ERROR_CODE' => $statement->errorCode(),
                        'ERROR' => $statement->errorInfo()
                    ]);
                return -1;
            }

            return $this->pdo->lastInsertId();
        } catch (\Exception $exp) {
            $this->log(LogLevel::ERROR, 'Failed to executeInsertQuery', [
                    'MESSAGE' => $exp->getMessage(),
                    'TRACE' => $exp->getTraceAsString(),
                    'SQL' => $sql,
                    'FILE' => $exp->getFile(),
                    'LINE' => $exp->getLine()
                ]
            );
            if ($isThrowException) {
                throw $exp;
            }
        }

        return -1;
    }

    /**
     * @param string $sql
     * @param array $params
     * @param bool $isThrowException
     * @return \PDOStatement|null
     * @throws \Exception
     */
    public
    function executeQueryResult(string $sql, array $params = [], bool $isThrowException = true): \PDOStatement|null
    {
        if (!self::$init) {
            $this->log(LogLevel::ERROR, 'Database PDO Manager not initialized. executeInsertQuery', [$sql]);

            return null;
        }

        if (null === $this->pdo) {
            $this->connect();
        }
        $this->log(LogLevel::DEBUG, 'Preparing executeQueryResult', [$sql]);

        try {
            $statement = $this->pdo->prepare($sql);

            if ($statement === false) {
                $this->log(LogLevel::ERROR, 'Failed to executeQueryResult',
                    [
                        'SQL' => $sql,
                        'ERROR_CODE' => $this->pdo->errorCode(),
                        'ERROR' => $this->pdo->errorInfo()
                    ]);

                return null;
            }
            if (!empty($params) && count($params) > 0) {
                foreach ($params as $key => $value) {
                    if ($statement->bindParam($key, $value, self::getDatabaseFieldType($value)) === false) {
                        $this->log(LogLevel::ERROR, 'Failed to executeQueryResult bind param',
                            [
                                'SQL' => $sql,
                                'KEY' => $key,
                                'VALUE' => $value,
                                'ERROR_CODE' => $statement->errorCode(),
                                'ERROR' => $statement->errorInfo()
                            ]);

                        return null;
                    }
                }
            }

            if ($statement->execute() === false) {
                $this->log(LogLevel::ERROR, 'Failed to executeQueryResult',
                    [
                        'SQL' => $sql,
                        'ERROR_CODE' => $statement->errorCode(),
                        'ERROR' => $statement->errorInfo()
                    ]);
                return null;
            }

            return $statement;
        } catch (\Exception $exp) {
            $this->log(LogLevel::ERROR, 'Failed to prepare statement', [
                    'MESSAGE' => $exp->getMessage(),
                    'TRACE' => $exp->getTraceAsString(),
                    'SQL' => $sql,
                    'FILE' => $exp->getFile(),
                    'LINE' => $exp->getLine()
                ]
            );
            if ($isThrowException) {
                throw $exp;
            }
        }

        return null;
    }

    /**
     * @param \PDOStatement|null $statement
     * @param bool $isThrowException
     * @return array
     * @throws \Exception
     */
    public
    function fetchAssoc(\PDOStatement|null $statement, bool $isThrowException = true): array
    {
        if (!self::isInit()) {
            $this->log(LogLevel::ERROR, 'Database PDO Manager not initialized. fetchAssoc');
            return [];
        }
        if($statement === null){
            $this->log(LogLevel::ERROR, 'Statement is null! Nothing to fetch. fetchAssoc');
            return [];
        }
        try {
            return $statement->fetch(\PDO::FETCH_ASSOC);
        } catch (\Exception $exp) {
            $this->log(LogLevel::ERROR, 'Failed to prepare statement', [
                    'MESSAGE' => $exp->getMessage(),
                    'TRACE' => $exp->getTraceAsString(),
                    'SQL' => $sql,
                    'FILE' => $exp->getFile(),
                    'LINE' => $exp->getLine()
                ]
            );
            if($isThrowException)
            {
                throw $exp;
            }
        }

        return [];
    }

    /**
     * @param mixed $value
     * @return int
     */
    private
    static function getDatabaseFieldType(mixed $value): int
    {
        if (is_bool($value)) {
            return PDO::PARAM_BOOL;
        }
        if (is_int($value)) {
            return PDO::PARAM_INT;
        }
        if (is_float($value) || is_nan($value) || is_infinite($value)) {
            return PDO::PARAM_STR;
        }
        if (is_null($value)) {
            return PDO::PARAM_STR;
        }
        if (is_string($value)) {
            return PDO::PARAM_STR_NATL;
        }

        return PDO::PARAM_NULL;
    }

    /**
     * @return mixed
     */
    private function __clone()
    {
        $this->log(LogLevel::ERROR, 'Cannot clone singleton');
        throw new \PDOException('Cannot clone singleton');
    }

    /**
     * @return mixed
     */
    public function __wakeup()
    {
        $this->log(LogLevel::ERROR, 'Cannot unserialize singleton');
        throw new \PDOException('Cannot unserialize singleton');
    }

    /**
     * @return array
     */
    public function __serialize(): array
    {
        $this->log(LogLevel::ERROR, 'Cannot serialize singleton');
        throw new \PDOException('Cannot serialize singleton');    }

    /**
     *
     */
    public function __destruct()
    {
        if(isset($this->pdo))
        {
            $this->pdo = null;
        }
    }
}
