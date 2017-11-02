<?php

namespace splitbrain\TheBankster;

use Slim\Http\Request;
use Slim\Http\Response;
use splitbrain\TheBankster\Entity\Category;
use splitbrain\TheBankster\Entity\Transaction;

class ErrorHandler
{
    protected $container;
    protected $view;

    public function __construct(Container $container)
    {
        $this->container = $container;
        $this->view = $container->view;
    }

    /**
     * Default Error Handler
     *
     * @param Request $request
     * @param Response $response
     * @param \Exception|\Error $error
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke(Request $request, Response $response, $error)
    {
        if($this->container->settings['displayErrorDetails']) {
            $details = get_class($error)."\n"
                . $error->getFile().':'.$error->getLine()."\n"
                . $error->getTraceAsString();
        } else {
            $details = 'Enable displayErrorDetails for more details';
        }

        $breadcrumbs = [
            'Home' => $this->container->router->pathFor('home'),
            'Error' => '#'
        ];

        return $this->view->render($response, 'error.twig',
            [
                'title' => 'Error',
                'breadcrumbs' => $breadcrumbs,
                'severity' => 'danger',
                'error' => $error->getMessage(),
                'details' => $details,
                'info' => 'A fatal error occured. This shouldn\'t happen, but did. If you figure out what went wrong,
                send a pull request please.'
            ]
        )->withStatus(500);
    }

    /**
     * 404 Error Handler
     *
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function notFound(Request $request, Response $response) {
        $breadcrumbs = [
            'Home' => $this->container->router->pathFor('home'),
            'Not Found' => '#'
        ];

        return $this->view->render($response, 'error.twig',
            [
                'title' => 'Not Found',
                'breadcrumbs' => $breadcrumbs,
                'severity' => 'warning',
                'error' => 'The resource you\'re looking for does not exist.',
            ]
        )->withStatus(404);
    }
}
