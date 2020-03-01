<?php

namespace phpload\core\components;

use yii\mutex\DbMutex;
use yii\base\InvalidConfigException;

class SqliteMutex extends DbMutex
{
    /**
     * Initializes Sqlite specific mutex component implementation.
     * @throws InvalidConfigException if [[db]] is not MySQL connection.
     */
    public function init()
    {
        parent::init();
        if ($this->db->driverName !== 'sqlite') {
            throw new InvalidConfigException('In order to use SqliteMutex connection must be configured to use Sqlite database.');
        }
    }

    /**
     * Acquires lock by given name.
     * @param string $name of the lock to be acquired.
     * @param int $timeout time (in seconds) to wait for lock to become released.
     * @return bool acquiring result.
     * @see http://dev.mysql.com/doc/refman/5.0/en/miscellaneous-functions.html#function_get-lock
     */
    protected function acquireLock($name, $timeout = 0)
    {
        return $this->db->useMaster(function ($db) use ($name, $timeout) {
            /** @var \yii\db\Connection $db */
            return (bool) $db->createCommand(
                'SELECT GET_LOCK(:name, :timeout)',
                [':name' => $this->hashLockName($name), ':timeout' => $timeout]
            )->queryScalar();
        });
    }

    /**
     * Releases lock by given name.
     * @param string $name of the lock to be released.
     * @return bool release result.
     * @see http://dev.mysql.com/doc/refman/5.0/en/miscellaneous-functions.html#function_release-lock
     */
    protected function releaseLock($name)
    {
        return $this->db->useMaster(function ($db) use ($name) {
            /** @var \yii\db\Connection $db */
            return (bool) $db->createCommand(
                'SELECT RELEASE_LOCK(:name)',
                [':name' => $this->hashLockName($name)]
            )->queryScalar();
        });
    }

    /**
     * Generate hash for lock name to avoid exceeding lock name length limit.
     *
     * @param string $name
     * @return string
     * @since 2.0.16
     * @see https://github.com/yiisoft/yii2/pull/16836
     */
    protected function hashLockName($name) {
        return sha1($name);
    }
}
