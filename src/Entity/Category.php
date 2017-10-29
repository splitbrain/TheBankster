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

    /**
     * Get all categories for the use in forms
     *
     * @return array
     */
    static public function formList() {
        $data = [];
        $cats = Container::getInstance()->db->fetch(Category::class)->orderBy('top')->orderBy('label')->all();
        foreach ($cats as $cat) {
            if (!isset($data[$cat->top])) $data[$cat->top] = [];
            $data[$cat->top][$cat->id] = $cat->label;
        }
        return $data;
    }
}