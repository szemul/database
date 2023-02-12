<?php
declare(strict_types=1);

namespace Szemul\Database\Test\Connection;

use Carbon\Carbon;
use DateTimeZone;
use Mockery;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use PDO;
use PDOException;
use PDOStatement;
use PHPUnit\Framework\TestCase;
use Szemul\Database\Config\GenericOptions;
use Szemul\Database\Debugging\DatabaseCompleteEvent;
use Szemul\Database\Debugging\DatabaseStartEvent;
use Szemul\Database\Exception\ConnectionException;
use Szemul\Database\Exception\QueryException;
use Szemul\Database\Test\Mock\MockDbConnection;
use Szemul\Debugger\DebuggerInterface;
use Szemul\Debugger\Event\DebugEventInterface;

/**
 * @covers \Szemul\Database\Connection\DbConnectionAbstract
 */
class DbConnectionAbstractTest extends TestCase
{
    use MockeryPHPUnitIntegration;

    private const PARAM_PREFIX          = '_';
    private const CONNECTION_NAME       = 'testConnection';
    private const PDO_OPTIONS           = [
        'TEST' => 'value',
    ];
    private const PDO_EXCEPTION_MESSAGE = 'SQLSTATE[123]: Test error';
    private const DB_EXCEPTION_MESSAGE  = 'Test error';
    private const DB_EXCEPTION_CODE     = 123;

    private MockDbConnection                      $sut;
    private PDO|MockInterface|LegacyMockInterface $pdo;
    private GenericOptions                        $genericOptions;

    public function setUp(): void
    {
        parent::setUp();

        $this->pdo            = Mockery::mock(PDO::class);
        $this->genericOptions = new GenericOptions(self::CONNECTION_NAME, self::PARAM_PREFIX, self::PDO_OPTIONS);
        $this->sut            = new MockDbConnection($this->pdo, $this->genericOptions); //@phpstan-ignore-line
    }

    public function testGetConnection(): void
    {
        $this->expectConnection();
        $this->assertSame($this->pdo, $this->sut->getConnection());
    }

    public function testConnectAndDisconnect(): void
    {
        $this->expectConnection();
        $this->assertNull($this->sut->getCurrentConnection());
        $this->sut->connect();
        $this->assertSame($this->pdo, $this->sut->getCurrentConnection());
        $this->sut->disconnect();
        $this->assertNull($this->sut->getCurrentConnection());
    }

    public function testFailedConnection_shouldThrowException(): void
    {
        $this->expectException(ConnectionException::class);
        $this->expectExceptionMessage(self::DB_EXCEPTION_MESSAGE);
        $this->expectExceptionCode(self::DB_EXCEPTION_CODE);

        $this->sut->connectionException = new PDOException(self::PDO_EXCEPTION_MESSAGE);

        $this->sut->connect();
    }

    public function testGetParamPrefix(): void
    {
        $this->assertSame(self::PARAM_PREFIX, $this->sut->getParamPrefix());
    }

    public function testSuccessfulQueryWithNoParamsOrDebugger_shouldReturnResult(): void
    {
        $this->expectConnection();
        $query     = 'SELECT NOW()';
        $statement = $this->expectQueryPrepared($query);
        $this->expectStatementExecuted($statement);

        $this->assertSame(
            $statement,
            $this->sut->query($query)
                ->getPdoStatement(),
        );
    }

    public function testSuccessfulQueryWithParamsAndNoDebugger_shouldReturnResult(): void
    {
        $this->expectConnection();
        $query     = 'SELECT * FROM test WHERE test = :_valueKey';
        $statement = $this->expectQueryPrepared($query);
        $params    = [
            'valueKey' => 'value',
        ];
        $this->expectStatementExecuted($statement);
        $this->expectParamsBound($statement, $params);

        $this->assertSame(
            $statement,
            $this->sut->query($query, $params)
                ->getPdoStatement(),
        );
    }

