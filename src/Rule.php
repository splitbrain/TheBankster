<?php

namespace splitbrain\TheBankster;

class Rule
{

    /** @var  string */
    protected $name;

    /** @var  int */
    protected $id;

    protected $rule = [
        'debit' => 0,
        'account' => '',
        'description' => '',
        'x_name' => '',
        'x_bank' => '',
        'x_acct' => ''
    ];


    public function __construct($name)
    {
        $this->name = $name;
    }

    /**
     * Get a list of all available rules
     *
     * @return Rule[]
     */
    static public function loadAllRules()
    {
        $db = new DataBase();

        $sql = 'SELECT * FROM rules ORDER BY name';
        $rows = $db->queryAll($sql);

        $rules = [];
        foreach ($rows as $row) {
            $rules[] = self::initFromRecord($row);
        }

        return $rules;
    }

    /**
     * Load a rule from the database
     *
     * @param int $id
     * @return Rule
     * @throws \Exception
     */
    static public function loadFromDB($id)
    {
        $db = new DataBase();

        $sql = 'SELECT * FROM rules WHERE rule = :rule';
        $record = $db->queryRecord($sql, [':rule' => $id]);
        if (!$record) throw new \Exception('No such rule');

        return self::initFromRecord($record);
    }

    /**
     * Initialize a rule from the database record
     *
     * @param array $record
     * @return Rule
     */
    static public function initFromRecord($record)
    {
        $rule = new Rule($record['name']);
        $rule->setId($record['rule']);
        $rule->matchAccount($record['account']);
        $rule->matchDebit($record['debit']);
        $rule->matchDescription($record['description']);
        $rule->matchXName($record['x_name']);
        $rule->matchXBank($record['x_bank']);
        $rule->matchXAccount($record['x_acct']);

        return $rule;
    }

    /**
     * Save this rule
     *
     * @return int
     */
    public function save()
    {
        $this->validateMatches();
        if ($this->id) {
            $sql = 'UPDATE rules
                       SET name = :name,
                           account = :account,
                           debit = :debit,
                           description = :description,
                           x_name = :x_name,
                           x_bank = :x_bank,
                           x_acct = :x_acct
                     WHERE rule = :rule';
        } else {
            $sql = 'INSERT INTO rules
                           (name, account, debit, description, x_name, x_bank, x_acct) 
                    VALUES (:name, :account, :debit, :description, :x_name, :x_bank, :x_acct)';
        }

        $db = new DataBase();
        return $db->exec($sql, $this->getInsertData());
    }

    /**
     * Return the set matches as string
     *
     * @return string
     */
    public function getDisplayMatches()
    {
        $matches = [];

        foreach ($this->rule as $key => $val) {
            if ($val) {
                if ($key == 'debit') {
                    $val = ($val > 0) ? 'income' : 'spending';
                }

                $matches[] = "$key = $val";
            }
        }

        return join("\n", $matches);
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * Set the ID
     *
     * @param int $id
     */
    public function setId($id)
    {
        $this->id = $id;
    }

    /**
     * @return int
     */
    public function getId(): int
    {
        return $this->id;
    }

    /**
     * Match income (+1), spending (-1) or both (0)?
     *
     * @param $debit
     */
    public function matchDebit($debit)
    {
        if ($debit > 0) $debit = 1;
        if ($debit < 0) $debit = -1;
        if ($debit == 0) $debit = 0;

        $this->rule['debit'] = $debit;
    }

    /**
     * Match only this account ID
     *
     * @param string $account
     */
    public function matchAccount($account)
    {
        $this->rule['account'] = $account;
    }

    /**
     * Match against this description
     *
     * @param string $description
     */
    public function matchDescription($description)
    {
        $this->rule['description'] = $description;
    }

    /**
     * Match against this account holder name
     *
     * @param string $x_name
     */
    public function matchXName($x_name)
    {
        $this->rule['x_name'] = $x_name;
    }

    /**
     * Match against this bank name
     *
     * @param string $x_bank
     */
    public function matchXBank($x_bank)
    {
        $this->rule['x_bank'] = $x_bank;
    }

    /**
     * Match against this account name
     *
     * @param string $x_acct
     */
    public function matchXAccount($x_acct)
    {
        $this->rule['x_acct'] = $x_acct;
    }

    /**
     * Placeholder to insert the data
     */
    protected function getInsertData()
    {
        $data = [
            ':name' => $this->name,
        ];
        foreach ($this->rule as $key => $val) {
            $data[":$key"] = $val;
        }
        if ($this->id) {
            $data[':rule'] = $this->id;
        }
        return $data;
    }

    /**
     * Throw an exception if all the matching rules are empty
     *
     * @throws \Exception
     */
    protected function validateMatches() {
        foreach ($this->rule as $key => $val) {
            if($val) return;
        }
        throw new \Exception('This rule has no matches set up');
    }
}