<?php

namespace splitbrain\TheBankster\Controller;

use Slim\Views\Twig;
use splitbrain\TheBankster\Container;

/**
 * Class BaseController
 */
abstract class BaseController
{
    /**
     * @var Container
     */
    protected $container;

    /**
     * @var Twig
     */
    protected $view;

    /**
     * BaseController constructor.
     * @param Container $container
     */
    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->view = $container->view;
    }
}
