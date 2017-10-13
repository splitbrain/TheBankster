<?php

namespace splitbrain\TheBankster\CLI;

use splitbrain\TheBankster\Backend\AbstractBackend;

class Statements extends \splitbrain\phpcli\CLI
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

        foreach($config['accounts'] as $accid => $account) {

            $class = '\\splitbrain\\TheBankster\\Backend\\'.$account['backend'];
            /** @var AbstractBackend $backend */
            $backend = new $class($account['config']);

            $transactions = $backend->getTransactions(new \DateTime('2017-10-01'));

            foreach($transactions as $tx) {
                echo "--------------\n";
                echo "(".$tx->getXName().")\n";
                echo $tx->getDescription();
                echo "\n----\n";
                echo $tx->getCleanDescription();
                echo "\n";
            }
        }




    }
}