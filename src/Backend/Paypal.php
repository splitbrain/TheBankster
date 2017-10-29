<?php

namespace splitbrain\TheBankster\Backend;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use splitbrain\TheBankster\CurrencyConvert;
use splitbrain\TheBankster\Entity\Transaction;

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
    public function __construct($config, $accountid)
    {
        parent::__construct($config, $accountid);

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
    public static function configDescription()
    {
        return [
            'user' => [
                'help' => 'Your API user created at https://www.paypal.com/de/cgi-bin/webscr?cmd=_profile-api-add-direct-access-show',
            ],
            'pass' => [
                'help' => 'Your password for above user',
                'type' => 'password',
            ],
            'signature' => [
                'help' => 'Your signature for above user',
                'type' => 'password',
            ]
        ];
    }

    /** @inheritdoc */
    public function importTransactions(\DateTime $since)
    {
        $transactions = $this->findTransactions($since);

        $this->logger->notice('{count} new Paypal transactions available', ['count' => count($transactions)]);

        foreach ($transactions as $transaction) {
            $tx = $this->makeTransaction($transaction);
            $this->storeTransaction($tx);
        }
    }


    /**
     * Fetch transactions from Paypal
     *
     * @link https://developer.paypal.com/webapps/developer/docs/classic/api/merchant/TransactionSearch_API_Operation_NVP/
     * @param \DateTime $since
     * @param \DateTime|null $until
     * @return array
     * @throws \Exception
     */
    protected function findTransactions(\DateTime $since, \DateTime $until = null)
    {
        $this->logger->debug('Calling Paypal TransactionSearch');
        $options = [
            'PWD' => $this->config['pass'],
            'USER' => $this->config['user'],
            'SIGNATURE' => $this->config['signature'],
            'VERSION' => 94,
            'METHOD' => 'TransactionSearch',
            'STARTDATE' => $since->setTimezone(new \DateTimeZone('UTC'))->format('c'),
        ];
        if ($until) {
            $options['ENDDATE'] = $until->setTimezone(new \DateTimeZone('UTC'))->format('c');
        }

        $response = $this->client->post(
            'https://api-3t.paypal.com/nvp',
            [RequestOptions::FORM_PARAMS => $options]
        );

        $fields = $this->http_parse_query((string)$response->getBody());

        $rerun = false;
        if ($fields['ACK'] == 'SuccessWithWarning') {
            $rerun = true;
        } else if ($fields['ACK'] != 'Success') {
            print_r($fields);
            throw new \Exception('Paypal returned wrong acknowledgement');
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
            if (
                !in_array($trans['L_STATUS'], ['Canceled', 'Denied', 'Removed', 'Pending']) &&
                !in_array($trans['L_TYPE'], ['Authorization', 'Order'])
            ) {
                $data[] = $trans;
            }

            $i++;
        }

        $this->logger->info('{count} Paypal transactions found', ['count' => count($data)]);

        if ($rerun && isset($trans)) {
            $until = new \DateTime($trans['L_TIMESTAMP']);
            if ($since->diff($until)->format('%s') > 0) {
                $this->logger->warning(
                    'More Paypal transactions before {date} available. fetching...',
                    ['date' => $until->format('Y-m-d')]);
                $data = array_merge($data, $this->findTransactions($since, $until));
            }
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
            $this->logger->debug('Calling Paypal GetTransactionDetails');
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
                $this->logger->error('Paypal returned wrong acknowledgement');
                $this->logger->debug(print_r($data, true));
            } else {
                $data = array_merge($data, $fields);
            }
        }
        #$this->logger->debug(print_r($data));

        $data['BEFORE_FEES'] = "before fees: " . $data['L_AMT'] . ' ' . $data['L_CURRENCYCODE'];

        $tx = new Transaction();
        $tx->datetime = $data['L_TIMESTAMP'];
        $tx->amount = $this->convert->convert($data['L_NETAMT'], $data['L_CURRENCYCODE']);
        $tx->description = $this->join($data, 'SUBJECT', 'L_NAME0', 'L_TYPE', 'L_TRANSACTIONID', 'BEFORE_FEES');
        $tx->xName = $this->join($data, 'L_NAME');
        $tx->xBank = 'Paypal';
        $tx->xAcct = $this->join($data, 'L_EMAIL');
        return $tx;
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

    /**
     * Parses http query string into an array
     *
     * @author Alxcube <alxcube@gmail.com>
     * @link http://php.net/manual/en/function.parse-str.php#119484
     *
     * @param string $queryString String to parse
     * @param string $argSeparator Query arguments separator
     * @param integer $decType Decoding type
     * @return array
     */
    function http_parse_query($queryString, $argSeparator = '&', $decType = PHP_QUERY_RFC1738)
    {
        $result = array();
        $parts = explode($argSeparator, $queryString);

        foreach ($parts as $part) {
            list($paramName, $paramValue) = explode('=', $part, 2);

            switch ($decType) {
                case PHP_QUERY_RFC3986:
                    $paramName = rawurldecode($paramName);
                    $paramValue = rawurldecode($paramValue);
                    break;

                case PHP_QUERY_RFC1738:
                default:
                    $paramName = urldecode($paramName);
                    $paramValue = urldecode($paramValue);
                    break;
            }


            if (preg_match_all('/\[([^\]]*)\]/m', $paramName, $matches)) {
                $paramName = substr($paramName, 0, strpos($paramName, '['));
                $keys = array_merge(array($paramName), $matches[1]);
            } else {
                $keys = array($paramName);
            }

            $target = &$result;

            foreach ($keys as $index) {
                if ($index === '') {
                    if (isset($target)) {
                        if (is_array($target)) {
                            $intKeys = array_filter(array_keys($target), 'is_int');
                            $index = count($intKeys) ? max($intKeys) + 1 : 0;
                        } else {
                            $target = array($target);
                            $index = 1;
                        }
                    } else {
                        $target = array();
                        $index = 0;
                    }
                } elseif (isset($target[$index]) && !is_array($target[$index])) {
                    $target[$index] = array($target[$index]);
                }

                $target = &$target[$index];
            }

            if (is_array($target)) {
                $target[] = $paramValue;
            } else {
                $target = $paramValue;
            }
        }

        return $result;
    }
}
