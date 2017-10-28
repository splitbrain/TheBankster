<?php

namespace splitbrain\TheBankster\Controller;

use Slim\Exception\NotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;
use splitbrain\TheBankster\Entity\Category;
use splitbrain\TheBankster\Entity\Rule;

class RuleController extends BaseController
{

    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     * @return \Psr\Http\Message\ResponseInterface|static
     * @throws NotFoundException
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $error = '';

        if (isset($args['id'])) {
            $rule = $this->container->db->fetch(Rule::class, $args['id']);
            if ($rule === null) throw new NotFoundException($request, $response);
        } else {
            $rule = new Rule();
        }

        if ($request->isPost()) {
            try {
                $this->applyPostData($rule, $request->getParsedBody());
                $rule = $rule->save();
                return $response->withRedirect($this->container->router->pathFor('rule', ['id' => $rule->id]));
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        if ($rule->id) {
            $transactions = $rule->matchTransactionsQuery()->orderBy('ts', 'DESC')->all();
        } else {
            $transactions = [];
        }

        return $this->view->render($response, 'rule.twig', [
            'title' => ($rule->id) ? 'Edit Rule ' . $rule->id : 'Add a Rule',
            'accounts' => $this->getAccounts(),
            'categories' => $this->getCategories(),
            'rule' => $rule,
            'error' => $error,
            'transactions' => $transactions,
        ]);
    }

    /**
     * List all available rules
     *
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return \Psr\Http\Message\ResponseInterface
     */
    public function listAll(Request $request, Response $response, $args)
    {
        $rules = $this->container->db
            ->fetch(Rule::class)
            ->joinRelated('category')
            ->orderBy('category.top')
            ->orderBy('category.label')
            ->all();

        return $this->view->render($response, 'rules.twig', [
            'title' => 'Rules',
            'rules' => $rules,
        ]);
    }

    /**
     * Delete this rule and redirect back to the list
     *
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     * @throws NotFoundException
     */
    public function remove(Request $request, Response $response, $args)
    {
        $rule = $this->container->db->fetch(Rule::class, $args['id']);
        if ($rule === null) {
            throw new NotFoundException($request, $response);
        }
        $this->container->db->delete($rule);
        return $response->withRedirect($this->container->router->pathFor('rules'));
    }

    /**
     * Enable this rule and (re-)apply it
     *
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     * @throws NotFoundException
     */
    public function enable(Request $request, Response $response, $args)
    {
        /** @var Rule $rule */
        $rule = $this->container->db->fetch(Rule::class, $args['id']);
        if ($rule === null) {
            throw new NotFoundException($request, $response);
        }

        $rule->enabled = 1;
        $rule = $rule->save();

        $query = $rule->matchTransactionsQuery();
        $txs = $query->all();
        foreach ($txs as $tx) {
            $tx->categoryId = $rule->category_id;
            $tx->save();
        }

        return $response->withRedirect($this->container->router->pathFor('rule', ['id' => $rule->id]));
    }

    /**
     * Adds the post data to the given rule
     *
     * @param Rule $rule
     * @param array $post
     * @throws \Exception
     */
    protected function applyPostData(Rule $rule, array $post)
    {
        $ok = false;

        foreach (['account', 'debit', 'description', 'xName', 'xBank', 'xAcct'] as $key) {
            if (isset($post[$key])) {
                $rule->$key = $post[$key];
                if ($post[$key] !== '') $ok = true;
            }
        }
        if (isset($post['categoryId'])) {
            $rule->categoryId = $post['categoryId'];
        }
        if (!$ok) throw new \Exception('You need to provide at least one matching rule');
    }

    /**
     * Get list of available accounts
     *
     * @return array
     */
    protected function getAccounts()
    {
        $accounts = [];
        $accounts[''] = '';

        foreach ($this->container->settings['accounts'] as $key => $info) {
            $accounts[$key] = $info['label'];
        }
        return $accounts;
    }

    /**
     * Get a nested list of available categories
     *
     * @return array
     */
    protected function getCategories()
    {
        $data = [];
        $cats = $this->container->db->fetch(Category::class)->orderBy('top')->orderBy('label')->all();
        foreach ($cats as $cat) {
            if (!isset($data[$cat->top])) $data[$cat->top] = [];
            $data[$cat->top][$cat->id] = $cat->label;
        }
        return $data;
    }
}