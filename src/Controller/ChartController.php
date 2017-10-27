<?php

namespace splitbrain\TheBankster\Controller;

use Slim\Http\Request;
use Slim\Http\Response;

class ChartController extends BaseController
{

    public function __invoke(Request $request, Response $response, $args)
    {

        if (isset($args['top'])) {
            $data = $this->getSubTopicSums($args['top']);
            $cols = $this->getSubTopicNames($args['top']);
        } else {
            $data = $this->getTopTopicSums();
            $cols = $this->getTopTopicNames();
        }

        $rows = $this->buildArray($data, $cols);

        $this->view->render($response, 'chart.twig',
            [
                'title' => 'Home',
                'data' => json_encode($rows, JSON_PRETTY_PRINT)
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
    protected function buildArray(array $data, array $columns)
    {
        $config = [];
        $rows = [];

        // prepare the data
        array_unshift($columns, 'Sum');
        foreach ($data as $row) {
            $dt = $row['dt'];
            $cat = $row['cat'];
            $val = (float)$row['val'];

            // initialize
            if (!isset($config[$dt])) {
                $config[$dt] = array_fill_keys($columns, 0);
            }

            $config[$dt]['Sum'] += $val;
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
        $list[] = 'Uncategorized';
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
        $sql = 'SELECT strftime(\'%Y-%m\', ts, \'unixepoch\') AS dt,
                       IFNULL(C.top,\'Uncategorized\') AS cat,
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
    protected function getSubTopicSums($top)
    {
        $sql = 'SELECT strftime(\'%Y-%m\', ts, \'unixepoch\') AS dt,
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
}