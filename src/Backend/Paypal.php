<?php

namespace splitbrain\TheBankster\Backend;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use splitbrain\TheBankster\CurrencyConvert;
use splitbrain\TheBankster\Transaction;

/**
 * Class Paypal
 *
 * This uses the "classic" NVP API to fetch transaction details from Paypal. These infos
 * are currently not available from the modern REST API.
 *
 * We convert all foreign currencies to the default currency. Alternatively we'd need to have different
 * ledgers for different
 *
 * Use the follwoing link to create the needed credentials:
 * @link https://www.paypal.com/de/cgi-bin/webscr?cmd=_profile-api-add-direct-access-show
 *
 * @package splitbrain\TheBankster\Backend
 */
class Paypal extends AbstractBackend
{
    /** @var Client HTTP Client */
    protected $client;
    /** @var CurrencyConvert */
    protected $convert;

    /** @inheritdoc */
    public function __construct($config)
    {
        parent::__construct($config);

        $this->client = new Client([
            RequestOptions::ALLOW_REDIRECTS => [
                'max' => 10,        // allow at most 10 redirects.
                'strict' => true,      // use "strict" RFC compliant redirects.
                'referer' => true,      // add a Referer header
                'track_redirects' => true,
            ]
        ]);

        $this->convert = new CurrencyConvert();
    }


    /** @inheritdoc */
    public function getTransactions(\DateTime $since)
    {
        return $this->call($since);
    }


    /**
     * Fetch transactions from Paypal
     *
     * @link https://developer.paypal.com/webapps/developer/docs/classic/api/merchant/TransactionSearch_API_Operation_NVP/
     *
     * @param \DateTime $since
     * @throws \Exception
     */
    protected function call(\DateTime $since)
    {
        $response = $this->client->post(
            'https://api-3t.paypal.com/nvp',
            [
                RequestOptions::FORM_PARAMS => [
                    'PWD' => $this->config['pass'],
                    'USER' => $this->config['user'],
                    'SIGNATURE' => $this->config['signature'],
                    'VERSION' => 94,
                    'METHOD' => 'TransactionSearch',
                    'STARTDATE' => $since->setTimezone(new \DateTimeZone('UTC'))->format('c'),
                ]
            ]
        );

        $fields = [];
        parse_str((string)$response->getBody(), $fields);

        if ($fields['ACK'] != 'Success') {
            throw new \Exception('Paypal returned wrong acknowledgement'); //FIXME handle more than 100 entries 
        }

        $data = [];
        $i = 0;
        while (isset($fields["L_TRANSACTIONID$i"])) {
            $trans = [];
            foreach ([
                         'L_TIMESTAMP', 'L_TYPE', 'L_EMAIL', 'L_NAME', 'L_TRANSACTIONID', 'L_STATUS',
                         'L_AMT', 'L_CURRENCYCODE', 'L_FEEAMT', 'L_NETAMT'
                     ] as $f) {
                if (isset($fields[$f . $i])) {
                    $trans[$f] = $fields[$f . $i];
                }
            }
            if (!in_array($trans['L_STATUS'], ['Canceled', 'Denied'])) {
                $data[] = $this->makeTransaction($trans);
            }

            $i++;
        }

        return ($data);
    }

    /**
     * Fetch details from Paypal and create a Transaction
     *
     * @link https://developer.paypal.com/docs/classic/api/merchant/GetTransactionDetails_API_Operation_NVP/
     * @param array $data The basic Paypal transaction details
     * @return Transaction
     */
    protected function makeTransaction($data)
    {
        // fetch additional info for tsome types
        if (!in_array($data['L_TYPE'], ['Transfer', 'Currency Conversion (debit)', 'Currency Conversion (credit)'])) {

            $response = $this->client->post(
                'https://api-3t.paypal.com/nvp',
                [
                    RequestOptions::FORM_PARAMS => [
                        'PWD' => $this->config['pass'],
                        'USER' => $this->config['user'],
                        'SIGNATURE' => $this->config['signature'],
                        'VERSION' => 94,
                        'METHOD' => 'GetTransactionDetails',
                        'TRANSACTIONID' => $data['L_TRANSACTIONID']
                    ]
                ]
            );
            $fields = [];
            parse_str((string)$response->getBody(), $fields);
            if ($fields['ACK'] != 'Success') {
                echo('Paypal returned wrong acknowledgement');
            } else {
                $data = array_merge($data, $fields);
            }
        }

        $data['BEFORE_FEES'] = "before fees: " . $data['L_AMT'] . ' ' . $data['L_CURRENCYCODE'];

        $transaction = new Transaction(
            new \DateTime($data['L_TIMESTAMP']),
            $this->convert->convert($data['L_NETAMT'], $data['L_CURRENCYCODE']),
            $this->join($data, 'SUBJECT', 'L_NAME0', 'L_TYPE', 'L_TRANSACTIONID', 'BEFORE_FEES'),
            $this->join($data, 'L_NAME'),
            '',
            $this->join($data, 'L_EMAIL')
        );

        print_r($data);

        return $transaction;
    }

    /**
     * @param array $data Input data
     * @param string[] ...$fields fields in the data to use if available
     * @return string
     */
    protected function join($data, ...$fields)
    {
        $return = [];
        foreach ($fields as $field) {
            if (isset($data[$field])) $return[] = $data[$field];
        }

        return join("\n", $return);
    }
}
