<?php

namespace splitbrain\TheBankster\CLI;

use splitbrain\phpcli\Colors;
use splitbrain\phpcli\Options;
use splitbrain\phpcli\TableFormatter;
use splitbrain\TheBankster\Model\Category;

class CategoryCLI extends \splitbrain\phpcli\PSR3CLI
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
        $args = $options->getArgs();

        switch ($options->getCmd()) {
            case 'list':
                $tf = new TableFormatter($this->colors);
                echo $tf->format(
                    ['10', '50', '*'],
                    ['ID', 'Top Level', 'Name'],
                    [Colors::C_BROWN, Colors::C_BROWN, Colors::C_BROWN]
                );

                foreach (Category::loadAll() as $item) {
                    echo $tf->format(
                        ['10', '50', '*'],
                        [$item['id'], $item['top'], $item['label']]
                    );
                }
                break;

            case 'add':
                $cat = new Category([
                    'top' => $args[0],
                    'label' => $args[1]
                ]);
                $id = $cat->save();
                $this->success('Category {id} added.', ['id' => $id]);
                break;

            case 'change':
                $cat = Category::load($args[0]);
                $cat->setData([
                    'top' => $args[1],
                    'label' => $args[2]
                ]);
                $cat->save();
                $this->success('Category changed');
                break;

            case 'del';
                $cat = Category::load($args[0]);
                $cat->delete();
                $this->success('Category deleted');
                break;

            case 'rename':
                $ok = Category::renameTop($args[0], $args[1]);
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