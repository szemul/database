<?php

declare(strict_types=1);

namespace Szemul\Database\Exception;

use Exception;

class EntityNotFoundException extends Exception
{
    private string $entityName;

    /** @var array<string, mixed> */
    private array $searchConditions;

    /**
     * @param array<string, mixed> $searchConditions
     */
    public function __construct(string $entityName, array $searchConditions = [])
    {
        parent::__construct('Entity ' . $entityName . ' not found');

        $this->entityName       = $entityName;
        $this->searchConditions = $searchConditions;
    }

    public function getEntityName(): string
    {
        return $this->entityName;
    }

    /**
     * @return array<string, mixed>
     */
    public function getSearchConditions(): array
    {
        return $this->searchConditions;
    }
}
