<?php

namespace splitbrain\TheBankster\Entity;

use ORM\Entity;
use splitbrain\TheBankster\Container;

class Category extends Entity
{

    /**
     * Get the complete label name
     *
     * @return string
     */
    public function getFullName()
    {
        return $this->top . '/' . $this->label;
    }

    /**
     * Rename a top category
     *
     * @param string $old The old top name
     * @param string $new The new top name
     * @return int number of changed rows
     */
    static public function renameTop($old, $new)
    {
        $db = Container::getInstance()->db->getSqlHelper();
        $sql = 'UPDATE "category" SET top = :new WHERE top = :old';
        return $db->exec($sql, [':new' => $new, ':old' => $old]);
    }
}