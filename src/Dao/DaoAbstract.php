<?php
declare(strict_types=1);

namespace Szemul\Database\Dao;

use Szemul\Database\Connection\MysqlConnection;
use Szemul\Database\Factory\MysqlFactory;
use Szemul\Database\Helper\QueryHelper;

abstract class DaoAbstract
{
    public function __construct(protected MysqlFactory $factory, protected QueryHelper $queryHelper)
    {
    }

    protected function getConnection(bool $isReadOnly = false): MysqlConnection
    {
        return $this->factory->get($isReadOnly);
    }
}
