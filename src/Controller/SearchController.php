<?php

namespace splitbrain\TheBankster\Controller;

use Slim\Http\Request;
use Slim\Http\Response;
use splitbrain\TheBankster\Entity\Account;
use splitbrain\TheBankster\Entity\Category;
use splitbrain\TheBankster\Entity\Transaction;

class SearchController extends BaseController
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
            'Search' => $this->container->router->pathFor('search'),
        ];

        if ($request->isPost()) {
            $txs = $this->query($request->getParsedBody());
        } else {
            $txs = [];
        }

        return $this->view->render($response, 'search.twig',
            [
                'title' => 'Advanced Search',
                'transactions' => $txs,
                'accounts' => Account::formList(),
                'categories' => array_merge(['' => ['' => '']], Category::formList()),
                'breadcrumbs' => $breadcrumbs,
                'query' => $request->getParsedBody(),
            ]
        );
    }

    /**
     * Execute a query
     *
     * @param array $query The search parameters
     * @return Transaction[]
     * @throws \ORM\Exception should not happen
     */
    protected function query($query)
    {
        $qb = $this->container->db->fetch(Transaction::class);

        if ($query['account'] !== '') $qb = $qb->where('account', '=', $query['account']);
        if ($query['categoryId'] !== '') $qb = $qb->where('category_id', '=', $query['categoryId']);
        if ($query['min'] !== '') $qb = $qb->where('amount', '>=', $query['min']);
        if ($query['max'] !== '') $qb = $qb->where('amount', '<=', $query['max']);
        try {
            if ($query['from'] !== '') $qb = $qb->where('ts', '>=', (new \DateTime($query['from']))->getTimestamp());
            if ($query['until'] !== '') $qb = $qb->where('ts', '<=', (new \DateTime($query['until']))->getTimestamp());
        } catch (\Exception $ignored) {
            // ignore bad times
        }

        foreach (['account', 'description', 'x_name', 'x_bank', 'x_acct'] as $key) {
            if ($query[$key] === '') continue;
            $qb = $qb->where($key, 'LIKE', '%' . $query[$key] . '%');
        }

        return $qb->orderBy('ts', 'DESC')->all();
    }

}
