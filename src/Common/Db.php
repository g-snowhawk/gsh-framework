<?php
/**
 * This file is part of G.Snowhawk Framework.
 *
 * Copyright (c)2016 PlusFive (https://www.plus-5.com)
 *
 * This software is released under the MIT License.
 * https://www.plus-5.com/licenses/mit-license
 */

namespace Gsnowhawk\Common;

use PDO;
use PDOException;

/**
 * Database connection class.
 *
 * @license  https://www.plus-5.com/licenses/mit-license  MIT License
 * @author   Taka Goto <www.plus-5.com>
 */
class Db
{
    public const SQL_EOL = "\r\n";
    public const MAX_ALLOWED_PACKET = 1048576; // 1MB

    /**
     * Database Type.
     *
     * @var string
     */
    private $driver;

    /**
     * Database host.
     *
     * @var string
     */
    private $host;

    /**
     * server port.
     *
     * @var string
     */
    private $port;

    /**
     * Database name.
     *
     * @var string
     */
    private $source;

    /**
     * Database Handler.
     *
     * @var PDO
     */
    private $handler;

    /**
     * PDOStatement Object.
     *
     * @var PDOStatement
     */
    private $statement;

    /**
     * SQL string.
     *
     * @vat string
     */
    private $sql;

    /**
     * Error Code.
     *
     * @var int|string
     */
    private $error_code;

    /**
     * Error Message.
     *
     * @var string
     */
    private $error_message;

    /**
     * Database.
     *
     * @var string
     */
    private $dsn;

    /**
     * Database user name.
     *
     * @var string
     */
    private $user;

    /**
     * Database access password.
     *
     * @var string
     */
    private $password;

    /**
     * Database encoding.
     *
     * @var string
     */
    private $encoding;

    /**
     * excute counter.
     *
     * @var int
     */
    private $ecount;

    /*
     * PDO Attributes
     *
     * @var array
     */
    private $options = [];

    /**
     * Error Mode
     *
     * @var
     */
    private $error_mode = null;

    /**
     * Object Constructor.
     *
     * @param string $driver   Database driver
     * @param string $host     Database server host name or IP address
     * @param string $source   Data source
     * @param string $user     Database user name
     * @param string $password Database password
     * @param string $port     Database server port
     * @param string $enc      Database encoding
     */
    public function __construct($driver, $host, $source, $user, $password, $port = 3306, $enc = '')
    {
        $this->driver = $driver;
        $this->host = $host;
        $this->source = $source;
        $this->user = $user;
        $this->password = $password;
        $this->port = $port;
        $this->encoding = $enc;
        if ($this->driver != 'sqlite' && $this->driver != 'sqlite2') {
            $this->dsn = "$driver:host=$host;port=$port;dbname=$source";
            if ($this->driver === 'mysql') {
                $this->options[PDO::MYSQL_ATTR_LOCAL_INFILE] = true;

                // SSL Options
                if (defined('MYSQL_SSL_CA')) {
                    $this->options[PDO::MYSQL_ATTR_SSL_CA] = MYSQL_SSL_CA;
                }
                if (defined('MYSQL_SSL_CERT')) {
                    $this->options[PDO::MYSQL_ATTR_SSL_CERT] = MYSQL_SSL_CERT;
                }
                if (defined('MYSQL_SSL_KEY')) {
                    $this->options[PDO::MYSQL_ATTR_SSL_KEY] = MYSQL_SSL_KEY;
                }
                if (defined('MYSQL_SSL_VERIFY_SERVER_CERT')) {
                    $this->options[PDO::MYSQL_ATTR_SSL_VERIFY_SERVER_CERT]
                        = MYSQL_SSL_VERIFY_SERVER_CERT;
                }

                if (strpos($host, 'unix_socket:') === 0) {
                    $socket = str_replace('unix_socket:', '', $host);
                    $this->dsn = "$driver:unix_socket=$socket;dbname=$source";
                }
            }
            if ($enc !== '') {
                if (file_exists($enc)) {
                    $this->options[PDO::MYSQL_ATTR_READ_DEFAULT_FILE] = $enc;
                    $content = str_replace('#', ';', file_get_contents($enc));
                    $init = parse_ini_string($content, true);
                    if (isset($init['client']['default-character-set'])) {
                        $this->encoding = $init['client']['default-character-set'];
                    }
                } else {
                    $this->dsn .= ";charset=$enc";
                }
            }
            $this->options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
        } else {
            if (!file_exists($host)) {
                mkdir($host, 0777, true);
            }
            $this->dsn = "$driver:$host/$source";
            if (!empty($port)) {
                $this->dsn .= ".$port";
            }
        }
    }

    /**
     * Clone this class.
     */
    public function __clone()
    {
    }

    /**
     * Getter method.
     *
     * @param string $name
     *
     * @return mixed
     */
    public function __get($name)
    {
        $key = '_'.$name;
        if (true === property_exists($this, $key)) {
            switch ($key) {
                case '_driver':
                    return $this->$key;
            }
        }

        return;
    }

    /**
     * Create database.
     *
     * @param string $db_name
     *
     * @return bool
     */
    public function create($db_name = null)
    {
        try {
            if (empty($db_name)) {
                $db_name = $this->source;
            }
            $dsn = "{$this->driver}:host={$this->host};port={$this->port}";
            $this->handler = new PDO($dsn, $this->user, $this->password);
            $this->handler->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            $sql = "CREATE DATABASE {$db_name}";
            if (!empty($this->encoding)) {
                if ($this->driver === 'mysql') {
                    $sql .= " DEFAULT CHARACTER SET {$this->encoding}";
                } elseif ($this->driver === 'pgsql') {
                    $sql .= " ENCODING '{$this->encoding}'";
                }
            }
            $this->handler->query($sql);
        } catch (PDOException $e) {
            $this->error_code = $e->getCode();
            $this->error_message = $e->getMessage();

            return self::exception($this->error_message, $this->error_code, $e);
        }

        return true;
    }

    /**
     * set driver options.
     *
     * @param array $options
     */
    public function addOptions($options)
    {
        foreach ($options as $key => $value) {
            $this->options[$key] = $value;
        }
    }

    /**
     * Open database connection.
     *
     * @param int $timeout
     * @param array $optional_modes
     *
     * @return bool
     */
    public function open($timeout = null, array $optional_modes = [])
    {
        try {
            if (!is_null($timeout)) {
                $this->options[PDO::ATTR_TIMEOUT] = $timeout;
            }
            $this->handler = new PDO($this->dsn, $this->user, $this->password, $this->options);
        } catch (PDOException $e) {
            $this->error_code = $e->getCode();
            $this->error_message = $e->getMessage();

            return self::exception($this->error_message, $this->error_code, $e);
        }

        if ($this->driver === 'mysql') {
            $this->addSQLMode($optional_modes);
            $this->removeSQLMode(['ANSI_QUOTES']);
        }

        return true;
    }

