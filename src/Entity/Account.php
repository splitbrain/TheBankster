<?php

namespace splitbrain\TheBankster\Entity;

use ORM\Entity;
use splitbrain\TheBankster\Backend\AbstractBackend;

class Account extends Entity
{
    protected static $primaryKey = 'account';
    protected static $autoIncrement = false;

    /**
     * Get all available backends and their configuration description
     *
     * @return array
     */
    public static function listBackends()
    {
        static $backends = null;
        if ($backends === null) {
            $files = glob(__DIR__ . '/../Backend/*.php');
            $backends = [];
            foreach ($files as $file) {
                $class = basename($file, '.php');
                if ($class == 'AbstractBackend') continue;
                $full = '\\splitbrain\\TheBankster\\Backend\\' . $class;
                $backends[$class] = call_user_func([$full, 'configDescription']);
            }
        }
        return $backends;
    }

    /**
     * @param $value
     * @throws \Exception
     */
    public function setBackend($value)
    {
        $backends = self::listBackends();
        if (!isset($backends[$value])) throw new \Exception('No such backend');
        $this->data['backend'] = $value;
    }

    /**
     * @return array
     * @throws \Exception
     */
    public function getConfigurationDescription()
    {
        if (!$this->backend) throw new \Exception('Backend not set');
        $backends = self::listBackends();
        return $backends[$this->backend];
    }

    /**
     * @param array $array
     * @throws \Exception
     */
    public function setConfiguration($array)
    {
        $this->config = json_encode($array);
    }

    /**
     * @return array
     */
    public function getConfiguration()
    {
        return json_decode($this->config, true);
    }

    /**
     * Validate before 
     *
     * @throws \Exception
     */
    public function prePersist() {
        $array = $this->getConfiguration();
        $config = $this->getConfigurationDescription();
        foreach ($config as $key => $info) {
            if (!isset($info['optional']) || !$info['optional']) {
                if (!isset($array[$key]) || $array[$key] === '') {
                    throw new \Exception('Not all required config options are set');
                }
            }
        }
    }

    /**
     * Executes the check of setup at the backend
     *
     * @return array
     */
    public function checkConfig() {
        $result = [
            'ok'  => true,
            'info' => '',
        ];

        try {
            $class = '\\splitbrain\\TheBankster\\Backend\\'.$this->backend;
            /** @var AbstractBackend $obj */
            $obj = new $class($this->configuration, $this->account);
            $result['info'] = $obj->checkSetup();
        } catch (\Exception $e) {
            $result['ok'] = false;
            $result['info'] = $e->getMessage();
        }

        return $result;
    }
}