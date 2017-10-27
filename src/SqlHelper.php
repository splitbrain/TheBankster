<?php

namespace splitbrain\TheBankster;

/**
 * Class SqlHelper
 * @package splitbrain\TheBankster
 *
 * Provides some helper method to execute raw queries
 */
class SqlHelper
{
    protected $pdo;

    /**
     * SqlHelper constructor.
     * @param \PDO $pdo
     */
    public function __construct(\PDO $pdo)
    {
        $this->pdo = $pdo;
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