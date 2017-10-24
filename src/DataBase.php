<?php

namespace splitbrain\TheBankster;

class DataBase
{

    protected $pdo;

    protected $dbfile = __DIR__ . '/../data.sqlite3';

    /**
     * DataBase constructor.
     */
    public function __construct()
    {
        $this->pdo = new \PDO('sqlite:' . $this->dbfile);
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
        $this->pdo->exec('PRAGMA foreign_keys = ON');
    }

    /**
     * @return \PDO
     */
    public function getPDO()
    {
        return $this->pdo;
    }

    /**
     * @param string $sql
     * @param array $parameters
     * @return array
     */
    public function queryAll($sql, $parameters = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parameters);
        return $stmt->fetchAll();
    }

    /**
     * Query one single row
     *
     * @param string $sql
     * @param array $parameters
     * @return array|null
     */
    public function queryRecord($sql, $parameters = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parameters);
        $row = $stmt->fetch();

        if (is_array($row) && count($row)) return $row;
        return null;
    }

    /**
     * Query for exactly one single value
     *
     * @param string $sql
     * @param array $parameters
     * @return mixed|null
     */
    public function querySingleValue($sql, $parameters = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parameters);
        $row = $stmt->fetch();

        if (is_array($row) && count($row)) return array_values($row)[0];
        return null;
    }

    /**
     * Execute a statement
     *
     * Returns the last insert ID on INSERTs or the number of affected rows
     *
     * @param string $sql
     * @param array $parameters
     * @return int
     */
    public function exec($sql, $parameters = [])
    {
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($parameters);

        $count = $stmt->rowCount();
        if ($count && preg_match('/^INSERT /i', $sql)) {
            return $this->querySingleValue('SELECT last_insert_rowid()');
        }

        return $count;
    }
}