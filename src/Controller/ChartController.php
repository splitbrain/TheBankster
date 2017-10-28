<?php

namespace splitbrain\TheBankster\Controller;

use Slim\Exception\NotFoundException;
use Slim\Http\Request;
use Slim\Http\Response;
use splitbrain\TheBankster\Entity\Category;
use splitbrain\TheBankster\Entity\Transaction;

class ChartController extends BaseController
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
        $params = $request->getQueryParams();

        $top = (isset($args['top'])) ? $args['top'] : '';
        $sub = (isset($args['sub'])) ? $args['sub'] : '';
        $date = (isset($params['date'])) ? $params['date'] : '';

        $txQuery = $transactions = $this->container->db
            ->fetch(Transaction::class)
            ->leftJoinRelated('category');

        if (preg_match('/^\d\d\d\d-\d\d$/', $date)) {
            $txQuery->where('strftime(\'%Y-%m\', ts, \'unixepoch\', \'localtime\')', '=', $date);
            $limit = 0;
            $subtitle = "Transactions for $date";
        } else {
            $limit = 50;
            $subtitle = "Last 50 Transactions";
        }

        if ($sub) {
            $cat = $this->container->db
                ->fetch(Category::class)
                ->where('top', '=', $top)
                ->where('label', '=', $sub)
                ->one();
            if ($cat === null) throw new NotFoundException($request, $response);

            $cols = [$cat->label];
            $data = $this->getSubTopicSums($cat);
            $title = "Category $top / $sub";
            $zoom = false;
            $txQuery->where('categoryID', '=', $cat->id);
        } elseif ($top) {
            $cols = $this->getSubTopicNames($args['top']);
            if (!count($cols)) throw new NotFoundException($request, $response);

            $data = $this->getTopicSums($args['top']);
            $title = "Category $top";
            $zoom = true;
            $txQuery->where('category.top', '=', $top);
        } else {
            $cols = $this->getTopTopicNames();
            $data = $this->getTopTopicSums();
            $title = "Categories";
            $zoom = true;
        }

        $transactions = $txQuery->all($limit);
        $chartData = $this->buildChartArray($data, $cols);

        return $this->view->render($response, 'chart.twig',
            [
                'title' => $title,
                'subtitle' => $subtitle,
                'data' => json_encode($chartData, JSON_PRETTY_PRINT),
                'zoom' => (int)$zoom,
                'transactions' => $transactions,
                'categories' => Category::formList(),
            ]
        );
    }

    /**
     * Creates the array needed to draw the chart
     *
     * @param array $data The data as returned from the SQL query
     * @param array $columns The column headers
     * @return array
     */
    protected function buildChartArray(array $data, array $columns)
    {
        $config = [];
        $rows = [];

        // prepare the data
        array_unshift($columns, '[Sum]');
        foreach ($data as $row) {
            $dt = $row['dt'];
            $cat = $row['cat'];
            $val = round((float)$row['val'], 2);

            // initialize
            if (!isset($config[$dt])) {
                $config[$dt] = array_fill_keys($columns, 0);
            }

            $config[$dt]['[Sum]'] += $val;
            $config[$dt][$cat] = $val;
        }

        // first row is labels
        $firstrow = $columns;
        array_unshift($firstrow, 'Date');
        $rows[] = $firstrow;

        // add remaining rows
        foreach ($config as $dt => $cols) {
            $vals = array_values($cols);
            array_unshift($vals, $dt);
            $rows[] = $vals;
        }

        return $rows;
    }

    /**
     * Column headers for Top Topic Overview
     *
     * @return array
     */
    protected function getTopTopicNames()
    {
        $sql = 'SELECT DISTINCT top FROM category WHERE id != 0 ORDER BY top';
        $list = $this->container->db->getSqlHelper()->queryList($sql);
        $list[] = '[Uncategorized]';
        return $list;
    }

    /**
     * Column headers for Sub Topic View
     *
     * @return array
     */
    protected function getSubTopicNames($top)
    {
        $sql = 'SELECT DISTINCT label FROM category WHERE top = :top ORDER BY top';
        $list = $this->container->db->getSqlHelper()->queryList($sql, [':top' => $top]);
        return $list;
    }

    /**
     * Data for Top Topic Overview
     *
     * @return array
     */
    protected function getTopTopicSums()
    {
        $sql = 'SELECT strftime(\'%Y-%m\', ts, \'unixepoch\', \'localtime\') AS dt,
                       IFNULL(C.top,\'[Uncategorized]\') AS cat,
                       SUM(amount) AS val
                  FROM "transaction" AS T
             LEFT JOIN "category" AS C ON T.category_id = C.id
                 WHERE category_id != 0 OR category_id IS NULL
              GROUP BY C.top, dt
              ORDER BY dt, C.top';
        return $this->container->db->getSqlHelper()->queryAll($sql);
    }

    /**
     * Data for Sub Topic View
     *
     * @return array
     */
    protected function getTopicSums($top)
    {
        $sql = 'SELECT strftime(\'%Y-%m\', ts, \'unixepoch\', \'localtime\') AS dt,
                       C.label AS cat,
                       SUM(amount) AS val
                  FROM "transaction" AS T,
                       "category" AS C
                 WHERE T.category_id = C.id
                   AND C.top = :top
              GROUP BY C.label, dt
              ORDER BY dt, C.label';
        return $this->container->db->getSqlHelper()->queryAll($sql, [':top' => $top]);
    }

    /**
     * @param Category $cat
     * @return array
     */
    protected function getSubTopicSums(Category $cat)
    {
        $sql = 'SELECT strftime(\'%Y-%m\', ts, \'unixepoch\', \'localtime\') AS dt,
                       C.label AS cat,
                       SUM(amount) AS val
                  FROM "transaction" AS T,
                       "category" AS C
                 WHERE T.category_id = C.id
                   AND T.category_id = :catid
              GROUP BY C.label, dt
              ORDER BY dt, C.label';
        return $this->container->db->getSqlHelper()->queryAll($sql, [':catid' => $cat->id]);
    }
}