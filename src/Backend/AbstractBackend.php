<?php

namespace splitbrain\TheBankster\Backend;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

abstract class AbstractBackend
{

    protected $config;
    protected $db;
    protected $accountid;
    /** @var LoggerInterface */
    protected $logger;

    public function __construct($config, $accountid)
    {
        $this->config = $config;
        $this->accountid = $accountid;
        $this->logger = new NullLogger();
    }

    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Return all statements since the given DateTime
     *
     * Backends that can not select by time should return as many statements as possible
     *
     * @param \DateTime $since
     */
    abstract public function importTransactions(\DateTime $since);

    protected function storeTransaction(\splitbrain\TheBankster\Entity\Transaction $tx)
    {
        try {
            $tx->account = $this->accountid;
            $tx = $tx->save();
            $this->logger->notice("Saved:\t" . (string)$tx);
        } catch (\PDOException $e) {
            if ($e->getCode() == '23000') {
                $this->logger->warning("Duplicate:\t" . (string)$tx);
            } else {
                throw $e;
            }
        } catch (\Exception $e) {
            $this->logger->error('Transaction was not saved: {message}', ['message' => $e->getMessage()]);
            $this->logger->debug($e->getTraceAsString());
        }
    }
}