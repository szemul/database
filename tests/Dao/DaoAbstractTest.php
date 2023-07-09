<?php
declare(strict_types=1);

namespace Szemul\Database\Test\Dao;

use Mockery;
use Mockery\LegacyMockInterface;
use Mockery\MockInterface;
use Szemul\Database\Connection\MysqlConnection;
use Szemul\Database\Factory\MysqlFactory;
use Szemul\Database\Helper\QueryHelper;
use Szemul\Database\Dao\DaoAbstract;
use PHPUnit\Framework\TestCase;

class DaoAbstractTest extends TestCase
{
    private DaoAbstract $sut;
    private MysqlFactory|MockInterface|LegacyMockInterface $mysqlFactory;
    private QueryHelper|MockInterface|LegacyMockInterface $queryHelper;

    protected function setUp(): void
    {
        $this->mysqlFactory = Mockery::mock(MysqlFactory::class);
        $this->queryHelper  = Mockery::mock(QueryHelper::class);

        $this->sut = new class ($this->mysqlFactory, $this->queryHelper) extends DaoAbstract {
            public function getConnection(bool $isReadOnly = false): MysqlConnection
            {
                return parent::getConnection($isReadOnly);
            }
        };
    }

    public function testGetConnectionWithReadWrite(): void
    {
        $mock = Mockery::mock(MysqlConnection::class);

        //@phpstan-ignore-next-line
        $this->mysqlFactory->shouldReceive('get')
            ->once()
            ->with(false)
            ->andReturn($mock);

        $this->assertSame($mock, $this->sut->getConnection()); // @phpstan-ignore-line
    }

    public function testGetConnectionWithReadOnly(): void
    {
        $mock = Mockery::mock(MysqlConnection::class);

        //@phpstan-ignore-next-line
        $this->mysqlFactory->shouldReceive('get')
            ->once()
            ->with(true)
            ->andReturn($mock);

        $this->assertSame($mock, $this->sut->getConnection(true)); // @phpstan-ignore-line
    }
}