    /**
     * Close database connection.
     *
     * @return bool
     */
    public function close()
    {
        $this->handler = null;
    }

    public function getHandler()
    {
        return new PDO($this->dsn, $this->user, $this->password, $this->options);
    }

    public function addSQLMode(array $user_mode)
    {
        $this->query('SELECT @@SESSION.sql_mode');
        $default_mode = explode(',', $this->fetchColumn());
        $modes = array_merge($default_mode, $user_mode);
        $ret = $this->exec(
            'SET SESSION sql_mode=?',
            [implode(',', array_unique($modes))]
        );
    }

    public function removeSQLMode(array $user_mode)
    {
        $this->query('SELECT @@SESSION.sql_mode');
        $default_mode = explode(',', $this->fetchColumn());
        $modes = array_diff($default_mode, $user_mode);
        $this->exec(
            'SET SESSION sql_mode=?',
            [implode(',', array_unique($modes))]
        );
    }

    public function getSQLMode()
    {
        $this->query('SELECT @@SESSION.sql_mode');

        return explode(',', $this->fetchColumn());
    }

    /**
     * Execute SQL.
     *
     * @param string $sql
     * @param array $options
     *
     * @return mixed
     */
    public function exec($sql, array $options = null, $bind = null)
    {
        $this->sql = $this->normalizeSQL($sql);
        try {
            if (is_null($options)) {
                $this->ecount = $this->handler->exec($this->sql);
            } else {
                $this->prepare($this->sql);
                if (is_array($bind)) {
                    $is_hash = ($bind !== array_values($bind));
                    $n = 0;
                    foreach ($options as $key => $value) {
                        $param = ($is_hash) ? ":{$key}" : ++$n;
                        switch ($bind[$key]) {
                            case 'blob':
                                $type = PDO::PARAM_LOB;
                                break;
                            case 'bool':
                                $type = PDO::PARAM_BOOL;
                                break;
                            case 'int':
                                $type = PDO::PARAM_INT;
                                break;
                            case 'null':
                                $type = PDO::PARAM_NULL;
                                break;
                            default:
                                $type = PDO::PARAM_STR;
                                break;
                        }
                        $this->statement->bindValue($param, $value, $type);
                    }
                    $options = null;
                }
                $this->ecount = $this->execute($options);
            }
        } catch (PDOException $e) {
            $this->error_code = $e->getCode();
            $this->error_message = $e->getMessage();

            return self::exception($this->error_message, $this->error_code, $e);
        }

        return $this->ecount;
    }

    /**
     * Execute SQL.
     *
     * @param string $sql
     *
     * @return mixed
     */
    public function query($sql, $options = null, $bind = null)
    {
        $this->sql = $this->normalizeSQL($sql);
        try {
            if (is_array($options)) {
                $this->prepare($this->sql);
                if (is_array($bind)) {
                    $is_hash = ($bind !== array_values($bind));
                    $n = 0;
                    foreach ($options as $key => $value) {
                        $param = ($is_hash) ? ":{$key}" : ++$n;
                        switch ($bind[$key]) {
                            case 'blob':
                                $type = PDO::PARAM_LOB;
                                break;
                            case 'bool':
                                $type = PDO::PARAM_BOOL;
                                break;
                            case 'int':
                                $type = PDO::PARAM_INT;
                                break;
                            case 'null':
                                $type = PDO::PARAM_NULL;
                                break;
                            default:
                                $type = PDO::PARAM_STR;
                                break;
                        }
                        $this->statement->bindValue($param, $value, $type);
                    }
                    $options = null;
                }
                $this->execute($options);
            } else {
                $this->statement = $this->handler->query($this->sql);
            }
        } catch (PDOException $e) {
            $this->error_code = $e->getCode();
            $this->error_message = $e->getMessage();

            return self::exception($this->error_message, $this->error_code, $e);
        }

        return $this->statement;
    }

    /**
     * exec insert SQL.
     *
     * @param string $table
     * @param array  $data
     * @param array  $raws
     * @param array  $fields
     *
     * @return mixed
     */
    public function insert($table, array $data, $raws = null, $fields = null, bool $ignore = false)
    {
        if (is_null($fields)) {
            $fields = self::getFields($table, true);
        }
        $data = (Variable::isHash($data)) ? [$data] : $data;
        $raws = (Variable::isHash($raws)) ? [$raws] : $raws;
        $cnt = 0;
        $keys = [];
        $rows = [];
        foreach ($data as $n => $unit) {
            $vals = [];
            foreach ($unit as $key => $value) {
                if ($cnt === 0) {
                    $keys[] = "\"$key\"";
                }
                $fZero = (
                    isset($fields[$key])
                    && isset($fields[$key]['Type'])
                    && self::is_number($fields[$key]['Type'])
                ) ? true : false;
                $vals[] = (is_null($value)) ? 'NULL' : $this->quote($value, $fZero);
            }
            if (isset($raws[$n])) {
                foreach ($raws[$n] as $key => $value) {
                    if ($cnt === 0) {
                        $keys[] = "\"$key\"";
                    }
                    $vals[] = $value;
                }
            }
            $rows[] = '('.implode(',', $vals).')';
            ++$cnt;
        }
        $sql = '('.implode(',', $keys).') VALUES '.implode(',', $rows);

        $ignore = (false !== $ignore) ? 'IGNORE ' : '';

        return $this->exec("INSERT {$ignore}INTO \"{$table}\" {$sql}");
    }

    /**
     * exec update SQL.
     *
     * @param string $table
     * @param array  $data
     * @param string $statement
     * @param array  $options
     * @param array  $raws
     * @param array  $fields
     *
     * @return mixed
     */
    public function update($table, $data, $statement = '', $options = [], $raws = null, $fields = null)
    {
        if (is_null($fields)) {
            $fields = self::getFields($table, true);
        }
        $pair = [];
        foreach ($data as $key => $value) {
            $type = (isset($fields[$key]['Type'])) ? $fields[$key]['Type'] : null;
            $fZero = (isset($fields[$key]) && self::is_number($type)) ? true : false;
            $value = (is_null($value)) ? 'NULL' : $this->quote($value, $fZero);
            $pair[] = "\"$key\" = $value";
        }
        if (is_array($raws)) {
            foreach ($raws as $key => $value) {
                $pair[] = "\"$key\" = ".$value;
            }
        }
        $sql = 'SET '.implode(',', $pair);
        if (!empty($statement)) {
            $sql .= ' WHERE '.$this->prepareStatement($statement, $options);
        }

        return $this->exec("UPDATE \"$table\" $sql");
    }

    /**
     * exec delete SQL.
     *
     * @param string $table
     * @param string $where_clause
     * @param array  $options
     *
     * @return mixed
     */
    public function delete($table, $where_clause = '', $options = [])
    {
        $this->prepare(
            "DELETE FROM `{$table}`" . self::optimizeWhereClause($where_clause)
        );

        return $this->execute($options);
    }

