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
        if(empty($this->fields['name'])) throw new \Exception('Name has to be set');
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
}