<?php

namespace splitbrain\TheBankster\Controller;

use Slim\Exception\NotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;
use splitbrain\TheBankster\Entity\Category;

class CategoryController extends BaseController
{

    public function __invoke(Request $request, Response $response, $args)
    {

        $error = '';

        if (isset($args['id'])) {
            $cat = $this->container->db->fetch(Category::class, $args['id']);
            if ($cat === null) throw new NotFoundException($request, $response);
        } else {
            $cat = new Category();
        }

        if ($request->isPost()) {
            try {
                $this->applyPostData($cat, $request->getParsedBody());
                $cat = $cat->save();
                return $response->withRedirect($this->container->router->pathFor('categories'));
            } catch (\Exception $e) {
                $error = $e->getMessage();
            }
        }

        return $this->view->render($response, 'category.twig',
            [
                'title' => $cat->id ? 'Edit Category ' . $cat->id : 'Create Category',
                'category' => $cat,
                'error' => $error,
            ]
        );
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
        $cats = $this->container->db
            ->fetch(Category::class)
            ->orderBy('top')
            ->orderBy('label')
            ->all();

        return $this->view->render($response, 'categories.twig', [
            'title' => 'Categories',
            'categories' => $cats,
        ]);
    }

    /**
     * Delete this category and redirect back to the list
     *
     * @param Request $request
     * @param Response $response
     * @param $args
     * @return Response
     * @throws NotFoundException
     */
    public function remove(Request $request, Response $response, $args)
    {
        $cat = $this->container->db->fetch(Category::class, $args['id']);
        if($cat === null) {
            throw new NotFoundException($request, $response);
        }
        $this->container->db->delete($cat);
        return $response->withRedirect($this->container->router->pathFor('categories'));
    }

    /**
     * Apply the posted data to the given category
     *
     * @param Category $cat
     * @param $post
     * @throws \Exception
     */
    protected function applyPostData(Category $cat, $post)
    {
        if (!isset($post['top']) || $post['top'] === '') {
            throw new \Exception('You have to provide a Top Level');
        }
        if (!isset($post['label']) || $post['label'] === '') {
            throw new \Exception('You have to provide a Category');
        }

        $cat->top = $post['top'];
        $cat->label = $post['label'];
    }
}