    /**
     * exec update or insert SQL.
     *
     * @param string $table
     * @param array  $data
     * @param array  $unique
     * @param array  $raws
     *
     * @return mixed
     */
    public function updateOrInsert($table, array $data, $unique, $raws = [])
    {
        $ecount = 0;
        if (Variable::isHash($data)) {
            $data = [$data];
        }
        foreach ($data as $unit) {
            $keys = array_keys($unit);
            $update = $unit;
            $where = [];
            foreach ($unique as $key) {
                if (is_null($update[$key])) {
                    $arr[] = "{$key} IS NULL";
                } else {
                    $arr[] = "{$key} = ?";
                    $where[] = $update[$key];
                }
                if (in_array($key, $keys)) {
                    unset($update[$key]);
                }
            }
            $statement = implode(' AND ', $arr);

            if (false === $ret = self::update($table, $update, $statement, $where, $raws)) {
                return false;
            }
            if ($ret === 0 && false === self::exists($table, $statement, $where)) {
                if (false === $ret = self::insert($table, $unit, $raws)) {
                    return false;
                }
            }
            $ecount += $ret;
        }
        $this->ecount = $ecount;

        return $this->ecount;
    }

    /**
     * exec insert or update SQL.
     *
     * @param string $table
     * @param array  $data
     * @param array  $unique
     * @param array  $raws
     * @param array  $fields
     *
     * @return mixed
     */
    public function replace($table, array $data, $unique, $raws = [], $fields = null)
    {
        $ecount = 0;
        if (is_null($fields)) {
            $fields = self::getFields($table, true);
        }
        if (Variable::isHash($data)) {
            $data = [$data];
        }
        $cnt = 0;
        $keys = [];
        $dest = [];
        $cols = '';
        foreach ($data as $unit) {
            $vals = [];
            foreach ($unit as $key => $value) {
                if (empty($cols)) {
                    $keys[] = "\"$key\"";
                }
                $fZero = (isset($fields[$key]) &&
                    isset($fields[$key]['Type']) &&
                    self::is_number($fields[$key]['Type'])) ? true : false;
                $vals[] = (is_null($value)) ? 'NULL' : $this->quote($value, $fZero);
                if (!in_array($key, $unique)) {
                    if (is_null($value)) {
                        $dest[] = "\"$key\" IS NULL";
                    } else {
                        $dest[] = "\"$key\" = ".$this->quote($value, $fZero);
                    }
                }
            }
            foreach ($raws as $key => $value) {
                if (empty($cols)) {
                    $keys[] = "\"$key\"";
                }
                $vals[] = $value;
                if (!in_array($key, $unique)) {
                    $dest[] = "\"$key\" = {$value}";
                }
            }
            if (empty($cols)) {
                $cols = implode(',', $keys);
            }
            $sql = "($cols) VALUES (".implode(',', $vals).')';
            if ($this->driver == 'mysql') {
                $sql = "INSERT INTO \"$table\" $sql ON DUPLICATE KEY UPDATE ".implode(',', $dest);
            } elseif ($this->driver == 'pgsql') {
                $where = [];
                $arr = [];
                foreach ($unique as $key) {
                    $where[] = $unit[$key];
                    $arr[] = "{$key} = ?";
                    unset($unit[$key]);
                }
                if (false === $ret = self::update($table, $unit, implode(' AND ', $arr), $where, $raws)) {
                    return false;
                }
                if ($ret > 0) {
                    continue;
                }
                $sql = "INSERT INTO \"$table\" $sql";
            } elseif ($this->driver == 'sqlite' || $this->driver == 'sqlite2') {
                $sql = "INSERT INTO \"$table\" $sql";
                if (false === $ret = $this->exec($sql)) {
                    if (preg_match('/columns? (.+) (is|are) not unique/i', $this->error(), $match)) {
                        $unique = Text::explode(',', $match[1]);
                        $where = [];
                        $arr = [];
                        foreach ($unique as $key) {
                            $where[] = $unit[$key];
                            $arr[] = "{$key} = ?";
                            unset($unit[$key]);
                        }
                        if (false === $ret = self::update($table, $unit, implode(' AND ', $arr), $where, $raws)) {
                            return false;
                        }
                    } else {
                        return false;
                    }
                }
                $ecount += $ret;
                continue;
            }
            if (false === $ret = $this->exec($sql)) {
                return false;
            }
            $ecount += $ret;
        }
        $this->ecount = $ecount;

        return $this->ecount;
    }

    public function merge($table_name, array $data, array $skip = [], $key_name = 'PRIMARY')
    {
        if ($this->driver != 'mysql') {
            throw new ErrorException('This function supports only for MySQL');
        }

        if (false === self::query(
            "show keys from {$table_name} where Key_name = ?",
            [$key_name]
        )) {
            return false;
        }

        $uniques = [];
        while ($unit = self::fetch()) {
            $uniques[$unit['Seq_in_index']] = $unit['Column_name'];
        }

        $replaces = [];
        $columns = [];
        $updates = [];
        foreach ($data as $key => $value) {
            $columns[] = "`{$key}`";
            $replaces[] = $value;
            if (!in_array($key, $uniques)) {
                $updates[$key] = $value;
            }
        }
        $ph1 = implode(',', array_fill(0, count($replaces), '?'));

        $col = implode(',', $columns);
        $columns = [];
        foreach ($updates as $key => $value) {
            $columns[] = "`{$key}` = ?";
            $replaces[] = $value;
        }
        $ph2 = implode(',', $columns);
        $sql = "INSERT INTO {$table_name} ({$col}) VALUES ({$ph1})
                    ON DUPLICATE KEY UPDATE {$ph2}";

        return self::query($sql, $replaces);
    }

    /**
     * Select.
     *
     * @param string $columns
     * @param string $table
     * @param string $statement
     * @param array  $options
     *
     * @return mixed
     */
    public function select($columns, $table, $statement = '', $options = [])
    {
        $columns = self::verifyColumns($columns);
        $sql = "SELECT $columns FROM $table";
        if (!empty($statement)) {
            $sql .= ' '.$this->prepareStatement($statement, $options);
        }
        if ($this->query($sql)) {
            return $this->fetchAll();
        }

        return false;
    }

    /**
     * Exists Records.
     *
     * @param string $table
     * @param string $statement
     * @param array  $options
     *
     * @return bool
     */
    public function exists($table, $statement = '', $options = [])
    {
        $sql = "SELECT * FROM $table";
        if (!empty($statement)) {
            $sql .= ' WHERE ' . $statement;
        }
        $sql .= ' LIMIT 1';

        if (false === $this->query($sql, $options) || empty($this->fetch())) {
            return false;
        }

        return true;
    }

