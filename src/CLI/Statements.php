<?php

namespace splitbrain\TheBankster\CLI;

use splitbrain\phpcli\PSR3CLI;
use splitbrain\TheBankster\Backend\AbstractBackend;
use splitbrain\TheBankster\DataBase;

class Statements extends PSR3CLI
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
        // FIXME we load this data from the database later
        $config = \Spyc::YAMLLoad(__DIR__ . '/../../config.yaml');

        foreach ($config['accounts'] as $accid => $account) {
            $class = '\\splitbrain\\TheBankster\\Backend\\' . $account['backend'];
            /** @var AbstractBackend $backend */
            $backend = new $class($account['config'], $accid);
            $backend->setLogger($this);

            $last = $this->getLastUpdate($accid);
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
     * @param $accid
     * @return \DateTime
     */
    protected function getLastUpdate($accid)
    {
        $db = new DataBase();
        $date = $db->querySingleValue(
            'SELECT datetime
               FROM transactions
              WHERE account = :account
           ORDER BY datetime DESC
              LIMIT 1',
            ['account' => $accid]
        );

        $dt = new \DateTime();
        if ($date !== null) {
            $dt = $dt->setTimestamp($date);
        } else {
            // import from start of current year
            $year = $dt->format('Y');
            $dt->setDate($year, 1, 1);
            $dt->setTime(0, 0, 1);
        }

        return $dt;
    }
}