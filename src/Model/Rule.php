<?php

namespace splitbrain\TheBankster\Model;

class Rule extends AbstractModel
{

    protected $fields = [
        'category_id' => 0,
        'name' => '',
        'account' => '',
        'debit' => 0,
        'description' => '',
        'x_name' => '',
        'x_bank' => '',
        'x_acct' => ''
    ];


    /**
     * Match income (+1), spending (-1) or both (0)?
     *
     * @param $debit
     */
    public function setDebit($debit)
    {
        if ($debit > 0) $debit = 1;
        if ($debit < 0) $debit = -1;
        if ($debit == 0) $debit = 0;

        $this->fields['debit'] = $debit;
    }

    /** @inheritdoc */
    protected function validate()
    {
        parent::validate();
        if (empty($this->fields['name'])) throw new \Exception('Name has to be set');
    }


    /**
     * Return the set matches as string
     *
     * @return string
     */
    public function displayRules()
    {
        $matches = [];

        foreach (['account', 'debit', 'description', 'x_name', 'x_bank', 'x_acct'] as $key) {
            if ($this->fields[$key]) {
                $val = $this->fields[$key];
                if ($key == 'debit') {
                    $val = ($val > 0) ? 'income' : 'spending';
                }

                $matches[] = "$key = $val";
            }
        }

        return join("\n", $matches);
    }

    /**
     * Get the WHERE conditions to apply this rule
     *
     * @param \DateTime|null $from
     * @param \DateTime|null $to
     * @return array($sql,$vars)
     */
    public function getMatchCondition(\DateTime $from = null, \DateTime $to = null)
    {
        $where = [];
        $vars = [];

        // LIKE matching
        foreach (['description', 'x_name', 'x_bank', 'x_acct'] as $key) {
            if(!empty($this->fields[$key])) {
                $match = $this->fields[$key];
                $where[] = '"'.$key.'" LIKE :'.$key;
                $vars[":$key"] = "%$match%";
            }
        }

        if(!empty($this->fields['account'])) {
            $where[] = '"account" = :account';
            $vars[':account'] = $this->fields['account'];
        }

        if($this->fields['debit'] < 0) {
            $where[] = '"amount" < 0';
        } elseif($this->fields['debit'] > 0) {
            $where[] = '"amount" > 0';
        }

        if($from !== null) {
            $where[] = '"datetime" >= :from';
            $vars[':from'] = $from->getTimestamp();
        }

        if($to !== null) {
            $where[] = '"datetime" <= :to';
            $vars[':to'] = $from->getTimestamp();
        }

        $sql = join("\nAND\n", $where);

        return [$sql, $vars];
    }

    /**
     * Get all transactions matching this rule
     *
     * @return Transaction[]
     */
    public function getTransactions() {
        list($where, $vars) = $this->getMatchCondition();
        $sql = 'SELECT * FROM "transaction" WHERE '.$where;
        $list = $this->db->queryAll($sql, $vars);

        $transactions = [];
        if($list) foreach($list as $row) {
            $transactions[] = new Transaction($row);
        }
        return $transactions;
    }
}