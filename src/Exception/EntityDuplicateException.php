<?php

declare(strict_types=1);

namespace Szemul\Database\Exception;

use Exception;

class EntityDuplicateException extends QueryException
{
    private ?string $entityName;

    public function __construct(?string $entityName, Exception $previousException)
    {
        $message = empty($entityName)
            ? 'Entity already exits'
            : $entityName . ' already exists';
        parent::__construct($message, 1062, $previousException);

        $this->entityName = $entityName;
    }

    public function getEntityName(): ?string
    {
        return $this->entityName;
    }
}
