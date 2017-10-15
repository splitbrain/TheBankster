<?php

namespace splitbrain\TheBankster;

class Transaction
{
    protected $txid;
    protected $datetime;
    protected $amount;
    protected $description;
    protected $x_name;
    protected $x_bank;
    protected $x_acct;

    /**
     * Transaction constructor.
     * @param \DateTime|string $datetime
     * @param float $amount
     * @param string $description
     * @param string $x_name who issued the transaction
     * @param string $x_bank bank ID of the transaction issuer
     * @param string $x_acct account ID of the transaction issuer
     */
    public function __construct($datetime, $amount, $description, $x_name = '', $x_bank = '', $x_acct = '')
    {
        if (!is_a($datetime, \DateTime::class)) {
            $datetime = new \DateTime($datetime);
        }

        $this->datetime = $datetime;
        $this->amount = (float)$amount;
        $this->description = $description;
        $this->x_name = $x_name;
        $this->x_bank = $x_bank;
        $this->x_acct = $x_acct;

        $this->txid = md5(join('-', [
            $this->datetime->format('c'),
            $this->description,
            $this->amount
        ]));
    }

    /** @return string */
    public function getTxid(): string
    {
        return $this->txid;
    }

    /** @return \DateTime|string */
    public function getDatetime()
    {
        return $this->datetime;
    }

    /** @return float */
    public function getAmount(): float
    {
        return $this->amount;
    }

    /** @return string */
    public function getDescription(): string
    {
        return $this->description;
    }

    /** @return string */
    public function getXName(): string
    {
        return $this->x_name;
    }

    /** @return string */
    public function getXBank(): string
    {
        return $this->x_bank;
    }

    /** @return string */
    public function getXAcct(): string
    {
        return $this->x_acct;
    }

    /**
     * Tries to remove unimportant stuff from the description
     *
     * @return string
     */
    public function getCleanDescription()
    {
        $lines = explode("\n", $this->description);

        // try to get the EREF bullshit to the end
        $parts = explode('+', $lines[0]);
        $lines[0] = array_pop($parts);
        $lines = array_merge($lines, $parts);
        if (preg_match('/^(SEPA.*?) /', $lines[0], $m)) {
            $lines[0] = preg_replace('/^(SEPA.*?) /', '', $lines[0]);
            $lines[] = $m[1];
        }

        // more line breaks
        $text = join("\n", $lines);
        $text = str_replace('+', "\n", $text);
        $text = str_replace('//', "\n", $text);
        $text = str_replace('/', "\n", $text);

        // clean up
        $lines = explode("\n", $text);
        $lines = array_map('trim', $lines);

        $lines = array_filter($lines, function ($line) {
            if (!$line) return false;
            if (preg_match('/(EREF|CRED|MREF|SVWZ)$/', $line)) return false;
            return true;
        });

        return join("\n", $lines);
    }
}