    public function testFailedQueryWithNoDebugger_shouldThrowException(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionCode(self::DB_EXCEPTION_CODE);
        $this->expectExceptionMessage(self::DB_EXCEPTION_MESSAGE);
        $this->expectConnection();

        $query     = 'SELECT NOW()';
        $statement = $this->expectQueryPrepared($query);
        $this->expectExceptionWhileStatementIsExecuted($statement);

        $this->sut->query($query);
    }

    public function testSuccessfulQueryWithNoParamsWithDebugger_shouldReturnResult(): void
    {
        $this->expectConnection();
        $debugger = $this->getDebugger();
        $this->sut->setDebugger($debugger);
        $query     = 'SELECT NOW()';
        $statement = $this->expectQueryPrepared($query);
        $this->expectStatementExecuted($statement);
        $this->expectDebugStartEvent($debugger, $query);
        $this->expectSuccessfulDebugCompleteEvent($debugger);

        $this->assertSame(
            $statement,
            $this->sut->query($query)
                ->getPdoStatement(),
        );
    }

    public function testSuccessfulQueryWithParamsAndDebugger_shouldReturnResult(): void
    {
        $this->expectConnection();
        $debugger = $this->getDebugger();
        $this->sut->setDebugger($debugger);
        $query     = 'SELECT * FROM test WHERE test = :_valueKey';
        $statement = $this->expectQueryPrepared($query);
        $params    = [
            'valueKey' => 'value',
        ];
        $this->expectStatementExecuted($statement);
        $this->expectParamsBound($statement, $params);
        $this->expectDebugStartEvent($debugger, $query, $params);
        $this->expectSuccessfulDebugCompleteEvent($debugger);

        $this->assertSame(
            $statement,
            $this->sut->query($query, $params)
                ->getPdoStatement(),
        );
    }

    public function testFailedQueryWithDebugger_shouldThrowException(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionCode(self::DB_EXCEPTION_CODE);
        $this->expectExceptionMessage(self::DB_EXCEPTION_MESSAGE);
        $this->expectConnection();
        $debugger = $this->getDebugger();
        $this->sut->setDebugger($debugger);
        $query     = 'SELECT NOW()';
        $statement = $this->expectQueryPrepared($query);
        $this->expectExceptionWhileStatementIsExecuted($statement);
        $this->expectDebugStartEvent($debugger, $query);
        $this->expectFailedDebugCompleteEvent($debugger);

        $this->sut->query($query);
    }

    /** @dataProvider getQueryPagedValues */
    public function testQueryPagedWithoutItemCount_shouldNotCalculateFoundRows(int $pageNumber, int $itemsPerPage, int $offset): void
    {
        $this->expectConnection();
        $query     = 'SELECT NOW()';
        $statement = $this->expectQueryPrepared($query . " LIMIT $itemsPerPage OFFSET $offset");
        $this->expectStatementExecuted($statement);

        $this->assertSame(
            $statement,
            $this->sut->queryPaged($query, [], $pageNumber, $itemsPerPage)
                ->getPdoStatement(),
        );
    }

    /** @dataProvider getQueryPagedValues */
    public function testQueryPagedWithItemCount_shouldCalculateFoundRows(int $pageNumber, int $itemsPerPage, int $offset): void
    {
        $this->expectConnection();
        $query     = 'SELECT NOW()';
        $statement = $this->expectQueryPrepared("SELECT SQL_CALC_FOUND_ROWS NOW() LIMIT $itemsPerPage OFFSET $offset");
        $foundRows = $this->expectQueryPrepared('SELECT FOUND_ROWS()');
        $this->expectStatementExecuted($statement);
        $this->expectStatementExecuted($foundRows);
        $this->expectColumnFetchedFromStatement($foundRows, '100');
        $itemCount = 0;

        $this->assertSame(
            $statement,
            $this->sut->queryPaged($query, [], $pageNumber, $itemsPerPage, $itemCount)
                ->getPdoStatement(),
        );
        $this->assertSame(100, $itemCount);
    }

