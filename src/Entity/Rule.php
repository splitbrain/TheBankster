<?php

namespace splitbrain\TheBankster\Entity;

use ORM\Entity;

class Rule extends Entity
{
    protected static $relations = [
        'category' => [Category::class, ['categoryId' => 'id']],
    ];


    /**
     * Match income (+1), spending (-1) or both (0)?
     *
     * @param $debit
     */
    public function setDebit($debit)
    {
        $debit = (int)$debit;
        if ($debit > 0) $debit = 1;
        if ($debit < 0) $debit = -1;
        if ($debit == 0) $debit = 0;

        $this->data[static::getColumnName('debit')] = $debit;
    }


    /**
     * Return the rule as readable string
     *
     * @return string
     */
    public function displayRules()
    {
        $matches = [];

        foreach (['account', 'debit', 'description', 'xName', 'xBank', 'xAcct'] as $key) {
            if ($this->$key) {
                $val = $this->$key;
                if ($key == 'debit') {
                    $val = ($val > 0) ? 'income' : 'spending';
                }

                $matches[] = "$key = $val";
            }
        }

        return join("\n", $matches);
    }

    /**
     * @return \ORM\EntityFetcher
     */
    public function matchTransactionsQuery()
    {
        $query = $this->entityManager->fetch(Transaction::class);

        if ($this->account) {
            $query->andWhere('account', '=', $this->account);
        }

        if ($this->debit) {
            $op = ($this->debit < 0) ? '<=' : '>=';
            $query->andWhere('amount', $op, 0);
        }

        foreach (['description', 'xName', 'xBank', 'xAcct'] as $key) {
            if ($this->$key) {
                $query->andWhere($key, 'LIKE', '%' . $this->$key . '%');
            }
        }

        return $query;
    }

    public function __isset($value)
    {
        return true;
    }
}