<?php

namespace splitbrain\TheBankster\Model;

use splitbrain\TheBankster\DataBase;

class Category extends AbstractModel
{

    protected $fields = [
        'top' => '',
        'label' => ''
    ];


    /**
     * @inheritdoc
     */
    public function delete()
    {
        if (!$this->id) throw new \Exception('No such entity');

        // deassociate transactions
        $sql = 'UPDATE "transaction" SET category_id = NULL WHERE category_id = :id';
        $this->db->exec($sql, [':id' => $this->id]);

        // remove rules for this rule
        $sql = 'DELETE FROM "rule" WHERE category_id = :id';
        $this->db->exec($sql, [':id' => $this->id]);

        return parent::delete();
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
        $sql = 'UPDATE "category" SET top = :new WHERE top = :old';
        $db = new DataBase();
        return $db->exec($sql, [':new' => $new, ':old' => $old]);
    }


}