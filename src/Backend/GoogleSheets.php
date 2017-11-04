<?php

namespace splitbrain\TheBankster\Backend;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\RequestOptions;
use splitbrain\phpcli\Exception;
use splitbrain\TheBankster\Entity\Transaction;

/**
 * Class GoogleSheets
 *
 * Imports transactions from a Google Sheets document. The document's access has to
 * configured to allow "Everyone with the link" to "View"
 *
 * @package splitbrain\TheBankster\Backend
 */
class GoogleSheets extends AbstractBackend
{

    protected $docID;
    protected $jar;
    protected $client;

    /**
     * GoogleSheets constructor.
     * @param array $config
     * @param string $accountid
     */
    public function __construct($config, $accountid)
    {
        parent::__construct($config, $accountid);
        $this->docID = $config['docID'];

        $this->jar = new CookieJar();
        $this->client = new Client([
            'cookies' => $this->jar,
            RequestOptions::HEADERS => [
                'Upgrade-Insecure-Requests' => '1',
                'Cache-Control' => 'max-age=0',
            ],
            RequestOptions::ALLOW_REDIRECTS => [
                'max' => 0
            ],
        ]);
    }

    /** @inheritdoc */
    public static function configDescription()
    {
        return [
            'docID' => [
                'help' => 'The ID of the spreadsheet',
            ],
            'datetimeColumn' => [
                'help' => 'The letter of the column containing the dates. This column has to be formatted as a date in your spreadsheet!',
            ],
            'amountColumn' => [
                'help' => 'The letter of the column containing the amounts.',
            ],
            'descriptionColumn' => [
                'help' => 'The letters of the column containing the amounts. Separate multiple with commas, they will be concatenated',
            ],
            'xNameColumn' => [
                'help' => 'The letter of the column containing the issuer\'s name. Optional.',
                'optional' => true,
            ],
            'xBankColumn' => [
                'help' => 'The letter of the column containing the issuer\'s bank. Optional.',
                'optional' => true,
            ],
            'xAcctColumn' => [
                'help' => 'The letter of the column containing the issuer\'s account. Optional.',
                'optional' => true,
            ],
        ];
    }

    /** @inheritdoc */
    public function checkSetup()
    {
        $select = $this->getSelectColumns();
        $columns = array_keys($select);
        $sel = join(',', $columns);
        $query = "SELECT $sel LIMIT 1";

        $cols = $this->queryData($query, 'cols');

        $info = "Access okay. Importing columns:\n";
        foreach ($cols as $col) {
            $info .= $col['id'] . ': ' . $col['label'] . "\n";
        }
        return $info;
    }


    /** @inheritdoc */
    public function importTransactions(\DateTime $since)
    {
        $select = $this->getSelectColumns();
        $columns = array_keys($select);
        $fields = array_values($select);

        $dt = $since->format("Y-m-d H:i:s");
        $sel = join(',', $columns);
        $query = "SELECT $sel WHERE C > DATETIME '$dt'";

        $rows = $this->queryData($query);
        foreach ($rows as $row) {
            $tx = new Transaction();
            foreach ($row['c'] as $idx => $cell) {
                // each cell may be used in multiple fields
                foreach ($fields[$idx] as $field) {
                    if ($field == 'datetime') {
                        $tx->$field = $this->parseDate($cell['v']);
                    } elseif ($field == 'amount') {
                        $tx->$field = (float)$cell['v'];
                    } else {
                        // append to field
                        $tx->$field .= "\n" . $cell['v'];
                        $tx->$field = trim($tx->$field);
                    }
                }
            }
            $this->storeTransaction($tx);
        }
    }

    /**
     * Send a query to Google Sheets and return the rows
     *
     * @param string $query
     * @return array mixed
     * @throws \Exception if any error occurs
     */
    protected function queryData($query, $sub = 'rows')
    {
        $this->logger->info("Google Query: $query");
        $query = rawurlencode($query);
        $url = 'https://docs.google.com/spreadsheets/d/' . $this->docID . '/gviz/tq?tqx=out:json&tq=' . $query;
        $this->logger->debug("Google Query URL: $url");

        $response = $this->client->get($url);
        if ($response->getStatusCode() != 200) {
            throw new \Exception('Failed to access Google sheet. Make sure it\'s link public.');
        }

        $body = (string)$response->getBody();
        if (!substr($body, 0, 7) == '/*O_o*/') {
            $this->logger->debug($body);
            throw new \Exception('Did not get a JSON response from Google');
        }
        $body = preg_replace('/^.*?\(/s', '', $body);
        $body = preg_replace('/\);$/s', '', $body);
        $data = json_decode($body, true);
        if (!$data) {
            $this->logger->debug($body);
            throw new \Exception('Failed to decode JSON response from Google');
        }

        if ($data['status'] != 'ok') {
            $msg = $data['errors'][0]['message'];
            $error = print_r($data['errors'], true);
            $this->logger->debug($error);
            throw new \Exception("Got error response from Google Sheets: $msg");
        }

        return $data['table'][$sub];
    }


    /**
     * Parses Google's Date(*) format into a datetime
     *
     * @param string $date
     * @return \DateTime
     * @throws Exception
     */
    protected function parseDate($date)
    {
        if (substr($date, 0, 5) != 'Date(') throw new Exception("Failed to parse Date $date");
        $date = substr($date, 5, -1);
        $parts = explode(',', $date);

        $dt = new \DateTime();
        $dt->setDate($parts[0], $parts[1] + 1, $parts[2]);
        if (count($parts) == 6) {
            $dt->setTime($parts[3], $parts[4], $parts[5]);
        }

        return $dt;
    }

    /**
     * Figure out which columns to select and into which fields their data goes
     *
     * @return array[]
     */
    protected function getSelectColumns()
    {
        $select = [];

        foreach ($this->config as $key => $columns) {
            if (substr($key, -6) !== 'Column') continue;
            if (trim($columns) === '') continue;
            $field = substr($key, 0, -6);

            $columns = explode(',', $columns);
            $columns = array_map('trim', $columns);
            foreach ($columns as $col) {
                if (!isset($select[$col])) $select[$col] = [];
                $select[$col][] = $field;
            }
        }

        return $select;
    }
}