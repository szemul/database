<?php
declare(strict_types=1);

namespace Szemul\Database\Helper;

class QueryHelper
{
    /** Interval unit for day. */
    public const INTERVAL_UNIT_DAY = 'day';
    /** Interval unit for month. */
    public const INTERVAL_UNIT_MONTH = 'month';
    /** Interval unit for year. */
    public const INTERVAL_UNIT_YEAR = 'year';

    public function __construct(protected string $paramPrefix = '')
    {
    }

    /**
     * Generates a condition like [tableAlias].[fieldName] = [expectedValue]
     *
     * @param string[]            $conditions
     * @param array<string,mixed> $queryParams
     */
    public function getEqualityCondition(
        string $fieldName,
        mixed $expectation,
        array &$conditions,
        array &$queryParams,
        string $tableAlias = '',
        bool $onlyNullConsideredEmpty = false,
    ): void {
        if (
            (!$onlyNullConsideredEmpty && empty($expectation))
            ||
            ($onlyNullConsideredEmpty && is_null($expectation))
        ) {
            return;
        }

        if (is_bool($expectation)) {
            $expectation = (int)$expectation;
        }

        $paramName = $this->getParamName($fieldName, $tableAlias);
        $fieldName = $this->getPrefixedField($tableAlias, $fieldName);

        $conditions[]            = $fieldName . ' = :' . $this->paramPrefix . $paramName;
        $queryParams[$paramName] = $expectation;
    }

    /**
     * Generates a condition like [tableAlias].[fieldName] IN ([list])
     *
     * @param mixed[]             $list
     * @param string[]            $conditions
     * @param array<string,mixed> $queryParams
     */
    public function getInListCondition(
        string $fieldName,
        array $list,
        array &$conditions,
        array &$queryParams,
        string $tableAlias = '',
        bool $isNegated = false,
    ): void {
        if (empty($list)) {
            return;
        }

        $paramNames = [];
        foreach ($list as $index => $item) {
            $paramName = $this->getParamName($fieldName, $tableAlias, $index);

            $paramNames[]            = ':' . $this->paramPrefix . $paramName;
            $queryParams[$paramName] = $item;
        }

        $operator = $isNegated ? 'NOT IN' : 'IN';

        $conditions[] = $this->getPrefixedField($tableAlias, $fieldName) . ' ' . $operator
            . ' (' . implode(', ', $paramNames) . ')';
    }

    /**
     * Returns the name of the given field with the proper prefix.
     */
    protected function getPrefixedField(string $tableAlias, string $fieldName): string
    {
        $prefix = empty($tableAlias) ? '' : $tableAlias . '.';

        return $prefix . $fieldName;
    }

    /**
     * Creates a parameter name based on the given field and table.
     */
    protected function getParamName(string $fieldName, string $tableAlias = '', ?int $index = null): string
    {
        $paramNameParts = [];

        if (!empty($tableAlias)) {
            $paramNameParts[] = $tableAlias;
        }
        $paramNameParts[] = $fieldName;

        if (!is_null($index)) {
            $paramNameParts[] = $index;
        }

        return implode('_', $paramNameParts);
    }
}
