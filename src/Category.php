<?php

namespace splitbrain\TheBankster;

class Category
{

    protected $db;

    public function __construct()
    {
        $this->db = new DataBase();
    }

    public function getAll()
    {
        $sql = 'SELECT cat, top, label FROM categories ORDER BY top, label';
        return $this->db->queryAll($sql);
    }

    /**
     * Create a new category
     *
     * @param string $top
     * @param string $label
     * @return int the category ID
     */
    public function add($top, $label)
    {
        $sql = 'INSERT INTO categories (top, label) VALUES (:top, :label)';
        return $this->db->exec($sql, [':top' => $top, ':label' => $label]);
    }

    /**
     * Update a category's descriptions
     *
     * @param $cat
     * @param string $top
     * @param string $label
     * @return int number of updated rows
     */
    public function update($cat, $top, $label)
    {
        $sql = 'UPDATE categories SET top = :top, label = :label WHERE cat = :cat';
        return $this->db->exec($sql, [':top' => $top, ':label' => $label, ':cat' => $cat]);
    }

    /**
     * Rename a top category
     *
     * @param string $old The old top name
     * @param string $new The new top name
     * @return int number of changed rows
     */
    public function renameTop($old, $new) {
        $sql = 'UPDATE categories SET top = :new WHERE top = :old';
        return $this->db->exec($sql, [':new' => $new, ':old' => $old]);
    }

    /**
     * Delete category with given ID
     *
     * @param int $cat
     * @return int
     */
    public function del($cat)
    {
        // deassociate transactions
        $sql = 'UPDATE transactions SET cat = NULL WHERE cat = :cat';
        $this->db->exec($sql, ['cat' => $cat]);

        // delete category
        $sql = 'DELETE FROM categories WHERE cat = :cat';
        return $this->db->exec($sql, ['cat' => $cat]);
    }
}