    /**
     * RecordCount.
     *
     * @param string $table
     * @param string $statement
     * @param array  $options
     *
     * @return mixed
     */
    public function count($table, $statement = '', $options = [])
    {
        $sql = "SELECT COUNT(*) AS cnt FROM $table";
        if (!empty($statement)) {
            $sql .= ' WHERE '.$this->prepareStatement($statement, $options);
        }

        return ($this->query($sql)) ? (int) $this->fetchColumn() : false;
    }

    /**
     * Get Value.
     *
     * @param string $column
     * @param string $table
     * @param string $statement
     * @param array  $options
     *
     * @return mixed
     */
    public function get($column, $table, $statement = '', $options = [])
    {
        $column = self::verifyColumns($column);
        $sql = "SELECT $column FROM $table";
        if (!empty($statement)) {
            $sql .= ' WHERE '.$this->prepareStatement($statement, $options);
        }
        if (false === $this->query($sql)) {
            return false;
        }
        $ret = $this->fetch();
        if (!is_array($ret)) {
            return $ret;
        } elseif (count($ret) > 1) {
            return $ret;
        }

        return array_shift($ret);
    }

    /**
     * MIN Value.
     *
     * @param string $column
     * @param string $table
     * @param string $statement
     * @param array  $options
     *
     * @return mixed
     */
    public function min($column, $table, $statement = '', $options = [])
    {
        $sql = "SELECT MIN($column) FROM \"$table\"";
        if (!empty($statement)) {
            $sql .= ' WHERE '.$this->prepareStatement($statement, $options);
        }
        if ($this->query($sql)) {
            return $this->fetchColumn();
        }

        return false;
    }

    /**
     * MAX Value.
     *
     * @param string $column
     * @param string $table
     * @param string $statement
     * @param array  $options
     *
     * @return mixed
     */
    public function max($column, $table, $statement = '', $options = [])
    {
        $sql = "SELECT MAX($column)
                  FROM \"$table\"";
        if (!empty($statement)) {
            $sql .= ' WHERE '.$this->prepareStatement($statement, $options);
        }
        if ($this->query($sql)) {
            return $this->fetchColumn();
        }

        return false;
    }

    /**
     * Prepare.
     *
     * @param string $statement
     *
     * @return mixed
     */
    public function prepare($statement)
    {
        $statement = $this->normalizeSQL($statement);

        return $this->statement = $this->handler->prepare($statement);
    }

    /**
     * Execute.
     *
     * @param array $params
     *
     * @return bool
     */
    public function execute($input_parameters)
    {
        if (false !== $this->statement->execute($input_parameters)) {
            return $this->statement->rowCount();
        }

        return false;
    }

    /**
     * Prepare statement.
     *
     * @param string $statement
     * @param array  $options
     *
     * @return string
     */
    public function prepareStatement($statement, array $options)
    {
        $statement = $this->normalizeSQL($statement);
        if ($options !== array_values($options)) {
            $pattern = [];
            $replace = [];
            $holder = [];
            foreach ($options as $key => $option) {
                if (is_int($key)) {
                    $holder[] = $option;
                    continue;
                }
                $key = preg_replace('/^:/', '', $key, 1);
                $pattern[] = '/'.preg_quote(preg_replace('/^:?/', ':', $key, 1), '/').'/';
                $replace[] = (is_null($option)) ? 'NULL' : $this->quote(str_replace('$', '$\\', $option));
            }
            $statement = preg_replace($pattern, $replace, $statement);
        } else {
            $holder = $options;
        }
        foreach ($holder as $option) {
            $replace = (is_null($option)) ? 'NULL' : $this->quote(str_replace('$', '$\\', $option));
            $statement = preg_replace("/\?/", $replace, $statement, 1);
        }

        return str_replace('$\\', '$', $statement);
    }

    /**
     * result of query.
     *
     * @param int $type
     * @param int $cursor
     * @param int $offset
     *
     * @return mixed
     */
    public function fetch($type = PDO::FETCH_ASSOC, $cursor = PDO::FETCH_ORI_NEXT, $offset = 0)
    {
        try {
            $data = $this->statement->fetch($type, $cursor, $offset);
        } catch (PDOException $e) {
            $this->error_code = $e->getCode();
            $this->error_message = $e->getMessage();

            return self::exception($this->error_message, $this->error_code, $e);
        }

        return $data;
    }

    /**
     * result of query.
     *
     * @param mixed $type
     *
     * @return mixed
     */
    public function fetchAll($type = PDO::FETCH_ASSOC, $columnIndex = 0)
    {
        try {
            if ($type == PDO::FETCH_COLUMN) {
                $data = $this->statement->fetchAll($type, $columnIndex);
            } else {
                $data = $this->statement->fetchAll($type);
            }
        } catch (PDOException $e) {
            $this->error_code = $e->getCode();
            $this->error_message = $e->getMessage();

            return self::exception($this->error_message, $this->error_code, $e);
        }

        return $data;
    }

    /**
     * result of query.
     *
     * @param mixed $type
     *
     * @return mixed
     */
    public function fetchColumn($column_number = 0)
    {
        try {
            $data = $this->statement->fetchColumn($column_number);
        } catch (PDOException $e) {
            $this->error_code = $e->getCode();
            $this->error_message = $e->getMessage();

            return self::exception($this->error_message, $this->error_code, $e);
        }

        return $data;
    }

    /**
     * escape string.
     *
     * @param mixed $value
     * @param int   $force
     *
     * @return string
     */
    public function quote($value, $force = null)
    {
        if (!is_null($force)) {
            $parameter_type = (int) $force;
        } elseif (is_null($value)) {
            $parameter_type = PDO::PARAM_NULL;
        } elseif (preg_match('/^[0-9]+$/', $value)) {
            $parameter_type = PDO::PARAM_INT;
        } else {
            $parameter_type = PDO::PARAM_STR;
        }

        return $this->handler->quote($value, $parameter_type);
    }

    /**
     * escape table or column name.
     *
     * @return string
     */
    public function escapeName($str)
    {
        $str = $this->handler->quote($str);
        $str = preg_replace("/^'/", '"', $str, 1);
        $str = preg_replace("/'$/", '"', $str, 1);

        return $str;
    }

    /**
     * PDOStatement::getColumnMeta is EXPERIMENTAL.
     * The behaviour of this function, its name, and surrounding documentation
     * may change without notice in a future release of PHP.
     *
     * @return array
     */
    public function fields($data = null)
    {
        $result = [];
        if (is_array($data)) {
            return array_keys($data);
        }
        if ($this->driver == 'sqlite' || $this->driver == 'sqlite2') {
            if (preg_match("/^SELECT\s+.+\s+FROM\s[`'\"]?(\w+)[`'\"]?.*$/i", $this->sql, $match)) {
                $tableName = $match[1];
            } elseif (preg_match("/^UPDATE\s+[`'\"]?(\w+)[`'\"]?.*$/i", $this->sql, $match)) {
                $tableName = $match[1];
            }
            $sql = "PRAGMA table_info($tableName)";
            if ($this->query($sql)) {
                $result = $this->fetchAll(PDO::FETCH_COLUMN, 1);
            }
        } else {
            for ($i = 0; $i < $this->statement->columnCount(); ++$i) {
                $meta = $this->statement->getColumnMeta($i);
                $result[] = $meta['name'];
            }
        }

        return $result;
    }

