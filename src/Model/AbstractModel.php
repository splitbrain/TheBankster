<?php

namespace splitbrain\TheBankster\Model;

use splitbrain\TheBankster\DataBase;

/**
 * Class AbstractModel
 *
 * This implements a poor man's ORM
 *
 * @package splitbrain\TheBankster\Model
 */
abstract class AbstractModel implements \ArrayAccess
{

    /** @var mixed main identifier */
    protected $id;

    /** @var array() holds the data */
    protected $fields;

    /** @var DataBase */
    protected $db;

    /**
     * AbstractModel constructor.
     *
     * @param null|array $fields
     */
    public function __construct($fields = null)
    {
        if ($fields) $this->setData($fields);
        $this->db = new DataBase();
    }

    /**
     * Load all instances of this model from the database
     *
     * @return self[]
     */
    public static function loadAll()
    {
        $table = self::getTableName();
        $sql = "SELECT * FROM $table"; // FIXME set sorting

        $db = new DataBase();
        $records = $db->queryAll($sql);

        $class = get_called_class();
        $objs = [];
        foreach ($records as $record) {
            /** @var AbstractModel $obj */
            $obj = new $class();
            $obj->setData($record);
            $objs[] = $obj;
        }
        return $objs;
    }

    /**
     * Load a single instance
     *
     * @param $id
     * @return AbstractModel
     * @throws \Exception
     */
    public static function load($id)
    {
        $table = self::getTableName();
        $sql = "SELECT * FROM $table WHERE id = :id";

        $db = new DataBase();
        $record = $db->queryRecord($sql, ['id' => $id]);

        if (!$record) throw new \Exception('No such entity');

        $class = get_called_class();
        /** @var AbstractModel $obj */
        $obj = new $class();
        $obj->setData($record);
        return $obj;
    }

    /**
     * @param $fields
     */
    public function setData($fields)
    {
        foreach ($fields as $key => $value) {
            if (isset($this->fields[$key])) $this->fields[$key] = $value;
        }
        if (isset($fields['id'])) $this->id = $fields['id'];
    }

    /**
     * Override to validate before saving
     * 
     * @throws \Exception
     */
    protected function validate()
    {
    }

    /**
     * Save this instance
     */
    public function save()
    {
        $this->validate();

        if ($this->id) {
            return $this->update();
        } else {
            return $this->insert();
        }
    }

    /**
     * Delete this instance
     *
     * @return int
     * @throws \Exception
     */
    public function delete()
    {
        if (!$this->id) throw new \Exception('No such entity');

        $table = self::getTableName();
        $sql = "DELETE FROM $table WHERE id = :id";

        $ok = $this->db->exec($sql, [':id' => $this->id]);
        $this->id = '';
        return $ok;
    }

    /**
     * Update this record
     *
     * @return int
     */
    protected function update()
    {
        $table = self::getTableName();
        $placeholders = $this->getPlaceholders();
        $placeholders['id'] = $this->id;

        $inserts = [];
        foreach ($this->fields as $key => $value) {
            $inserts[] = '"' . $key . '"' . '= :' . $key;
        }
        $inserts = join(", ", $inserts);

        $sql = "UPDATE $table SET $inserts WHERE id = :id";

        return $this->db->exec($sql, $placeholders);
    }

    /**
     * Insert as new record
     *
     * @return int
     */
    protected function insert()
    {
        $table = self::getTableName();
        $placeholders = $this->getPlaceholders();
        $fields = join(', ',
            array_map(
                function ($in) {
                    return '"' . $in . '"';
                },
                array_keys($this->fields)
            )
        );
        $pl = join(', ', array_keys($placeholders));

        $sql = "INSERT INTO $table ($fields) VALUES ($pl)";

        $id = $this->db->exec($sql, $placeholders);
        $this->id = $id;
        return $id;
    }

    /**
     * Get the tablename matching the class
     *
     * @return string already escaped
     */
    protected static function getTableName()
    {
        $class = get_called_class();
        $name = substr($class, strrpos($class, '\\') + 1);
        $name = strtolower($name);
        return '"' . $name . '"';
    }

    /**
     * Current data ready for insertion
     *
     * @return array
     */
    protected function getPlaceholders()
    {
        $placeholders = [];
        foreach ($this->fields as $key => $val) {
            $placeholders[":$key"] = $val;
        }
        return $placeholders;
    }

    /** @inheritdoc */
    public function offsetSet($name, $value)
    {
        $setter = ucfirst("get_$name");
        if (is_callable([$this, $setter])) {
            return $this->$setter($value);
        }

        if (isset($this->fields[$name])) {
            $this->fields[$name] = $value;
        } elseif ($name === 'id') {
            $this->id = $value;
        } else {
            throw new \Exception('No such field');
        }

    }

    /** @inheritdoc */
    public function offsetExists($name)
    {
        return isset($this->fields[$name]) or ($name == 'id');
    }

    /** @inheritdoc */
    public function offsetUnset($name)
    {
        $this->offsetSet($name, '');
    }

    /** @inheritdoc */
    public function offsetGet($name)
    {
        $getter = ucfirst("get_$name");
        if (is_callable([$this, $getter])) {
            return $this->$getter();
        }

        if (isset($this->fields[$name])) {
            return $this->fields[$name];
        } elseif ($name == 'id') {
            return $this->id;
        }
        throw new \Exception('No such field');
    }


    /**
     * Convert snake to camel case
     *
     * @link https://stackoverflow.com/a/31275117/172068
     * @param $snake
     * @return string
     */
    static protected function snakeToCamel($snake)
    {
        return lcfirst(implode('', array_map('ucfirst', explode('_', $snake))));
    }
}