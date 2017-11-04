<?php


namespace splitbrain\TheBankster\Backend;

use splitbrain\TheBankster\Entity\Transaction;

/**
 * Class AmazonMail
 *
 * This backend logs into a mail account and looks for order confirmation and confirmed refund
 * emails from Amazon. This information is used to create transactions based on your Amazon
 * purchases. When using this, you should create a rule to skip (category -/-) Amazon transactions
 * in your bank account to avoid tracking spendings twice.
 *
 * This backend currently supports Amazon.de emails in German only. PullRequests for other Amazon
 * accounts or languages are welcome.
 *
 * @package splitbrain\TheBankster\Backend
 */
class AmazonMail extends AbstractBackend
{
    /**
     * Describe what configuration is required for this backend
     *
     * @return array
     */
    public static function configDescription()
    {
        return [
            'mailbox' => [
                'help' => 'Your server and the mailbox to look at. Please refer to http://php.net/imap_open for details. Example for GMail: {imap.gmail.com:993/imap/ssl}Amazon',
            ],
            'user' => [
                'help' => 'Your IMAP user. This might be your full email address.',
            ],
            'pass' => [
                'help' => 'Your IMAP password. GMail users should create an app password at https://myaccount.google.com/apppasswords',
                'type' => 'password',
            ],
        ];
    }

    /**
     * @throws \Exception when anything is not right, message is shown to the user
     * @return string Any success mesage to show to the user
     */
    public function checkSetup()
    {
        $inbox = $this->openImap();
        imap_close($inbox);

        return 'IMAP connection succeeded.';
    }

    /**
     * Return all statements since the given DateTime
     *
     * Backends that can not select by time should return as many statements as possible
     *
     * @param \DateTime $since
     * @throws \Exception
     */
    public function importTransactions(\DateTime $since)
    {
        $inbox = $this->openImap();
        $emails = imap_search($inbox, 'ALL SINCE '.$since->format('Y-m-d'));
        if(!is_array($emails)) {
            throw new \Exception('Failed to look for mails '.imap_last_error());
        }

        foreach ($emails as $email_number) {
            $overview = imap_fetch_overview($inbox, $email_number);
            $message = imap_fetchbody($inbox, $email_number, '1' /* = plain text*/);
            $message = @imap_qprint($message);

            $date = new \DateTime($overview[0]->date);
            $from = @imap_utf8($overview[0]->from);
            $subject = @imap_utf8($overview[0]->subject);

            imap_errors();
            imap_alerts(); // see https://stackoverflow.com/a/5423306/172068

            // match email address to decoding function FIXME add more types here
            try {
                if (preg_match('/bestellbestaetigung@amazon.de/', $from) &&
                    preg_match('/Bestellung/', $subject)
                ) {
                    $products = $this->decodeGermanOrderMessage($message);
                } elseif (
                    preg_match('/rueckgabe@amazon.de/', $from) &&
                    preg_match('/Ihre Erstattung/', $subject)
                ) {
                    $products = $this->decodeGermanReturnMessage($message);
                } else {
                    $this->logger->warning('Skipping ' . $date->format('r') . ' ' . $subject);
                    continue;
                }
            } catch (\Exception $e) {
                $this->logger->error('Failed ' . $date->format('r') . ' ' . $subject . ' ' . $e->getMessage());
                continue;
            }

            $this->storeProducts($products, $date);
        }

        imap_close($inbox);
    }

    /**
     * Open IMAP connection
     *
     * @return resource
     * @throws \Exception
     */
    protected function openImap()
    {
        if (!function_exists('imap_open')) throw new \Exception('The PHP IMAP extension is not available');

        $inbox = @imap_open($this->config['mailbox'], $this->config['user'], $this->config['pass'], OP_READONLY);
        if (!$inbox) throw new \Exception('Cannot connect to IMAP Server: ' . imap_last_error());
        $error = imap_last_error();
        imap_errors();
        imap_alerts(); // see https://stackoverflow.com/a/5423306/172068
        if ($error) throw new \Exception("IMAP Server returned error: $error");

        return $inbox;
    }