    /**
     * begin transaction.
     *
     * @param string $identifier
     *
     * @return bool
     */
    public function begin($identifier = ''): bool
    {
        if ($this->handler->inTransaction() || $this->handler->beginTransaction()) {
            if (empty($identifier) || false !== $this->savepoint($identifier)) {
                return true;
            }
        }

        return false;
    }

    /**
     * commit transaction.
     *
     * @return bool
     */
    public function commit($identifier = ''): bool
    {
        if (!empty($identifier)) {
            return false !== $this->release($identifier);
        }
        if ($this->handler->commit()) {
            return true;
        }

        return false;
    }

    /**
     * rollback transaction.
     *
     * @param string $identifier
     *
     * @return bool
     */
    public function rollback($identifier = '')
    {
        if (false === $this->handler->inTransaction()) {
            return true;
        }
        if (!empty($identifier)) {
            return false !== $this->query("ROLLBACK TO SAVEPOINT $identifier");
        }
        try {
            if ($this->handler->rollBack()) {
                return true;
            }
        } catch (PDOException $e) {
            $this->error_code = $e->getCode();
            $this->error_message = $e->getMessage();
        }

        return self::exception($this->error_message, $this->error_code, $e);
    }

    /**
     * add savepoint to transaction.
     *
     * @return mixed
     */
    public function savepoint($identifier = '')
    {
        return $this->query("SAVEPOINT $identifier");
    }

    /**
     * release savepoint from transaction.
     *
     * @return mixed
     */
    public function release($identifier = '')
    {
        return $this->query("RELEASE SAVEPOINT $identifier");
    }

    /**
     * transaction exists.
     *
     * @return bool
     */
    public function getTransaction()
    {
        return $this->handler->inTransaction();
    }

    /**
     * record count of execute query.
     *
     * @return int
     */
    public function recordCount($sql = '', array $options = null)
    {
        if (empty($sql)) {
            $sql = $this->sql;
        }

        try {
            $sql = 'SELECT COUNT(*) AS rec FROM ('.$sql.') AS rc';
            if (is_array($options)) {
                $this->prepare($sql);
                $this->execute($options);
            } else {
                $this->query($sql);
            }

            return (int)$this->statement->fetchColumn();
        } catch (PDOException $e) {
            $this->error_code = $e->getCode();
            $this->error_message = $e->getMessage();
        }

        return self::exception($this->error_message, $this->error_code, $e);
    }

    /**
     * record count of execute query.
     *
     * @return int
     */
    public function rowCount()
    {
        try {
            return $this->statement->rowCount();
        } catch (PDOException $e) {
            $this->error_code = $e->getCode();
            $this->error_message = $e->getMessage();
        }

        return self::exception($this->error_message, $this->error_code, $e);
    }

    /**
     * Error message.
     *
     * @return string
     */
    public function error()
    {
        $err = $this->errorInfo();
        if (!empty($err)) {
            return $err[2];
        }

        return $this->error_message;
    }

    /**
     * AUTO_INCREMENT.
     *
     * @param string $table Table Name
     *
     * @return mixed
     */
    public function lastInsertId($table = null, $col = null)
    {
        if (is_null($table)) {
            return $this->handler->lastInsertId($col);
        }

        $sql = "SELECT LAST_INSERT_ID() AS id FROM \"$table\"";
        if ($this->driver == 'sqlite' || $this->driver == 'sqlite2') {
            $sql = "SELECT last_insert_rowid() AS id FROM \"$table\"";
        }
        if ($this->query($sql)) {
            $num = $this->fetch();

            return $num['id'];
        }

        return;
    }

