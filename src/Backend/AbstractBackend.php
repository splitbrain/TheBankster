<?php

namespace splitbrain\TheBankster\Backend;

use splitbrain\TheBankster\Transaction;

abstract class AbstractBackend {

    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Return all statements since the given DateTime
     *
     * Backends that can not select by time should return as many statements as possible
     *
     * @param \DateTime $since
     * @return Transaction[]
     */
    abstract public function getTransactions(\DateTime $since);
}