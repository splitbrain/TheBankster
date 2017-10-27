<?php

namespace splitbrain\TheBankster;

use ORM\DbConfig;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;


/**
 * Class Container
 *
 * @property \ORM\EntityManager db
 */
class Container extends \Slim\Container
{
    /** @var Container */
    static protected $instance;

    /** @var  LoggerInterface */
    protected $logger;

    /**
     * Returns the initialized singleton instance of the container
     *
     * @return Container
     */
    public static function getInstance()
    {
        if (self::$instance === null) {
            $configuration = []; // FIXME
            self::$instance = new Container($configuration);
        }
        return self::$instance;
    }

    /**
     * Container constructor.
     *
     * You should not call this, but use getInstance() instead
     *
     * @param array $values
     */
    public function __construct(array $values = [])
    {
        parent::__construct($values);

        // default logger
        $this->logger = new NullLogger();


        // DataBase Entity Manager
        $this['db'] = function () {
            $em = new EntityManager([
                EntityManager::OPT_CONNECTION => new DbConfig(
                    'sqlite',
                    __DIR__ . '/../data.sqlite3'
                )
            ]);
            $em->getConnection()->exec('PRAGMA foreign_keys = ON');
            return $em;
        };
    }

    /**
     * Change the logger from the default
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger)
    {
        $this->logger = $logger;
    }

    /**
     * Get the logger
     *
     * @return LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }
}