    /**
     * Get field list.
     *
     * @param string $table     Table Name
     * @param bool   $property
     * @param bool   $comment
     * @param string $statement
     *
     * @return mixed
     */
    public function getFields($table, $property = false, $comment = false, $statement = '')
    {
        if ($this->driver === 'mysql') {
            $sql = ($comment === true) ? "SHOW FULL COLUMNS FROM `{$table}`" : "SHOW COLUMNS FROM `{$table}`";
            if (!empty($statement)) {
                $sql .= " $statement";
            }
        } elseif ($this->driver === 'pgsql') {
            $primary = [];
            $comments = [];
            $sql = 'SELECT ccu.column_name as column_name
                      FROM information_schema.table_constraints tc,
                           information_schema.constraint_column_usage ccu
                     WHERE tc.table_catalog = '.$this->quote($this->source).'
                       AND tc.table_name = '.$this->quote($table)."
                       AND tc.constraint_type = 'PRIMARY KEY'
                       AND tc.table_catalog = ccu.table_catalog
                       AND tc.table_schema = ccu.table_schema
                       AND tc.table_name = ccu.table_name
                       AND tc.constraint_name = ccu.constraint_name";
            if ($this->query($sql)) {
                $result = $this->fetchAll();
                foreach ($result as $unit) {
                    $primary[$unit['column_name']] = 'PRI';
                }
            }
            if ($comment === true) {
                $sql = 'SELECT pa.attname as column_name,
                               pd.description as column_comment
                          FROM pg_stat_all_tables psat,
                               pg_description pd,
                               pg_attribute pa
                         WHERE psat.schemaname = (
                                   SELECT schemaname
                                     FROM pg_stat_user_tables
                                    WHERE relname = '.$this->quote($table).'
                               )
                           AND psat.relname = '.$this->quote($table).'
                           AND psat.relid = pd.objoid
                           AND pd.objsubid <> 0
                           AND pd.objoid = pa.attrelid
                           AND pd.objsubid = pa.attnum
                         ORDER BY pd.objsubid';
                if ($this->query($sql)) {
                    $result = $this->fetchAll();
                    foreach ($result as $unit) {
                        $comments[$unit['column_name']] = $unit['column_comment'];
                    }
                }
            }

            if (!empty($statement)) {
                $statement = "AND column_name $statement";
            }

            $sql = sprintf('SELECT *
                      FROM information_schema.columns
                     WHERE table_catalog = '.$this->quote($this->source).'
                       AND table_name = '.$this->quote($table).' %s
                     ORDER BY ordinal_position', $statement);
        } elseif ($this->driver === 'sqlite' || $this->driver === 'sqlite2') {
            $sql = "PRAGMA table_info($table);";
        }
        $data = [];
        if ($this->query($sql)) {
            while ($value = $this->fetch()) {
                if ($property === false) {
                    if ($this->driver === 'sqlite' || $this->driver === 'sqlite2') {
                        $data[] = $value['name'];
                    } elseif ($this->driver === 'pgsql') {
                        $data[] = $value['column_name'];
                    } else {
                        $data[] = $value['Field'];
                    }
                } else {
                    if ($this->driver === 'sqlite' || $this->driver === 'sqlite2') {
                        $data[$value['name']] = $value;
                    } elseif ($this->driver === 'pgsql') {
                        if (isset($primary[$value['column_name']])) {
                            $value['Key'] = $primary[$value['column_name']];
                        }
                        if (isset($comments[$value['column_name']])) {
                            $value['Comment'] = $comments[$value['column_name']];
                        }
                        $data[$value['column_name']] = $value;
                    } else {
                        $data[$value['Field']] = $value;
                    }
                }
            }
        }

        return $data;
    }

    /**
     * the field type is number or else.
     *
     * @param string $type
     *
     * @return bool
     */
    public static function is_number($type)
    {
        return preg_match('/^(double|float|real|dec|int|tinyint|smallint|mediumint|bigint|numeric|bit)/i', $type);
    }

    /**
     * get last query.
     *
     * return string
     */
    public function latestSQL()
    {
        return $this->sql;
    }

    /**
     * get ecount.
     *
     * return int
     */
    public function getRow()
    {
        return $this->ecount;
    }

    /**
     * PDO::errorInfo.
     *
     * @return array
     */
    public function errorInfo()
    {
        if (is_object($this->statement)) {
            $info = $this->statement->errorInfo();
            if (!is_null($info[2])) {
                return $info;
            }
        }
        if (is_null($this->handler)) {
            return;
        }

        return $this->handler->errorInfo();
    }

    /**
     * PDO::errorCode.
     *
     * @return array
     */
    public function errorCode()
    {
        if (is_object($this->statement)) {
            $code = $this->statement->errorCode();
            if (!is_null($code)) {
                return $code;
            }
        }

        return $this->handler->errorCode();
    }

    /**
     * Set PDO Attribute.
     *
     * @param int   $attribute
     * @param mixed $value
     *
     * @return bool
     */
    public function setAttribute($attribute, $value)
    {
        return $this->handler->setAttribute($attribute, $value);
    }

    /**
     * AES Encrypt data.
     *
     * @param string $phrase
     *
     * @return string
     */
    public function aes_encrypt($phrase, $salt)
    {
        $sql = 'SELECT HEX(AES_ENCRYPT('.$this->quote($phrase).',
               '.$this->quote($salt).'));';
        if ($this->query($sql)) {
            return $this->fetchColumn();
        }
    }

    /**
     * Convert Count SQL.
     *
     * @param string $sql
     *
     * @return string
     */
    public static function countSQL($sql)
    {
        $arr = [];
        $tags = ['select', 'from'];
        foreach ($tags as $tag) {
            $offset = 0;
            while (false !== $idx = stripos($sql, $tag, $offset)) {
                if (!isset($arr[$tag])) {
                    $arr[$tag] = [];
                }
                $arr[$tag][] = $idx;
                $offset = $idx + strlen($tag);
            }
        }
        for ($i = 1; $i < count($arr['from']); ++$i) {
            if ($arr['select'][$i - 1] < $arr['from'][$i]) {
                $start = 6;
                $length = ($arr['from'][$i] - $start);

                return substr_replace($sql, ' COUNT(*) AS cnt ', $start, $length);
            }
        }

        return false;
    }

    /**
     * Normalize SQL sting.
     *
     * @param string $sql
     *
     * @return string
     */
    public function normalizeSQL($sql)
    {
        if ($this->driver === 'mysql') {
            $sql = preg_replace('/([^\\\])"/', '$1`', preg_replace('/^"/', '`', $sql));
        }
        $sql = preg_replace("/LIMIT[\s]+([0-9]+)[\s]*,[\s]*([0-9]+)/i", 'LIMIT $2 OFFSET $1', $sql);

        return $sql;
    }

    /**
     * Requote column name
     *
     * @param string $columns
     *
     * @return string
     */
    private static function verifyColumns($columns)
    {
        $columns = array_map([__CLASS__, 'quoteColumn'], explode(',', $columns));

        return implode(',', array_filter($columns));
    }

    private static function quoteColumn($column, $quote = '"')
    {
        $column = trim($column);
        if ($column === '*') {
            return $column;
        } elseif (preg_match('/^\s*(\w+)\s+as\s+(\w+)\s*$/i', $column, $match)) {
            return $quote . str_replace($quote, '', $match[1]) . $quote . ' AS '
                 . $quote . str_replace($quote, '', $match[2]) . $quote;
        } elseif (preg_match('/^(.+)\.\*$/', $column, $match)) {
            return $quote . str_replace($quote, '', $match[1]) . $quote . '.*';
        }

        return $quote . str_replace($quote, '', $column) . $quote;
    }

    private static function optimizeWhereClause($clause): string
    {
        if (preg_match('/^\s*$/', $clause)) {
            return '';
        }

        if (preg_match('/^\s*(where|order\s+by|limit\s+[0-9])\s+/i', $clause)) {
            return $clause;
        }

        return " WHERE {$clause}";
    }

    public function resetAutoIncrement($table)
    {
        $column = null;
        $fields = self::getFields($table, true);
        foreach ($fields as $field) {
            if ($field['Extra'] === 'auto_increment') {
                $column = $field['Field'];
            }
        }

        if (empty($column)) {
            trigger_error("Table {$table} doesn't have AUTO_INCREMENT", E_USER_WARNING);

            return false;
        }

        $self = false;
        if (!$this->getTransaction()) {
            $this->begin();
            $self = true;
        }

        if (false === $this->query('SET @n:=0')
            || false === $this->query("UPDATE `{$table}` SET `{$column}`=@n:=@n+1 ORDER BY `{$column}`")
        ) {
            if ($self) {
                $this->rollback();
            }

            return false;
        }

        if ($self && false === $this->commit()) {
            return false;
        }

        $max = $this->max($column, $table);

        return $this->query("ALTER TABLE `{$table}` AUTO_INCREMENT = " . (intval($max) + 1));
    }

    public function setErrorMode($mode = null)
    {
        $this->error_mode = $mode;
    }

    private function exception($message = '', $code = 0, $previous = null)
    {
        if ($this->error_mode === PDO::ERRMODE_EXCEPTION) {
            throw new DbException($message, $code, $previous);
        }

        return false;
    }

    public function execSql($fp)
    {
        if (!is_resource($fp)) {
            if (false === $fp = fopen($fp, 'r')) {
                return false;
            }
        }

        $sql = null;
        $command_type = null;
        $prev_chr = null;
        $quote = 'even';
        $db = self::getHandler();
        $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $success = 0;

        rewind($fp);
        while (false !== ($buffer = fgets($fp, 32))) {
            $buffer = str_replace("\r", '', $buffer);
            if (is_null($sql)) {
                if (preg_match('/^--[\s]*$/s', $buffer)) {
                    $command_type = 'empty comment';
                    $sql = null;
                } elseif (preg_match('/^(\r\n|\r|\n)$/s', $buffer)) {
                    $command_type = 'empty';
                } elseif (strpos($buffer, '-- ') === 0) {
                    $command_type = 'comment';
                    $sql = $buffer;
                //} elseif (strpos($buffer, '/*!') === 0) {
                //    $command_type = 'mysql versioning';
                //    $sql = $buffer;
                } else {
                    $command_type = 'sql';
                    $quote = 'even';
                    $sql = $sql ?? '';
                    $prev_chr = null;
                    foreach (mb_str_split($buffer) as $chr) {
                        if ($chr === "'" && $prev_chr !== '\\') {
                            $quote = ($quote === 'even') ? 'odd' : 'even';
                        } elseif ($prev_chr === ';' && $chr === "\n") {
                            $sql = null;
                            $quote = 'even';
                            break;
                        } elseif ($chr === ';') {
                            if ($quote !== 'odd') {
                                try {
                                    $db->exec($sql);
                                } catch (PDOException $e) {
                                    $this->fault = $e->errorInfo;
                                    $this->fault[] = $sql;
                                    if ($db->inTransaction()) {
                                        $db->rollBack();
                                    }

                                    return false;
                                }
                                ++$success;
                                $sql = '';
                                $quote = 'even';
                                $prev_chr = $chr;
                                continue;
                            }
                        }
                        $sql .= $chr;
                        $prev_chr = $chr;
                    }

                    if (feof($fp) && !empty($sql)) {
                        try {
                            $db->exec($sql);
                        } catch (PDOException $e) {
                            $this->fault = $e->errorInfo;
                            $this->fault[] = $sql;
                            if ($db->inTransaction()) {
                                $db->rollBack();
                            }

                            return false;
                        }
                        ++$success;
                        $sql = '';
                        $quote = 'even';
                        $prev_chr = $chr;
                        continue;
                    }
                }
            } else {
                if ($command_type === 'sql') {
                    foreach (mb_str_split($buffer) as $chr) {
                        if ($chr === "'" && $prev_chr !== '\\') {
                            $quote = ($quote === 'even') ? 'odd' : 'even';
                        } elseif ($prev_chr === ';' && $chr === "\n") {
                            $sql = null;
                            $quote = 'even';
                            break;
                        } elseif ($chr === ';') {
                            if ($quote !== 'odd') {
                                try {
                                    $db->exec($sql);
                                } catch (PDOException $e) {
                                    $this->fault = $e->errorInfo;
                                    $this->fault[] = $sql;
                                    if ($db->inTransaction()) {
                                        $db->rollBack();
                                    }

                                    return false;
                                }
                                ++$success;
                                $sql = '';
                                $quote = 'even';
                                $prev_chr = $chr;
                                continue;
                            }
                        }
                        $sql .= $chr;
                        $prev_chr = $chr;
                    }

                    if (feof($fp) && !empty($sql)) {
                        try {
                            $db->exec($sql);
                        } catch (PDOException $e) {
                            $this->fault = $e->errorInfo;
                            $this->fault[] = $sql;
                            if ($db->inTransaction()) {
                                $db->rollBack();
                            }

                            return false;
                        }
                        ++$success;
                        $sql = '';
                        $quote = 'even';
                        $prev_chr = $chr;
                        continue;
                    }
                } elseif ($command_type === 'comment') {
                    $sql .= $buffer;
                    if (preg_match('/.+(\r\n|\r|\n)$/s', $sql)) {
                        $sql = null;
                    }
                } elseif ($command_type === 'mysql versioning') {
                    $sql .= $buffer;
                    if (preg_match('/.+\*\/;(\r\n|\r|\n)$/s', $sql)) {
                        $sql = null;
                    }
                } else {
                    $sql = null;
                }
            }
        }

        return $success;
    }

    public function dump($tables, $options = null, $tofile = null, float $threshold = 0.25)
    {
        if (!is_null($tofile) && !file_exists($tofile) && false === @touch($tofile)) {
            return false;
        }
        $mem = $tofile ?? 'php://memory';
        if (false === $fp = fopen($mem, 'r+')) {
            return false;
        }

        $max_allowed_packet = self::MAX_ALLOWED_PACKET;
        if (false !== $this->query("SHOW VARIABLES LIKE 'max_allowed_packet'")) {
            $unit = $this->fetch();
            if (!empty($unit['Value'] ?? null)) {
                $max_allowed_packet = $unit['Value'];
            }
        }

        $charset = null;
        if (false !== $this->query("SHOW CREATE DATABASE `".$this->source."`")) {
            $unit = $this->fetch();
            if (preg_match('/DEFAULT\s+CHARACTER\s+SET\s+([^\s]+)/i', $unit['Create Database'], $match)) {
                $charset = $match[1];
            }
        }
        if (is_null($charset) && false !== $this->query("SHOW VARIABLES LIKE 'character_set_database'")) {
            $unit = $this->fetch();
            if (!empty($unit['Value'] ?? null)) {
                $charset = $unit['Value'];
            }
        }

        $ml = Environment::getMemoryLimit();
        $max_allowed_memory = ($ml / $max_allowed_packet >= 2) ? round($ml * $threshold) : null;

        $clone = self::getHandler();
        $db = self::getHandler();

        fwrite($fp, '/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;' . self::SQL_EOL);
        fwrite($fp, '/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;' . self::SQL_EOL);
        fwrite($fp, '/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;' . self::SQL_EOL);
        fwrite($fp, '/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;' . self::SQL_EOL);
        fwrite($fp, "/*!50503 SET NAMES {$charset} */;" . self::SQL_EOL);
        fwrite($fp, '/*!40103 SET @OLD_TIME_ZONE=@@TIME_ZONE */;' . self::SQL_EOL);
        fwrite($fp, "/*!40103 SET TIME_ZONE='+00:00' */;" . self::SQL_EOL);
        fwrite($fp, '/*!40014 SET @OLD_UNIQUE_CHECKS=@@UNIQUE_CHECKS, UNIQUE_CHECKS=0 */;' . self::SQL_EOL);
        fwrite($fp, '/*!40014 SET @OLD_FOREIGN_KEY_CHECKS=@@FOREIGN_KEY_CHECKS, FOREIGN_KEY_CHECKS=0 */;' . self::SQL_EOL);
        fwrite($fp, "/*!40101 SET @OLD_SQL_MODE=@@SQL_MODE, SQL_MODE='NO_AUTO_VALUE_ON_ZERO' */;" . self::SQL_EOL);
        fwrite($fp, '/*!40111 SET @OLD_SQL_NOTES=@@SQL_NOTES, SQL_NOTES=0 */;' . self::SQL_EOL);

        $statement = $db->query('SHOW TABLES');
        while ($unit = $statement->fetch()) {
            $table = array_shift($unit);
            if (!in_array($table, $tables)) {
                continue;
            }

            if ($options['no-create-info'] !== 1) {
                fwrite($fp, self::SQL_EOL);
                fwrite($fp, '--' . self::SQL_EOL);
                fwrite($fp, "-- Table structure for table `{$table}`" . self::SQL_EOL);
                fwrite($fp, '--' . self::SQL_EOL);
                fwrite($fp, self::SQL_EOL);
                fwrite($fp, "DROP TABLE IF EXISTS `{$table}`;" . self::SQL_EOL);
                fwrite($fp, '/*!40101 SET @saved_cs_client     = @@character_set_client */;' . self::SQL_EOL);
                fwrite($fp, "/*!50503 SET character_set_client = {$charset} */;" . self::SQL_EOL);

                if (false === ($stat = $clone->query("SHOW CREATE TABLE `{$table}`"))) {
                    return false;
                }
                while ($fetch = $stat->fetch()) {
                    $create = array_pop($fetch);
                    $create = preg_replace('/([^\r])\n/', '$1' . self::SQL_EOL, $create);
                    fwrite($fp, $create . ';' . self::SQL_EOL);
                }

                fwrite($fp, '/*!40101 SET character_set_client = @saved_cs_client */;' . self::SQL_EOL);
            }

            if ($options['no-data'] !== 1) {
                $columns = [];
                if (false === ($stat = $clone->query("SHOW COLUMNS FROM `{$table}`"))) {
                    return false;
                }
                while ($unit = $stat->fetch()) {
                    $columns[] = $unit['Type'];
                }

                if (false === ($stat = $clone->query("SELECT COUNT(*) AS cnt FROM `{$table}`"))) {
                    return false;
                }
                $records = $stat->fetchColumn();

                if (false === ($stat = $clone->query("SELECT * FROM `{$table}`"))) {
                    return false;
                }

                fwrite($fp, self::SQL_EOL);
                fwrite($fp, '--' . self::SQL_EOL);
                fwrite($fp, "-- Dumping data for table `{$table}`" . self::SQL_EOL);
                fwrite($fp, '--' . self::SQL_EOL);
                fwrite($fp, self::SQL_EOL);

                if ($records > 0) {
                    fwrite($fp, "LOCK TABLES `{$table}` WRITE;" . self::SQL_EOL);
                    fwrite($fp, "/*!40000 ALTER TABLE `{$table}` DISABLE KEYS */;" . self::SQL_EOL);

                    $insert = 'INSERT';
                    if (($options['insert-ignore'] ?? null) === 1) {
                        $insert .= ' IGNORE';
                    }
                    $insert_sql = "{$insert} INTO `{$table}` VALUES ";
                    $strlen = strlen($insert_sql);

                    $values = '';
                    $comma = '';
                    while ($unit = $stat->fetch(PDO::FETCH_NUM)) {
                        $str = $comma.'(';
                        $separator = '';
                        foreach ($unit as $n => $value) {
                            $quote = '';
                            if (is_null($value)) {
                                $value = 'NULL';
                            } else {
                                $value = str_replace(['\\', '"', "'", "\r", "\n"], ['\\\\', '\\"', "\\'", '\\r', '\\n'], $value);
                                if (!preg_match('/^(tinyint|smallint|mediumint|int|bigint|float|double|decimal|numeric)/i', $columns[$n])) {
                                    $quote = "'";
                                }
                            }

                            $str .= "{$separator}{$quote}{$value}{$quote}";
                            $separator = ',';
                        }

                        $str .= ')';
                        $length = strlen($str);

                        $comma = ',';
                        if (($strlen + $length) >= $max_allowed_packet) {
                            if (!empty($values)) {
                                fwrite($fp, ((!isset($fputs_started)) ? $insert_sql : '') . $values . ';' . self::SQL_EOL);
                                unset($fputs_started);
                                $str = preg_replace('/^,\(/', '(', $str);
                            }
                            $values = $str;
                            $strlen = strlen($insert_sql) + $length;
                        } elseif (($strlen + $length) >= $max_allowed_memory) {
                            if (!empty($values)) {
                                fwrite($fp, ((!isset($fputs_started)) ? $insert_sql : '') . $values);
                                $fputs_started = true;
                            }
                            $values = $str;
                        } else {
                            $values .= $str;
                            $strlen += $length;
                        }
                    }
                    if (!empty($values)) {
                        fwrite($fp, ((!isset($fputs_started)) ? $insert_sql : '') . $values . ';' . self::SQL_EOL);
                    } elseif (isset($fputs_started)) {
                        fwrite($fp, ';' . self::SQL_EOL);
                    }

                    fwrite($fp, "/*!40000 ALTER TABLE `{$table}` ENABLE KEYS */;" . self::SQL_EOL);
                    fwrite($fp, 'UNLOCK TABLES;' . self::SQL_EOL);
                    unset($fputs_started);
                } else {
                    fwrite($fp, '-- Empty set;' . self::SQL_EOL);
                }
            }
        }

        fwrite($fp, self::SQL_EOL);
        fwrite($fp, '/*!40103 SET TIME_ZONE=@OLD_TIME_ZONE */;' . self::SQL_EOL);
        fwrite($fp, '/*!40101 SET SQL_MODE=@OLD_SQL_MODE */;' . self::SQL_EOL);
        fwrite($fp, '/*!40014 SET FOREIGN_KEY_CHECKS=@OLD_FOREIGN_KEY_CHECKS */;' . self::SQL_EOL);
        fwrite($fp, '/*!40014 SET UNIQUE_CHECKS=@OLD_UNIQUE_CHECKS */;' . self::SQL_EOL);
        fwrite($fp, '/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;' . self::SQL_EOL);
        fwrite($fp, '/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;' . self::SQL_EOL);
        fwrite($fp, '/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;' . self::SQL_EOL);
        fwrite($fp, '/*!40111 SET SQL_NOTES=@OLD_SQL_NOTES */;' . self::SQL_EOL);

        if (!empty($tofile)) {
            return fclose($fp);
        }

        $content_length = ftell($fp);
        rewind($fp);

        $statement = $db->query('SELECT database()');
        $source = $statement->fetchColumn();

        $filename = $source . '.sql';
        $mime = 'text/plain';
        $charset = 'utf-8';

        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Pragma: no-cache');
        header("Content-Disposition: attachment; filename=\"{$filename}\"");
        header("Content-type: {$mime}{$charset}");
        header("Content-length: {$content_length}");
        echo stream_get_contents($fp);
        fclose($fp);

        exit;
    }

    public function getEncoding(): ?string
    {
        $enc = null;
        if (!empty($this->encoding)) {
            $enc = $this->encoding;
        } elseif ($this->query("SHOW VARIABLES LIKE 'character_set_client'")) {
            $fetch = $this->fetch();
            $enc = $fetch['value'] ?? null;
        }

        return $enc;
    }
}
