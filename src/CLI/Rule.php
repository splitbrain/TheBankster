<?php

namespace splitbrain\TheBankster\CLI;

use splitbrain\phpcli\Colors;
use splitbrain\phpcli\Options;
use splitbrain\phpcli\PSR3CLI;
use splitbrain\phpcli\TableFormatter;

class Rule extends PSR3CLI
{

    /**
     * Register options and arguments on the given $options object
     *
     * @param Options $options
     * @return void
     */
    protected function setup(Options $options)
    {
        $options->setHelp('Manage categorization rules');

        $options->registerCommand('list', 'List all available rules');

        $options->registerCommand('add', 'Add a new rule');
        $options->registerArgument('name', 'Name for the new rule', true, 'add');
        $options->registerOption('account', 'Only apply this rule to this account', null, 'account', 'add');
        $options->registerOption('debit', 'Only match spending (-1) or income (+1)', null, '-1|+1', 'add');
        $options->registerOption('desc', 'Match this against the transaction\'s description', null, 'match', 'add');
        $options->registerOption('xname', 'Match this against the transaction issuer\'s name', null, 'match', 'add');
        $options->registerOption('xbank', 'Match this against the transaction issuer\'s bank', null, 'match', 'add');
        $options->registerOption('xaccount', 'Match this against the transaction issuer\'s account', null, 'match', 'add');

        $options->registerCommand('change', 'Change an existing rule');
        $options->registerArgument('id', 'ID of the rule to change', true, 'change');
        $options->registerOption('account', 'Only apply this rule to this account', null, 'account', 'change');
        $options->registerOption('debit', 'Only match spending (-1) or income (+1)', null, '-1|+1', 'change');
        $options->registerOption('desc', 'Match this against the transaction\'s description', null, 'match', 'change');
        $options->registerOption('xname', 'Match this against the transaction issuer\'s name', null, 'match', 'change');
        $options->registerOption('xbank', 'Match this against the transaction issuer\'s bank', null, 'match', 'change');
        $options->registerOption('xaccount', 'Match this against the transaction issuer\'s account', null, 'match', 'change');
    }

    /**
     * Your main program
     *
     * Arguments and options have been parsed when this is run
     *
     * @param Options $options
     * @return void
     */
    protected function main(Options $options)
    {

        $args = $options->getArgs();

        switch ($options->getCmd()) {
            case 'list':
                $rules = \splitbrain\TheBankster\Rule::loadAllRules();
                $tf = new TableFormatter($this->colors);

                echo $tf->format(
                    [5, 25, '*'],
                    ['ID', 'Name', 'Matches'],
                    [Colors::C_BROWN, Colors::C_BROWN, Colors::C_BROWN]
                );

                foreach ($rules as $rule) {
                    echo $tf->format(
                        [5, 25, '*'],
                        [
                            $rule->getId(),
                            $rule->getName(),
                            $rule->getDisplayMatches(),
                        ]
                    );
                }

                break;

            case 'add':
                $rule = new \splitbrain\TheBankster\Rule($args[0]);
                $this->applyOptions($rule, $options);
                $id = $rule->save();
                $this->success('Saved rule {id}', ['id' => $id]);
                break;

            case 'change':
                $rule = \splitbrain\TheBankster\Rule::loadFromDB($args[0]);
                $this->applyOptions($rule, $options);
                $rule->save();
                $this->success('Updated rule');
                break;

            default:
                echo $options->help();
        }
    }

    /**
     * Apply the given options to the given rule
     *
     * @param \splitbrain\TheBankster\Rule $rule
     * @param Options $options
     * @throws \Exception
     */
    protected function applyOptions($rule, $options)
    {
        $ok = false;
        if ($options->getOpt('account') !== false) {
            $rule->matchAccount($options->getOpt('account'));
            $ok = true;
        }
        if ($options->getOpt('debit') !== false) {
            $rule->matchDebit($options->getOpt('debit'));
            $ok = true;
        }
        if ($options->getOpt('desc') !== false) {
            $rule->matchDescription($options->getOpt('desc'));
            $ok = true;
        }
        if ($options->getOpt('xname') !== false) {
            $rule->matchXName($options->getOpt('xname'));
            $ok = true;
        }
        if ($options->getOpt('xbank') !== false) {
            $rule->matchXBank($options->getOpt('xbank'));
            $ok = true;
        }
        if ($this->options->getOpt('xaccount') !== false) {
            $rule->matchXAccount($this->options->getOpt('xaccount'));
            $ok = true;
        }

        if (!$ok) throw new \Exception('You ned to provide at least one option');
    }
}