<?php

namespace splitbrain\TheBankster;

use splitbrain\TheBankster\Controller\CategoryController;
use splitbrain\TheBankster\Controller\ChartController;
use splitbrain\TheBankster\Controller\HomeController;
use splitbrain\TheBankster\Controller\RuleController;


/**
 * PMI Dashboard App
 */
class App
{

    /**
     * The app
     *
     * @var \Slim\App
     */
    protected $app;

    /**
     * Dependency container
     *
     * @var Container
     */
    protected $container;


    /**
     * Application constructor
     */
    public function __construct()
    {
        $c = Container::getInstance();
        $this->app = new \Slim\App($c);
        $this->container = $this->app->getContainer();
    }

    /**
     * Run application
     */
    public function run()
    {

        $this->app->get('/chart/[{top}]', ChartController::class)->setName('chart');

        $this->app->any('/category/del/{id}', CategoryController::class . ':remove')->setName('category-del');
        $this->app->any('/category/[{id}]', CategoryController::class)->setName('category');
        $this->app->any('/categories', CategoryController::class . ':listAll')->setName('categories');

        $this->app->any('/rule/del/{id}', RuleController::class . ':remove')->setName('rule-del');
        $this->app->any('/rule/on/{id}', RuleController::class . ':enable')->setName('rule-on');
        $this->app->any('/rule/[{id}]', RuleController::class)->setName('rule');
        $this->app->any('/rules', RuleController::class . ':listAll')->setName('rules');

        $this->app->get('/', HomeController::class)->setName('home');

        $this->app->run();
    }
}