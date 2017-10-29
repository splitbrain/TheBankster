<?php

namespace splitbrain\TheBankster\Backend;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use splitbrain\TheBankster\Entity\Transaction;

abstract class AbstractBackend
{

    protected $config;
    protected $db;
    protected $accountid;
    /** @var LoggerInterface */
    protected $logger;

    /**
     * AbstractBackend constructor.
     * @param array $config
     * @param string $accountid
     */
    public function __construct($config, $accountid)
    {
        $this->config = $config;
        $this->accountid = $accountid;
        $this->logger = new NullLogger();
    }

    /**
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Describe what configuration is required for this backend
     *
     * @return array
     */
    abstract public static function configDescription();

    /**
     * Return all statements since the given DateTime
     *
     * Backends that can not select by time should return as many statements as possible
     *
     * @param \DateTime $since
     */
    abstract public function importTransactions(\DateTime $since);

    /**
     * All Backends need to call this from their importTransactions() method
     *
     * @param Transaction $tx
     */
    protected function storeTransaction(Transaction $tx)
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