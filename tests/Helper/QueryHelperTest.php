<?php

namespace Szemul\Database\Test\Helper;

use Mockery\MockInterface;
use PHPUnit\Framework\TestCase;
use Szemul\Database\Connection\MysqlConnection;
use Szemul\Database\Helper\QueryHelper;
use Szemul\Database\Result\QueryResult;

class QueryHelperTest extends TestCase
{
    private MysqlConnection|MockInterface $connection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->connection = \Mockery::mock(MysqlConnection::class);
    }

    public function equalityConditionProvider(): array
    {
        $fieldName = 'field';

        return [
            'int'       => [$fieldName, 11, [$fieldName => 11], ['field = :field']],
            'string'    => [$fieldName, 'test', [$fieldName => 'test'], ['field = :field']],
            'bool-true' => [$fieldName, true, [$fieldName => 1], ['field = :field']],
        ];
    }

    /**
     * @dataProvider equalityConditionProvider
     */
    public function testGetEqualityCondition(string $fieldName, mixed $expectation, array $expectedParams, array $expectedConditions): void
    {
        $conditions = $params = [];

        (new QueryHelper())->getEqualityCondition($fieldName, $expectation, $conditions, $params);

        $this->assertSame($expectedParams, $params);
        $this->assertSame($expectedConditions, $conditions);
    }

    public function testGetEqualityConditionWhenEmptyExpectationGiven_shouldDoNothing(): void
    {
        $fieldName   = 'field';
        $expectation = '';
        $conditions  = $params = [];

        (new QueryHelper())->getEqualityCondition($fieldName, $expectation, $conditions, $params);

        $this->assertEmpty($conditions);
        $this->assertEmpty($params);
    }

    public function testGetEqualityConditionWhenEmptyExpectationGivenButOnlyNullConsideredEmpty_shouldAddCondition(): void
    {
        $fieldName   = 'field';
        $expectation = '';
        $conditions  = $params = [];

        (new QueryHelper())->getEqualityCondition($fieldName, $expectation, $conditions, $params, onlyNullConsideredEmpty: true);

        $this->assertEquals(['field = :field'], $conditions);
        $this->assertEquals(['field' => ''], $params);
    }

    public function testGetEqualityConditionWhenNullExpectationGivenAndOnlyNullConsideredEmpty_shouldDoNothing(): void
    {
        $fieldName   = 'field';
        $expectation = null;
        $conditions  = $params = [];

        (new QueryHelper())->getEqualityCondition($fieldName, $expectation, $conditions, $params, onlyNullConsideredEmpty: true);

        $this->assertEmpty($conditions);
        $this->assertEmpty($params);
    }

    public function testGetEqualityConditionWhenTableAliasGiven_shouldPrefixParam(): void
    {
        $fieldName   = 'field';
        $expectation = 'value';
        $tableAlias  = 'T';
        $conditions  = $params = [];

        (new QueryHelper())->getEqualityCondition($fieldName, $expectation, $conditions, $params, $tableAlias);

        $this->assertEquals(['T.field = :T_field'], $conditions);
        $this->assertEquals(['T_field' => $expectation], $params);
    }

    public function testGetInListConditionWhenEmptyListGiven_shouldDoNothing(): void
    {
        $fieldName  = 'field';
        $list       = [];
        $conditions = $params = [];

        (new QueryHelper())->getInListCondition($fieldName, $list, $conditions, $params);

        $this->assertEmpty($conditions);
        $this->assertEmpty($params);
    }

    public function testGetInListCondition_shouldCreateConditionWithInOperator(): void
    {
        $fieldName  = 'field';
        $list       = [1, 2];
        $conditions = $params = [];

        (new QueryHelper())->getInListCondition($fieldName, $list, $conditions, $params);

        $this->assertEquals(['field IN (:field_0, :field_1)'], $conditions);
        $this->assertEquals(['field_0' => 1, 'field_1' => 2], $params);
    }

    public function testGetInListConditionWhenNegated_shouldCreateConditionWithNotInOperator(): void
    {
        $fieldName  = 'field';
        $list       = [1, 2];
        $conditions = $params = [];

        (new QueryHelper())->getInListCondition($fieldName, $list, $conditions, $params, isNegated: true);

        $this->assertEquals(['field NOT IN (:field_0, :field_1)'], $conditions);
        $this->assertEquals(['field_0' => 1, 'field_1' => 2], $params);
    }

    public function testGetInListConditionWhenTableAlisGiven_shouldPrefixParamsWithAlias(): void
    {
        $fieldName  = 'field';
        $list       = [1, 2];
        $tableAlias = 'T';
        $conditions = $params = [];

        (new QueryHelper())->getInListCondition($fieldName, $list, $conditions, $params, $tableAlias);

        $this->assertEquals(['T.field IN (:T_field_0, :T_field_1)'], $conditions);
        $this->assertEquals(['T_field_0' => 1, 'T_field_1' => 2], $params);
    }

    public function testGetListFromTableByIdsWhenNoIdsGiven_shouldDoNothing(): void
    {
        $table  = 'table';
        $fields = ['field'];
        $ids    = [];

        $result = (new QueryHelper())->getListFromTableByIds($this->connection, $table, $fields, $ids);

        $this->assertEmpty($result);
    }

    public function testGetListFromTableByIdsWhenNoFieldsGiven_shouldThrowException(): void
    {
        $table  = 'table';
        $fields = [];
        $ids    = [1];

        $this->expectException(\InvalidArgumentException::class);
        (new QueryHelper())->getListFromTableByIds($this->connection, $table, $fields, $ids);
    }

    public function testGetIdListParamNames(): void
    {
        $ids    = [1, 2];
        $params = [];

        $paramNames = (new QueryHelper())->getIdListParamNames($ids, $params);

        $this->assertSame(['id_0' => 1, 'id_1' => 2], $params);
        $this->assertSame([':id_0', ':id_1'], $paramNames);
    }

    public function testGetListFromTableByIds(): void
    {
        $table  = 'table';
        $fields = ['field1', 'field2'];
        $ids    = [1, 2];

        $expectedQuery  = '
            SELECT
                `field1`, `field2`
            FROM
                `table`
            WHERE
                `identifier` IN (:id_0,:id_1)
            ORDER BY
                FIELD(`identifier`, :id_0,:id_1)
        ';
        $expectedParams = [
            'id_0' => 1,
            'id_1' => 2,
        ];
        $expectedResult = ['result'];

        $this->expectListRetrieved($expectedQuery, $expectedParams, $expectedResult);

        $result = (new QueryHelper())->getListFromTableByIds($this->connection, $table, $fields, $ids, 'identifier');

        $this->assertSame($expectedResult, $result);
    }

    private function expectListRetrieved(string $expectedQuery, array $expectedParams, array $expectedResult): void
    {
        $queryResult = \Mockery::mock(QueryResult::class)
            ->expects('fetchAll')
            ->andReturn($expectedResult)
            ->getMock();

        $this->connection
            ->expects('query')
            ->with(
                \Mockery::on(
                    function (string $query) use ($expectedQuery) {
                        return preg_replace("/\s+/", ' ', $query) === preg_replace("/\s+/", ' ', $expectedQuery);
                    },
                ),
                $expectedParams,
            )
            ->andReturn($queryResult);
    }
}
