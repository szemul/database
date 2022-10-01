<?php

declare(strict_types=1);

namespace Szemul\Database\Connection;

use JetBrains\PhpStorm\Pure;
use Szemul\Database\Config\MysqlConfig;
use Szemul\Database\Helper\MysqlErrorHelper;

class ConnectionFactory
{
    #[Pure]
    public static function getMysql(MysqlConfig $config): MysqlConnection
    {
        return new MysqlConnection($config, new MysqlErrorHelper());
    }
}
