<?php
declare(strict_types=1);

namespace Szemul\Database\Factory;

use Szemul\Database\Connection\MysqlConnection;

class MysqlFactory implements FactoryInterface
{
    public function __construct(protected MysqlConnection $readOnlyConnection, protected MysqlConnection $readWriteConnection)
    {
    }

    public function getReadOnly(): MysqlConnection
    {
        return $this->readOnlyConnection;
    }

    public function getReadWrite(): MysqlConnection
    {
        return $this->readWriteConnection;
    }

    public function get(bool $isReadOnly = false): MysqlConnection
    {
        return $isReadOnly ? $this->getReadOnly() : $this->getReadWrite();
    }
}
