<?php

namespace splitbrain\TheBankster\Backend;

use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use splitbrain\TheBankster\DataBase;
use splitbrain\TheBankster\Transaction;

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
        $this->db = new DataBase();
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
     * @return Transaction[]
     */
    abstract public function importTransactions(\DateTime $since);

    protected function storeTransaction(Transaction $tx)
    {
        try {
            $stmt = $this->db->getPDO()->prepare(
                "INSERT OR IGNORE INTO transactions
                             (txid, account, datetime, amount, description, x_name, x_bank, x_acct)
                      VALUES (:txid, :account, :datetime, :amount, :description, :x_name, :x_bank, :x_acct)
                ");
            $data = $tx->getInsertionArray();
            $data[':account'] = $this->accountid;
            $stmt->execute($data);

            $this->logger->notice('Saved: ' . (string)$tx);
        } catch (\Exception $e) {
            $this->logger->error('Transaction was not saved: {message}', ['message' => $e->getMessage()]);
            $this->logger->debug($e->getTraceAsString());
        }
        // FIXME print success
    }
}