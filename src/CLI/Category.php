<?php

namespace splitbrain\TheBankster\CLI;

use splitbrain\phpcli\Colors;
use splitbrain\phpcli\Options;
use splitbrain\phpcli\TableFormatter;

class Category extends \splitbrain\phpcli\PSR3CLI
{

    /**
     * Register options and arguments on the given $options object
     *
     * @param Options $options
     * @return void
     */
    protected function setup(Options $options)
    {
        $options->setHelp('Manage categories');

        $options->registerCommand('list', 'Show all the current categories');

        $options->registerCommand('add', 'Add a new category');
        $options->registerArgument('top', 'The top level category name', true, 'add');
        $options->registerArgument('cat', 'The category name', true, 'add');

        $options->registerCommand('del', 'Delete a category identified by it\'s ID');
        $options->registerArgument('id', 'The category\'s ID', true, 'del');

        $options->registerCommand('change', 'Change a category');
        $options->registerArgument('id', 'The category\'s ID', true, 'change');
        $options->registerArgument('top', 'The top level category name', true, 'change');
        $options->registerArgument('cat', 'The category name', true, 'change');

        $options->registerCommand('rename', 'Rename a top level category');
        $options->registerArgument('old', 'The old top level category name', true, 'rename');
        $options->registerArgument('new', 'The new top level category name', true, 'rename');
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
        $cat = new \splitbrain\TheBankster\Category();
        $args = $options->getArgs();

        switch ($options->getCmd()) {
            case 'list':
                $tf = new TableFormatter($this->colors);
                echo $tf->format(
                    ['10', '50', '*'],
                    ['ID', 'Top Level', 'Name'],
                    [Colors::C_BROWN, Colors::C_BROWN, Colors::C_BROWN]
                );

                foreach ($cat->getAll() as $item) {
                    echo $tf->format(
                        ['10', '50', '*'],
                        [$item['cat'], $item['top'], $item['label']]
                    );
                }
                break;

            case 'add':
                $id = $cat->add($args[0], $args[1]);
                $this->success('Category {id} added.', ['id' => $id]);
                break;

            case 'change':
                $ok = $cat->update($args[0], $args[1], $args[2]);
                if ($ok) {
                    $this->success('Category changed');
                } else {
                    $this->error('No such category');
                }
                break;

            case 'del';
                $ok = $cat->del($args[0]);
                if ($ok) {
                    $this->success('Category deleted');
                } else {
                    $this->error('No such category');
                }
                break;

            case 'rename':
                $ok = $cat->renameTop($args[0], $args[1]);
                if ($ok) {
                    $this->success('Top level renamed for {num} categories', ['num' => $ok]);
                } else {
                    $this->error('No matching top level found');
                }
                break;

            default:
                echo $options->help();
        }
    }
}