<?php

namespace splitbrain\TheBankster\Controller;

use Slim\Http\Request;
use Slim\Http\Response;
use splitbrain\TheBankster\Entity\Category;
use splitbrain\TheBankster\Entity\Transaction;

class HomeController extends BaseController
{
    /**
     * @param Request $request
     * @param Response $response
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function __invoke(Request $request, Response $response)
    {
        $beginOfMonth = strtotime('first day of this month 00:00');
        $txs = $this->container->db
            ->fetch(Transaction::class)
            ->where('ts', '>', $beginOfMonth)
            ->orderBy('ts', 'DESC')
            ->all();

        $breadcrumbs = [
            'Home' => $this->container->router->pathFor('home'),
        ];

        return $this->view->render($response, 'home.twig',
            [
                'title' => 'Home',
                'transactions'=>$txs,
                'categories' => Category::formList(),
                'breadcrumbs' => $breadcrumbs,
            ]
        );
    }
}