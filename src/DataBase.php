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
    }

    /**
     * @return \PDO
     */
    public function getPDO()
    {
        return $this->pdo;
    }
}