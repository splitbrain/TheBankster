<?php

namespace splitbrain\TheBankster\Backend;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\RequestOptions;
use splitbrain\TheBankster\Transaction;

class TargoBank extends AbstractBackend
{
    protected $jar;
    protected $client;

    /** @inheritdoc */
    public function __construct($config, $accountid)
    {
        parent::__construct($config, $accountid);

        $this->jar = new CookieJar();

        $this->client = new Client([
            'cookies' => $this->jar,
            RequestOptions::HEADERS => [
                'User-Agent' => 'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36',
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8',
                'Accept-Language' => 'en-US,en;q=0.8,de;q=0.6',
                'Upgrade-Insecure-Requests' => '1',
                'Cache-Control' => 'max-age=0',
            ],
            RequestOptions::ALLOW_REDIRECTS => [
                'max' => 10,        // allow at most 10 redirects.
                'strict' => true,      // use "strict" RFC compliant redirects.
                'referer' => true,      // add a Referer header
                'track_redirects' => true,
            ]
        ]);
    }

    /** @inheritdoc */
    public function importTransactions(\DateTime $since)
    {

        if (!$this->login($this->config['user'], $this->config['pass'])) {
            throw new \Exception('Failed to log in to TargoBank');
        }

        $body = $this->getStatementPage();
        \phpQuery::newDocumentHTML((string)$body);
        $table = pq('table[summary="Activity Details"]');

        if ($table->length !== 1) {
            throw new \Exception('Failed to parse account statements');
        }

        $transactions = array();

        $trs = $table->find('tr');
        foreach ($trs as $tr) {
            $tds = pq($tr)->find('td');
            if ($tds->length !== 6) continue;

            $transactions[] = new Transaction(
                $this->fixDate($tds->get(0)->textContent),
                $this->fixAmount($tds->get(4)->textContent),
                join("\n", [
                    $tds->get(1)->textContent, // details
                    $tds->get(2)->textContent, // city
                    $tds->get(3)->textContent, // country
                    $tds->get(5)->textContent, // foreign currency
                ])
            );
        }

        print_r($transactions);
        return $transactions;
    }

    /**
     * Normalize dates
     *
     * @param string $date
     * @return string
     */
    protected function fixDate($date)
    {
        return join('-', array_reverse(explode('/', $date)));
    }

    /**
     * Normalize amount
     *
     * @param string $amt
     * @return float
     */
    protected function fixAmount($amt)
    {
        $amt = preg_replace('/[^0-9,\\.\\-]+/', '', $amt);
        $amt = str_replace(',', '.', $amt);
        return floatval($amt);
    }

    /**
     * Get the HTML of the statement page
     *
     * @return string
     * @throws \Exception
     */
    protected function getStatementPage()
    {
        if (!$this->login($this->config['user'], $this->config['pass'])) {
            throw new \Exception('Failed to log in to TargoBank');
        }

        $response = $this->client->get('https://www.targobank.de/de/banque/mouvements_icard.cgi');
        if ($response->getStatusCode() !== 200) {
            throw new \Exception('Got wrong status code from TargoBank');
        }

        return (string)$response->getBody();
    }

    /**
     * Login to the web interface to open a session
     *
     * @param string $user
     * @param string $pass
     * @return bool true if the login succeeded
     */
    protected function login($user, $pass)
    {
        $this->client->get('https://www.targobank.de/');
        $url = 'https://www.targobank.de/de/identification/login.cgi';
        $this->client->post(
            $url,
            [
                RequestOptions::FORM_PARAMS => [
                    '_cm_user' => $user,
                    'flag' => 'password',
                    '_charset_' => 'windows-1252',
                    '_cm_pwd' => $pass,
                    'submit.x' => '74',
                    'submit.y' => '16',
                ],
                RequestOptions::HEADERS => [
                    'Referer' => $url
                ]
            ]
        );
        return ($this->jar->getCookieByName('IdSes') !== null);
    }
}