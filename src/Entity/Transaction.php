<?php

namespace splitbrain\TheBankster\Entity;

class Transaction extends \ORM\Entity
{
    protected static $relations = [
        'category' => [Category::class, ['categoryId' => 'id']],
    ];


    /**
     * Custom setter for the ts field
     *
     * @param int|string|\DateTime $dt
     */
    public function setDatetime($dt)
    {
        if (is_int($dt)) {
            $this->ts = $dt;
        } else {
            if (!is_a($dt, \DateTime::class)) {
                $dt = new \DateTime($dt);
            }
            $this->ts = $dt->getTimestamp();
        }
    }

    /**
     * Custom getter for ts
     *
     * @return \DateTime
     */
    public function getDatetime()
    {
        $dt = new \DateTime();
        $dt->setTimestamp($this->ts);
        return $dt;
    }

    /**
     * Show a useful identifier
     *
     * @return string
     */
    public function __toString()
    {
        return
            $this->account . "\t" .
            $this->id . "\t" .
            $this->datetime->format('Y-m-d H:i') . "\t" .
            $this->amount . "\t" .
            substr(str_replace("\n", ' ', $this->getCleanDescription()), 0, 50);
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