    public function testSuccessfulTransactionHandling(): void
    {
        $this->expectConnection();
        $this->expectTransactionBegun();
        $this->expectTransactionCommitted();

        $this->sut->beginTransaction();
        $this->sut->beginTransaction();
        $this->sut->completeTransaction();
        $this->sut->completeTransaction();
    }

    public function testFailedTransactionHandling(): void
    {
        $this->expectConnection();
        $this->expectTransactionBegun();
        $this->expectTransactionRolledBack();

        $this->sut->beginTransaction();
        $this->sut->failTransaction();
        $this->sut->beginTransaction();
        $this->sut->completeTransaction();
        $this->sut->completeTransaction();
    }

    public function testSuccessfulLastInsertIdWithNoName(): void
    {
        $this->expectConnection();
        $this->expectLastInsertId('100');

        $this->assertSame('100', $this->sut->lastInsertId());
    }

    public function testSuccessfulLastInsertIdWithName(): void
    {
        $this->expectConnection();
        $this->expectLastInsertId('100', 'test');

        $this->assertSame('100', $this->sut->lastInsertId('test'));
    }

    public function testFailedLastInsertId(): void
    {
        $this->expectException(QueryException::class);
        $this->expectExceptionMessage(self::DB_EXCEPTION_MESSAGE);
        $this->expectExceptionCode(self::DB_EXCEPTION_CODE);

        $this->expectConnection();
        $this->expectLastInsertIdThrowsException();

        $this->sut->lastInsertId();
    }

    public function testEscapeWildcards(): void
    {
        $this->assertSame('test.\_.\%', $this->sut->escapeWildcards('test._.%'));
    }

    public function testGetFormattedDateTimeWithDate_shouldReturnTheFormattedDateTime(): void
    {
        $carbon = new Carbon('2021-01-01T00:00:00Z', new DateTimeZone('UTC'));

        $this->assertSame('2021-01-01 00:00:00', $this->sut->getFormattedDateTime($carbon));
    }

    public function testGetFormattedDateTimeWithNull_shouldReturnNull(): void
    {
        $this->assertNull($this->sut->getFormattedDateTime(null));
    }

    public function testGetFormattedDateWithDate_shouldReturnTheFormattedDate(): void
    {
        $carbon = new Carbon('2021-01-01T00:00:00Z', new DateTimeZone('UTC'));

        $this->assertSame('2021-01-01', $this->sut->getFormattedDate($carbon));
    }

    public function testGetFormattedDateWithNull_shouldReturnNull(): void
    {
        $this->assertNull($this->sut->getFormattedDate(null));
    }

    /** @return int[][] */
    public function getQueryPagedValues(): array
    {
        return [
            [1, 10, 0],
            [2, 15, 15],
        ];
    }

    private function expectLastInsertId(string $result, ?string $name = null): void
    {
        // @phpstan-ignore-next-line
        $this->pdo->shouldReceive('lastInsertId')
            ->once()
            ->with($name)
            ->andReturn($result);
    }

    private function expectLastInsertIdThrowsException(): void
    {
        // @phpstan-ignore-next-line
        $this->pdo->shouldReceive('lastInsertId')
            ->once()
            ->with(null)
            ->andThrow(new PDOException(self::PDO_EXCEPTION_MESSAGE));
    }

    private function expectTransactionBegun(): void
    {
        // @phpstan-ignore-next-line
        $this->pdo->shouldReceive('beginTransaction')
            ->once()
            ->withNoArgs();
    }

    private function expectTransactionCommitted(): void
    {
        // @phpstan-ignore-next-line
        $this->pdo->shouldReceive('commit')
            ->once()
            ->withNoArgs()
            ->andReturn(true);
    }

    private function expectTransactionRolledBack(): void
    {
        // @phpstan-ignore-next-line
        $this->pdo->shouldReceive('rollBack')
            ->once()
            ->withNoArgs()
            ->andReturn(true);
    }

