<?php

require_once("LoggerTrait.php");

class DB extends \PDO
{
    use LoggerTrait;

    protected $logger;
    protected $debug = false;

    /** @var  \PDOStatement */
    protected $sth;

    public function __construct(
        $dsn,
        $username = null,
        $password = null,
        $options = []
    ){
        parent::__construct(
            $dsn,
            $username,
            $password,
            $options
        );
        $this->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        $this->exec("SET SESSION sql_mode=''");
    }

    public function setLogger($logger)
    {
        $this->logger = $logger;
    }

    public function getLogger()
    {
        return $this->logger;
    }

    public function setDebug($on = true)
    {
        $this->debug = $on;
    }

    protected function bindValue(\PDOStatement $sth, $key, $val)
    {
        switch (gettype($val)) {
            case "boolean" :
                $type = \PDO::PARAM_BOOL;
                break;
            case "integer" :
                $type = \PDO::PARAM_INT;
                break;
            case "NULL" :
                $type = \PDO::PARAM_NULL;
                break;
            default:
                $type = \PDO::PARAM_STR;
        }

        return $sth->bindValue($key, $val, $type);
    }

    protected function pdoParamsFix($params)
    {
        // match standard PDO execute() behavior of zero-indexed arrays
        if (array_key_exists(0, $params)) {
            array_unshift($params, null);
            unset($params[0]);
        }

        return $params;
    }

    public function execute($sql, $params = [])
    {
        $params = $this->pdoParamsFix($params);

        try {
            if ($this->debug) {
                $this->debug('SQL: ' . $sql, $params);
            }

            $this->sth = $sth = $this->prepare($sql);

            foreach ($params as $key => $val) {
                $sth->bindValue($key, $val);
            }

            $r = $sth->execute();

            if ($r) {
                return $sth->rowCount();
            }

        } catch (\PDOException $e) {
            $this->error('SQL: ' . $sql, $params);
            $this->error(str_replace("\n", ' | ', $e->__toString()));
            $this->error('debugDumpParams:', $sth->debugDumpParams());

            return false;
        }
    }

    public function all($sql, $params = [])
    {
        $res = $this->execute($sql, $params);

        //For insert, update etc or on failure
        if (is_bool($res)) {
            return $res;
        }

        return $this->sth->fetchAll(\PDO::FETCH_ASSOC);   //\PDO::FETCH_NUM
    }

    public function line($sql, $params = [], $assoc = 1)
    {
        $this->execute($sql, $params);
        $mode = $assoc ? \PDO::FETCH_ASSOC : \PDO::FETCH_NUM;

        return $this->sth->fetch($mode);   //
    }

    public function scalar($sql, $params = [])
    {
        $this->execute($sql, $params);
        $l = $this->sth->fetch(\PDO::FETCH_NUM);
        return $l[0];
    }

    public function insert(string $table, array $values, array $columns = [], $update = '')
    {
        if (empty($values)) {
            return;
        }

        $keys = !empty($columns) ? $columns : array_keys(current($values));

        $keyStr = "`" . implode("`, `", $keys) . "`";

        $params = $valBinds = [];

        foreach ($values as $l) {
            $l1 = [];
            foreach ($keys as $key) {
                $l1[$key] = $l[$key];   //filter values "columns"
            }
            $l1 = array_values($l1);
            $params = array_merge($params, $l1);
            $valBinds[] = "(" . implode(',', array_fill(0, sizeof($l1), '?')) . ")";
        }
        $valStr = implode(', ', $valBinds);

        $dupArr = [];
        foreach ($keys as $key) {
            if ($key != 'id') {
                $dupArr[] = "`$key`=VALUES(`$key`)";
            }
        }
        $dupStr = implode(', ', $dupArr);

        $sql = "insert into `$table` ($keyStr) values $valStr on duplicate key update $dupStr $update";

        return $this->execute($sql, $params);
    }

    public function update($table, $id, $values, $columns)
    {
        if (empty($values)) {
            return;
        }

        $keys = !empty($columns) ? $columns : array_keys($values);

        $setArr = $params = [];
        foreach ($keys as $key) {
            $setArr[] = "`$key`=?";
            $params[] = isset($values[$key]) ? $values[$key] : '';
        }
        $setStr = implode(', ', $setArr);
        $params[] = $id;

        $sql = "update `$table` set $setStr where id=?";
        return $this->execute($sql, $params);
    }

}