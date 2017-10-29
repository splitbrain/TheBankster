<?php

namespace splitbrain\TheBankster\Controller;

use Slim\Exception\NotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;
use splitbrain\TheBankster\Backend\AbstractBackend;
use splitbrain\TheBankster\Entity\Account;

class AccountController extends BaseController
{
    /**
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return \Psr\Http\Message\ResponseInterface
     * @throws NotFoundException
     */
    public function __invoke(Request $request, Response $response, $args)
    {
        $name = $args['account'];
        /** @var Account $account */
        $account = $this->container->db->fetch(Account::class, $name);
        if ($account === null) throw new NotFoundException($request, $response);

        return $this->handleAccount($request, $response, $account, false);
    }


    public function newAccount(Request $request, Response $response, $args)
    {
        $backend = $args['backend'];
        $account = new Account();
        $account->setBackend($backend);

        return $this->handleAccount($request, $response, $account, true);
    }


    public function handleAccount(Request $request, Response $response, Account $account, bool $isnew)
    {
        $configDesc = $account->getConfigurationDescription();
        $backend = $account->backend;
        if ($isnew) {
            $title = "New $backend Account";
            $self = $this->container->router->pathFor('account-new', ['backend' => $backend]);
        } else {
            $title = "Edit $backend Account " . $account->account;
            $self = $this->container->router->pathFor('account', ['account' => $account->account]);
        }

        $error = '';
        if ($request->isPost()) {
            $post = $request->getParsedBody();

            try {
                if ($isnew) $account->account = $post['account'];
                $account->configuration = $post['conf'];
                $account = $account->save();
                return $response->withRedirect(
                    $this->container->router->pathFor('account', ['account' => $account->account])
                );
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        $breadcrumbs = [
            'Home' => $this->container->router->pathFor('home'),
            'Accounts' => $this->container->router->pathFor('accounts'),
            $title => $self,
        ];

        return $this->view->render($response, 'account.twig',
            [
                'title' => $title,
                'breadcrumbs' => $breadcrumbs,
                'configDesc' => $configDesc,
                'error' => $error,
                'account' => $account,
                'isnew' => $isnew,
                'self' => $self,
            ]
        );
    }

    public function listAll(Request $request, Response $response)
    {
        $accounts = $this->container->db->fetch(Account::class)->orderBy('account')->all();
        $backends = Account::listBackends();

        $breadcrumbs = [
            'Home' => $this->container->router->pathFor('home'),
            'Accounts' => $this->container->router->pathFor('accounts'),
        ];

        return $this->view->render($response, 'accounts.twig',
            [
                'title' => 'Accounts',
                'breadcrumbs' => $breadcrumbs,
                'backends' => $backends,
                'accounts' => $accounts,
            ]
        );
    }


}