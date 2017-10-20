<?php

namespace splitbrain\TheBankster;

/**
 * Class CurrencyConvert
 *
 * Convert currencies into the default currency
 *
 * This is currently just approximate.
 *
 * @package splitbrain\TheBankster
 */
class CurrencyConvert
{

    protected $main = 'EUR';

    /**
     * @var array
     *
     * what 1 main unit is worth
     */
    protected $rates = [
        'USD' => 1.18
    ];

    /**
     * CurrencyConvert constructor.
     * @param string $main The main currency
     */
    public function __construct($main = 'EUR')
    {
        $this->main = 'EUR';
        $this->rates[$this->main] = 1.0; // self conversion
    }

    /**
     * Convert the amount form one to another currency
     *
     * @param float $amount
     * @param string $from
     * @param string $to
     * @return float
     * @throws \Exception
     */
    function convert($amount, $from, $to = '')
    {
        if ($to == '') $to = $this->main;

        // no cneversion needed
        if ($from == $to) {
            return $amount;
        }

        // ensure we have a rate
        if (!isset($this->rates[$from])) {
            throw new \Exception("Cannot convert from $from");
        }
        if (!isset($this->rates[$to])) {
            throw new \Exception("Cannot convert to $to");
        }

        // make sure one of the currencies is our main currency
        if ($from != $this->main && $to != $this->main) {
            $from = $this->convert($amount, $from);
        }

        // convert
        if ($to == $this->main) {
            $result = $amount / $this->rates[$from];
        } else {
            $result = $amount * $this->rates[$to];
        }

        return round($result, 2);
    }
}