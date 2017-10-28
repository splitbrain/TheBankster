<?php

namespace splitbrain\TheBankster\Controller;

use Slim\Exception\NotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;
use splitbrain\TheBankster\Entity\Category;
use splitbrain\TheBankster\Entity\Transaction;

class TransactionController extends BaseController
{

    /**
     * Show all uncategorized transactions
     *
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function uncategorized(Request $request, Response $response, $args)
    {
        $transactions = $this->container->db
            ->fetch(Transaction::class)
            ->where('category_id IS NULL')
            ->orderBy('ts', 'DESC')
            ->all();
        
        $breadcrumbs = [
            'Home' => $this->container->router->pathFor('home'),
            'Uncategorized' => $this->container->router->pathFor('uncategorized')
        ];

        return $this->view->render($response, 'transactions.twig', [
            'title' => 'Uncategorized Transactions',
            'transactions' => $transactions,
            'categories' => Category::formList(),
            'breadcrumbs' => $breadcrumbs,
        ]);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return \Psr\Http\Message\ResponseInterface
     * @throws NotFoundException
     */
    public function assign(Request $request, Response $response, $args)
    {
        $tx = $this->container->db->fetch(Transaction::class, $args['txid']);
        if ($tx === null) throw  new NotFoundException($request, $response);

        $cat = $this->container->db->fetch(Category::class, $args['catid']);
        if ($cat === null) throw  new NotFoundException($request, $response);

        $tx->categoryId = $cat->id;
        $tx->save();

        return $response->withJson(true);
    }
}