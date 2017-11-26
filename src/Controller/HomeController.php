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
        $breadcrumbs = [
            'Home' => $this->container->router->pathFor('home'),
        ];

        $query = $request->getQueryParam('q');
        if ($query) {
            $txs = $this->search($query);
            $breadcrumbs["🔍 $query"] = $this->container->router->pathFor('home', [], ['q' => $query]);
        } else {
            $txs = $this->thisMonth();
        }

        return $this->view->render($response, 'home.twig',
            [
                'title' => 'Home',
                'transactions' => $txs,
                'categories' => Category::formList(),
                'breadcrumbs' => $breadcrumbs,
                'query' => $query,
            ]
        );
    }

    /**
     * Search for a transaction
     *
     * @param $query
     * @return Transaction[]
     */
    protected function search($query)
    {
        return $this->container->db
            ->fetch(Transaction::class)
            ->where('description', 'LIKE', "%$query%")
            ->orWhere('x_name', 'LIKE', "%$query%")
            ->orWhere('x_bank', 'LIKE', "%$query%")
            ->orderBy('ts', 'DESC')
            ->all();
    }

    /**
     * Get this month's transactions
     *
     * @return Transaction[]
     */
    protected function thisMonth()
    {
        $beginOfMonth = strtotime('first day of this month 00:00');
        return $this->container->db
            ->fetch(Transaction::class)
            ->where('ts', '>', $beginOfMonth)
            ->orderBy('ts', 'DESC')
            ->all();
    }
}