    private function expectConnection(): void
    {
        // @phpstan-ignore-next-line
        $this->pdo->shouldReceive('setAttribute')
            ->once()
            ->with(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    private function expectQueryPrepared(string $query): PDOStatement|MockInterface
    {
        /** @var PDOStatement|MockInterface $statement */
        $statement = Mockery::mock(PDOStatement::class);
        // @phpstan-ignore-next-line
        $this->pdo->shouldReceive('prepare')
            ->once()
            ->with(
                Mockery::on(
                    function (string $receivedQuery) use ($query) {
                        $this->assertSame($query, preg_replace('/\s+/', ' ', $receivedQuery));

                        return true;
                    },
                ),
            )
            ->andReturn($statement);

        return $statement;
    }

    private function expectStatementExecuted(MockInterface $statement): void
    {
        // @phpstan-ignore-next-line
        $statement->shouldReceive('execute')
            ->once()
            ->withNoArgs();
    }

    private function expectExceptionWhileStatementIsExecuted(MockInterface $statement): void
    {
        $exception = new PDOException(self::PDO_EXCEPTION_MESSAGE);

        // @phpstan-ignore-next-line
        $statement->shouldReceive('execute')
            ->once()
            ->withNoArgs()
            ->andThrow($exception);
    }

    /** @param array<string,mixed> $params */
    private function expectParamsBound(MockInterface $statement, array $params): void
    {
        foreach ($params as $key => $value) {
            // @phpstan-ignore-next-line
            $statement->shouldReceive('bindValue')
                ->once()
                ->with(':' . self::PARAM_PREFIX . $key, $value);
        }
    }

    private function getDebugger(): DebuggerInterface|MockInterface|LegacyMockInterface
    {
        return Mockery::mock(DebuggerInterface::class);
    }

    /**
     * @param array<string,mixed> $params
     */
    private function expectDebugStartEvent(
        DebuggerInterface|MockInterface|LegacyMockInterface $debugger,
        string $query,
        array $params = [],
    ): void {
        // @phpstan-ignore-next-line
        $debugger->shouldReceive('handleEvent')
            ->once()
            ->with(
                Mockery::on(
                    function (DebugEventInterface $event) use ($query, $params): bool {
                        if (!($event instanceof DatabaseStartEvent)) {
                            return false;
                        }

                        $this->assertSame($query, $event->getQuery());
                        $this->assertEquals($params, $event->getParams());
                        $this->assertSame(self::CONNECTION_NAME, $event->getConnectionName());
                        $this->assertSame(MockDbConnection::BACKEND_TYPE, $event->getBackendType());

                        return true;
                    },
                ),
            );
    }

    private function expectSuccessfulDebugCompleteEvent(
        DebuggerInterface|MockInterface|LegacyMockInterface $debugger,
    ): void {
        // @phpstan-ignore-next-line
        $debugger->shouldReceive('handleEvent')
            ->once()
            ->with(
                Mockery::on(
                    function (DebugEventInterface $event): bool {
                        if (!($event instanceof DatabaseCompleteEvent)) {
                            return false;
                        }
                        $this->assertTrue($event->isSuccessful());
                        $this->assertNull($event->getException());

                        return true;
                    },
                ),
            );
    }

    private function expectFailedDebugCompleteEvent(
        DebuggerInterface|MockInterface|LegacyMockInterface $debugger,
    ): void {
        // @phpstan-ignore-next-line
        $debugger->shouldReceive('handleEvent')
            ->once()
            ->with(
                Mockery::on(
                    function (DebugEventInterface $event): bool {
                        if (!($event instanceof DatabaseCompleteEvent)) {
                            return false;
                        }
                        $this->assertFalse($event->isSuccessful());
                        $this->assertNotNull($event->getException());
                        $exception = $event->getException();
                        $this->assertInstanceOf(PDOException::class, $exception);
                        $this->assertSame(self::PDO_EXCEPTION_MESSAGE, $exception->getMessage());

                        return true;
                    },
                ),
            );
    }

    private function expectColumnFetchedFromStatement(MockInterface $statement, ?string $result): void
    {
        // @phpstan-ignore-next-line
        $statement->shouldReceive('fetchColumn')
            ->once()
            ->with(0)
            ->andReturn($result);
    }
}
