<?php

namespace splitbrain\TheBankster\Model;

class Transaction extends AbstractModel
{
    protected $fields = [
        'account' => '',
        'datetime' => 0,
        'amount' => 0.0,
        'description' => '',
        'x_name' => '',
        'x_bank' => '',
        'x_acct' => '',
        'category_id' => null,
    ];

    /**
     * Custom setter for the datetime field
     *
     * @param int|string|\DateTime $dt
     */
    public function setDatetime($dt)
    {
        if (is_int($dt)) {
            $this->fields['datetime'] = $dt;
        } else {
            if (!is_a($dt, \DateTime::class)) {
                $dt = new \DateTime($dt);
            }
            $this->fields['datetime'] = $dt->getTimestamp();
        }
    }

    /**
     * Custom getter for datetime
     *
     * @return \DateTime
     */
    public function getDatetime()
    {
        $dt = new \DateTime();
        $dt->setTimestamp($this->fields['datetime']);
        return $dt;
    }

    /**
     * IDs are created from data, this avoids double importing
     *
     * @return string
     */
    protected function generateID()
    {
        return md5(join('-', [
            $this->fields['datetime'],
            $this->fields['description'],
            $this->fields['amount'],
            $this->fields['x_bank'],
            $this->fields['x_name'],
            $this->fields['x_acct']
        ]));
    }

    protected function validate()
    {
        if ($this->getDatetime()->getTimestamp() === 0) throw new \Exception('Zero Timestamp Transaction forbidden');
    }


    /**
     * Show a useful identifier
     *
     * @return string
     */
    public function __toString()
    {
        return
            $this->fields['account'] . "\t" .
            substr($this->id, 0, 8) . "\t" .
            $this->getDatetime()->format('Y-m-d H:i') . "\t" .
            $this->fields['amount'] . "\t" .
            substr(str_replace("\n", ' ', $this->fields['description']), 0, 25);
    }

    /**
     * Tries to remove unimportant stuff from the description
     *
     * @return string
     */
    public function getCleanDescription()
    {
        $lines = explode("\n", $this->fields['description']);

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