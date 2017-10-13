<?php

namespace splitbrain\TheBankster\Backend;

abstract class AbstractBackend {

    protected $config;

    public function __construct($config)
    {
        $this->config = $config;
    }

    /**
     * Retrun all statements since the given DateTime
     *
     * Backends that can not select by time should return as many statements as possible
     * and ensure the TXID stays the same (already known transactions will be ignored)
     *
     * @param \DateTime $since
     * @return array[]
     */
    abstract public function getStatements(\DateTime $since);
}