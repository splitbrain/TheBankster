<?php

namespace splitbrain\TheBankster\CLI;

use ORM\EntityManager;
use ORM\QueryBuilder\QueryBuilder;
use splitbrain\phpcli\PSR3CLI;
use splitbrain\TheBankster\Backend\AbstractBackend;
use splitbrain\TheBankster\Container;
use splitbrain\TheBankster\Entity\Account;
use splitbrain\TheBankster\Entity\Rule;
use splitbrain\TheBankster\Entity\Transaction;

class ImportCLI extends PSR3CLI
{

    /**
     * Register options and arguments on the given $options object
     *
     * @param \splitbrain\phpcli\Options $options
     * @return void
     */
    protected function setup(\splitbrain\phpcli\Options $options)
    {
        $options->setHelp('Fetch new transactions and categorize them');
        $options->registerOption('from', 'Import from this date', 'f', 'YYYY-MM-DD');
        $options->registerArgument('account', 'The account to import, leave blank for all', false);
    }

    /**
     * Your main program
     *
     * Arguments and options have been parsed when this is run
     *
     * @param \splitbrain\phpcli\Options $options
     * @return void
     */
    protected function main(\splitbrain\phpcli\Options $options)
    {
        $container = Container::getInstance();
        $container->setLogger($this);
        $db = $container->db;

        $args = $options->getArgs();

        $from = $options->getOpt('from', '');
        if($from) $from = new \DateTime($from);

        /** @var Account $accounts */
        if ($args) {
            $accounts = $db->fetch(Account::class)->where('account', '=', $args[0])->all();
        } else {
            $accounts = $db->fetch(Account::class)->all();
        }
        foreach ($accounts as $account) {
            $class = '\\splitbrain\\TheBankster\\Backend\\' . $account->backend;
            /** @var AbstractBackend $backend */
            $backend = new $class($account->configuration, $account->account);
            $backend->setLogger($this);

            if($from) {
                $last = $from;
            } else {
                $last = $this->getLastUpdate($db, $account->account);
            }
            $this->notice(
                'Importing {account} from {date}',
                [
                    'account' => $account->account,
                    'date' => $last->format('Y-m-d')
                ]
            );

            try {
                $backend->importTransactions($last);
            } catch (\Exception $e) {
                $this->error("Account {acct} threw an Exception:", ['acct' => $account->account]);
                $this->debug($account->account . ': ' . $e->getTraceAsString());
                $this->error($e->getMessage());
            }
        }

        $this->applyRules($db);
    }

    /**
     * When was the last update of the given account?
     *
     * @param EntityManager $db
     * @param $accid
     * @return \DateTime
     */
    protected function getLastUpdate(EntityManager $db, $accid)
    {
        /** @var Transaction $last */
        $last = $db->fetch(Transaction::class)
            ->where('account', '=', $accid)
            ->orderBy('ts', QueryBuilder::DIRECTION_DESCENDING)
            ->one();
        if ($last !== null) {
            // we return 15 days before the last update, because some transactions may occur late
            $dt = $last->getDatetime();
            return $dt->sub(new \DateInterval('P15D'));
        }

        // import from start of current year
        $dt = new \DateTime();
        $year = $dt->format('Y');
        $dt->setDate($year, 1, 1);
        $dt->setTime(0, 0, 1);

        return $dt;
    }

    /**
     * Run rules on all non-categorized transactions
     *
     * @param EntityManager $db
     */
    protected function applyRules(EntityManager $db)
    {
        /** @var Rule[] $rules */
        $rules = $db->fetch(Rule::class)
            ->where('enabled', '=', 1)
            ->all();

        $count = 0;
        foreach ($rules as $rule) {
            /** @var Transaction[] $txs */
            $txs = $rule->matchTransactionsQuery()
                ->where('category_id IS NULL')
                ->all();

            foreach ($txs as $tx) {
                $tx->category_id = $rule->category_id;
                $tx->save();
                $this->notice('Rule {id} matched: ' . (string)$tx, ['id' => $rule->id]);
                $count++;
            }
        }
        if ($count) {
            $this->success('Automatically categorized {num} transactions.', ['num' => $count]);
        }
    }
}