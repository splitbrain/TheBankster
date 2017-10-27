<?php

namespace splitbrain\TheBankster;

class EntityManager extends \ORM\EntityManager {

    protected $sqlHelper;

    /** @inheritdoc */
    public function __construct($options=[])
    {
        parent::__construct($options);
        $this->sqlHelper = new SqlHelper($this->getConnection());
    }

    /**
     * @return SqlHelper
     */
    public function getSqlHelper() {
        return $this->sqlHelper;
    }
}