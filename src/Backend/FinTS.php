<?php

namespace splitbrain\TheBankster\Backend;

use splitbrain\TheBankster\Transaction;

class FinTS extends AbstractBackend
{

    protected $fints;

    public function __construct($config, $accountid)
    {
        parent::__construct($config, $accountid);

        $port = parse_url($this->config['url'], PHP_URL_PORT);
        $port = $port ?: 443;

        $this->fints = new \Fhp\FinTs(
            $this->config['url'],
            $port,
            $this->config['code'],
            $this->config['user'],
            $this->config['pass']
        );
    }


    /** @inheritdoc */
    public function importTransactions(\DateTime $since)
    {
        $transactions = [];

        $accounts = $this->fints->getSEPAAccounts();
        $this->logger->info('Found  {count} accounts.', ['count' => count($accounts)]);
        $account = $accounts[0];
        // use identifier when multiple acounts are available
        if (isset($this->config['ident'])) {
            foreach ($accounts as $acc) {
                if (
                    (strpos($acc->getAccountNumber(), $this->config['ident']) !== false) ||
                    (strpos($acc->getIban(), $this->config['ident']) !== false)
                ) {
                    $account = $acc;
                    break;
                }
            }
        }

        // get all the transactions
        $soa = $this->fints->getStatementOfAccount($account, $since, new \DateTime());
        foreach ($soa->getStatements() as $statement) {
            foreach ($statement->getTransactions() as $tx) {
                $amount = $tx->getAmount();
                if ($tx->getCreditDebit() == \Fhp\Model\StatementOfAccount\Transaction::CD_DEBIT) {
                    $amount *= -1;
                }

                $transaction = new Transaction(
                    $tx->getBookingDate(),
                    $amount,
                    join("\n", [
                        $tx->getDescription1(),
                        $tx->getDescription2(),
                        $tx->getBookingText()
                    ]),
                    $tx->getName(),
                    $tx->getBankCode(),
                    $tx->getAccountNumber()
                );
                $this->storeTransaction($transaction);
            }
        }
    }


}