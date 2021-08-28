<?php
declare(strict_types=1);

namespace Szemul\Database\Factory;

use Szemul\Database\Connection\DbConnectionAbstract;

interface FactoryInterface
{
    public function get(bool $isReadOnly = false): DbConnectionAbstract;
}
