<?php

namespace splitbrain\TheBankster\CLI;

use ORM\EntityManager;
use ORM\QueryBuilder\QueryBuilder;
use splitbrain\phpcli\PSR3CLI;
use splitbrain\TheBankster\Backend\AbstractBackend;
use splitbrain\TheBankster\Container;
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

        // FIXME we load this data from the database later
        $config = $container->settings;

        foreach ($config['accounts'] as $accid => $account) {
            $class = '\\splitbrain\\TheBankster\\Backend\\' . $account['backend'];
            /** @var AbstractBackend $backend */
            $backend = new $class($account['config'], $accid);
            $backend->setLogger($this);

            $last = $this->getLastUpdate($db, $accid);
            $this->notice(
                'Importing {account} from {date}',
                [
                    'account' => $account['label'],
                    'date' => $last->format('Y-m-d')
                ]
            );
            $backend->importTransactions($last);
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
        if ($last !== null) return $last->getDatetime();

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
        if($count) {
            $this->success('Automatically categorized {num} transactions.', ['num' => $count]);
        }
    }
}