    /**
     * Stores the found products as a transaction
     *
     * @param array $products
     * @param \DateTime $dt when the products where ordered
     */
    protected function storeProducts($products, \DateTime $dt)
    {
        foreach ($products as $product) {
            $tx = new Transaction();
            $tx->amount = $product['amount'];
            $tx->datetime = $dt;
            $tx->description = $product['order'] . " "
                . $product['platform'] . " Order\n"
                . $product['description'];
            $tx->xName = $product['platform'];
            $tx->xBank = $product['platform'];
            $tx->xAcct = $product['order'];

            $this->storeTransaction($tx);
        }
    }

    protected function decodeGermanReturnMessage($message)
    {
        if (preg_match('/\nDetails:\r\n (.*?)\r\n\r\nGesamtbetrag: EUR ([\.\d]+,\d\d)/s', $message, $m)) {
            $product = [
                'order' => '',
                'platform' => 'Amazon.de',
                'description' => "Amazon Refund\n" . $m[1],
                'amount' => floatval(str_replace(['.', ','], ['', '.'], $m[2])),
            ];

            if (preg_match('/orderID=(\d{3}-\d{7}-\d{7})/s', $message, $m)) {
                $product['order'] = $m[1];
            }

            return [$product];
        }


        throw new \Exception('Failed to parse return message');
    }

    /**
     * Parse the products out of a German Amazon.de order message
     *
     * @param string $message The plain text variant of the mail body
     * @return array
     * @throws \Exception
     */
    protected function decodeGermanOrderMessage($message)
    {
        $products = [];

        // check for bought vouchers
        if (preg_match('/Bestellt\(e\) Geschenkgutschein\(e\) \(Bestellnr.  (\d{3}-\d{7}-\d{7})\)/s', $message, $m)) {
            $product = [
                'order' => $m[1],
                'platform' => 'Amazon.de',
                'description' => 'Gift Voucher'
            ];
            if (preg_match('/GESAMTBETRAG: EUR ([\.\d]+,\d\d)/s', $message, $m)) {
                $product['amount'] = -1.0 * floatval(str_replace(['.', ','], ['', '.'], $m[1]));
                return [$product];
            } else {
                throw new \Exception('Failed to parse voucher message');
            }
        }

        // check for digital items
        if (preg_match('/Bestellung #: (D01-\d{7}-\d{7})/s', $message, $m)) {
            $product = [
                'order' => $m[1],
                'platform' => 'Amazon.de',
                'description' => 'Digital Item'
            ];
            if (preg_match('/Amazon Instant Video Germany GmbH/', $message)) {
                $product['description'] .= "\nAmazon Instant Video";
            } // FIXME I don't know how other digital products (like kindle, games or apps) look
            if (preg_match('/\nSumme:\s+EUR ([\.\d]+,\d\d)/s', $message, $m)) {
                $product['amount'] = -1.0 * floatval(str_replace(['.', ','], ['', '.'], $m[1]));
                return [$product];
            } else {
                throw new \Exception('Failed to parse digital download message');
            }
        }

        // check for normal order
        if (!preg_match_all('/\n(Bestellnummer #(\d{3}-\d{7}-\d{7})\s+.*?)(_{40})/s', $message, $matches, PREG_SET_ORDER)) {
            throw new \Exception('Couldn\'t parse message');
        }
        foreach ($matches as $m) {
            $product = [];
            $message = $m[1];
            $order = $m[2];
            $this->logger->info("parsing $order");


            $lines = explode("\n", $message);
            foreach ($lines as $line) {
                // two tabs mark a new product
                if (substr($line, 0, 2) == "\t\t") {
                    if (isset($product['amount'])) $products[] = $product; // save last product
                    $product = []; // prepare new one
                    continue;
                }

                // lines not starting with spaces are not part of the products
                if (substr($line, 0, 15) != '               ') continue;

                // parse out amount and keep the rest as description
                $line = trim($line);
                if (preg_match('/^EUR ([\.\d]+,\d\d)/', $line, $m)) {
                    $product['amount'] = -1.0 * floatval(str_replace(['.', ','], ['', '.'], $m[1]));
                    $product['order'] = $order;
                    $product['platform'] = 'Amazon.de';
                } else {
                    if (!isset($product['description'])) {
                        $product['description'] = $line;
                    } else {
                        $product['description'] .= "\n$line";
                    }
                }
            }
            if (isset($product['amount'])) $products[] = $product; // save last product
        }

        $count = count($products);
        if (!$count) throw new \Exception("found no products in the mail");
        $this->logger->info("Found $count products in message");

        return $products;
    }
}