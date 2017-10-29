<?php

namespace splitbrain\TheBankster\CLI;

use ORM\EntityManager;
use ORM\QueryBuilder\QueryBuilder;
use splitbrain\phpcli\PSR3CLI;
use splitbrain\TheBankster\Backend\AbstractBackend;
use splitbrain\TheBankster\Container;
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
        $options->setHelp('fetch the statements');
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
}