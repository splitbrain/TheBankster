<?php

namespace splitbrain\TheBankster\Backend;

use splitbrain\TheBankster\Entity\Transaction;

/**
 * Class FinTS
 *
 * Uses the FinTS protocol to access bank transactions
 *
 * @package splitbrain\TheBankster\Backend
 */
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
    public static function configDescription()
    {
        return [
            'url' => [
                'help' => 'The HBCI / FinTS API URL for your bank. See https://www.hbci-zka.de/institute/institut_select.php',
            ],
            'code' => [
                'help' => 'Your bank code (aka Bankleitzahl)',
            ],
            'user' => [
                'help' => 'Your online banking username / alias',
            ],
            'pass' => [
                'help' => 'Your online banking PIN (NOT! the pin of your bank card!)',
                'type' => 'password',
            ],
            'ident' => [
                'optional' => true, 'help' => 'Optional. Your account number if the credentials above give access to multiple accounts',
            ],
        ];
    }

    /** @inheritdoc */
    public function checkSetup()
    {
        $account = $this->identifyAccount();
        return 'Connected successfully to account ' . $account->getAccountNumber();
    }

    /**
     * Get the account to use
     *
     * @return \Fhp\Model\SEPAAccount
     */
    protected function identifyAccount()
    {
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

        return $account;
    }

    /** @inheritdoc */
    public function importTransactions(\DateTime $since)
    {
        $account = $this->identifyAccount();

        $today = new \DateTime();
        $today->setTime(0,0,0);

        // get all the transactions
        $soa = $this->fints->getStatementOfAccount($account, $since, new \DateTime());
        foreach ($soa->getStatements() as $statement) {
            foreach ($statement->getTransactions() as $fintrans) {
                $amount = $fintrans->getAmount();
                if ($fintrans->getCreditDebit() == \Fhp\Model\StatementOfAccount\Transaction::CD_DEBIT) {
                    $amount *= -1;
                }

                $tx = new Transaction();
                $tx->datetime = $fintrans->getBookingDate();
                $tx->amount = $amount;
                $tx->description = join("\n", [
                    $fintrans->getDescription1(),
                    $fintrans->getDescription2(),
                    $fintrans->getBookingText()
                ]);
                $tx->xName = $fintrans->getName();
                $tx->xBank = $fintrans->getBankCode();
                $tx->xAcct = $fintrans->getAccountNumber();

                if ($tx->datetime > $today) {
                    $this->logger->warning('Skipping future transaction '.((string) $tx));
                    continue;
                }

                $this->storeTransaction($tx);
            }
        }
    }


}